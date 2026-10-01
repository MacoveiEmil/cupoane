<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Support\Catalog;
use App\Support\PageCache;
use Illuminate\Console\Command;

/**
 * Conținutul unic al paginilor de magazin, ca fișier JSON — ușor de completat de un om sau de un AI.
 *
 *   php artisan cupons:content export storage/app/continut.json          (toate magazinele)
 *   php artisan cupons:content export storage/app/continut.json --empty  (doar cele fără FAQ / text)
 *   php artisan cupons:content import storage/app/continut.json [--dry-run]
 *
 * Format: {"answear-ro": {"offers_text": "<p>…</p>", "description": "<p>…</p>",
 *          "faq": [{"q": "…", "a": "…"}], "howto": [{"t": "…", "p": "…"}], "tips": [{"t": "…", "p": "…"}]}}
 * La import se actualizează doar cheile prezente; magazinele lipsă din fișier rămân neschimbate.
 */
class ContentCommand extends Command
{
    protected $signature = 'cupons:content {action : export | import} {file} {--empty : la export, doar magazinele fără conținut propriu} {--dry-run : la import, doar verifică fișierul}';

    protected $description = 'Exportă / importă conținutul unic al paginilor de magazin (JSON, pentru completare manuală sau cu AI)';

    private const LISTS = ['faq' => ['q', 'a'], 'howto' => ['t', 'p'], 'tips' => ['t', 'p']];

    private const HTML = ['offers_text', 'description'];

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'export' => $this->export((string) $this->argument('file')),
            'import' => $this->import((string) $this->argument('file')),
            default => $this->fail('Acțiune necunoscută: folosește export sau import.'),
        };
    }

    /** HTML simplu din fișier: doar tag-uri de text, fără atribute (în afară de href-uri http / relative). */
    private static function cleanHtml(string $html): string
    {
        $html = strip_tags($html, '<p><br><strong><b><em><i><ul><ol><li><h3><a>');
        $html = preg_replace_callback('/<(\/?)(\w+)([^>]*)>/', function ($m) {
            if (strtolower($m[2]) === 'a' && $m[1] === '' && preg_match('/href\s*=\s*"((?:https?:\/\/|\/)[^"]*)"/i', $m[3], $h)) {
                return '<a href="'.e($h[1]).'">';
            }

            return '<'.$m[1].strtolower($m[2]).'>';
        }, $html) ?? '';

        return trim($html);
    }

    private function export(string $file): int
    {
        $out = [];
        foreach (Store::orderBy('name')->get() as $s) {
            if ($this->option('empty') && $s->hasOwnContent()) {
                continue;
            }
            $out[$s->slug] = [
                '_nume' => $s->name,
                '_site' => $s->url,
                '_oferte_active' => Catalog::count($s),
                '_categorii' => $s->categories->pluck('name')->all(),
                'offers_text' => (string) $s->offers_text,
                'description' => (string) $s->description,
                'faq' => array_values($s->faq ?? []),
                'howto' => array_values($s->howto ?? []),
                'tips' => array_values($s->tips ?? []),
            ];
        }
        file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->info(count($out).' magazine exportate în '.$file.' (cheile cu „_” sunt doar informative).');

        return self::SUCCESS;
    }

    private function import(string $file): int
    {
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($data)) {
            return $this->fail('Fișier lipsă sau JSON invalid: '.$file);
        }

        $errors = [];
        $updates = [];
        foreach ($data as $slug => $fields) {
            $store = Store::where('slug', $slug)->first();
            if (! $store || ! is_array($fields)) {
                $errors[] = "{$slug}: magazin inexistent";

                continue;
            }
            $set = [];
            foreach (self::HTML as $key) {
                if (array_key_exists($key, $fields)) {
                    $set[$key] = self::cleanHtml((string) $fields[$key]) ?: null;
                }
            }
            foreach (self::LISTS as $key => [$a, $b]) {
                if (! array_key_exists($key, $fields)) {
                    continue;
                }
                $rows = [];
                foreach ((array) $fields[$key] as $i => $row) {
                    if (! is_array($row) || trim((string) ($row[$a] ?? '')) === '' || trim((string) ($row[$b] ?? '')) === '') {
                        $errors[] = "{$slug}.{$key}[{$i}]: trebuie să aibă „{$a}” și „{$b}” completate";

                        continue;
                    }
                    $rows[] = [$a => trim($row[$a]), $b => trim($row[$b])];
                }
                $set[$key] = $rows;
            }
            $updates[] = [$store, $set];
        }

        foreach ($errors as $e) {
            $this->warn('  '.$e);
        }
        if ($this->option('dry-run')) {
            $this->info('Verificare: '.count($updates).' magazine valide, '.count($errors).' probleme. Nimic salvat.');

            return $errors ? self::FAILURE : self::SUCCESS;
        }

        PageCache::batch(function () use ($updates) {
            foreach ($updates as [$store, $set]) {
                $store->fill($set)->save();
            }
        });
        $this->info(count($updates).' magazine actualizate'.($errors ? ', '.count($errors).' rânduri ignorate' : '').'.');

        return self::SUCCESS;
    }
}
