<?php

namespace App\Filament\Pages;

use App\Import\Importer;
use App\Models\Program;
use App\Models\Store;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * „Importă acum”: rulează importul pe pași de ~15 s (compatibil cu shared hosting). Pagina se reîmprospătează
 * singură cât timp rulează. Dedesubt: programele neasociate, cu asociere la un magazin sau creare de magazin nou.
 */
class ImportOffers extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Import din rețele';

    protected static ?string $title = 'Import din rețele';

    protected static ?string $slug = 'import';

    protected string $view = 'filament.pages.import-offers';

    /** @var array<int, string> program id => store id ales */
    public array $assoc = [];

    public function start(string $network): void
    {
        if (! Importer::enabled($network)) {
            Notification::make()->title('Rețeaua e dezactivată în Setări')->warning()->send();

            return;
        }
        Importer::start($network);
        $this->tick();
    }

    /** Apelat de wire:poll cât timp importul rulează. */
    public function tick(): void
    {
        foreach (array_keys(Importer::connectors()) as $network) {
            if (Importer::running(Importer::state($network))) {
                $s = Importer::step($network);
                if ($s['phase'] === 'done') {
                    Notification::make()->title('Import terminat')->body($this->summary($s))->success()->send();
                }
            }
        }
    }

    public function isRunning(): bool
    {
        foreach (array_keys(Importer::connectors()) as $network) {
            $s = Importer::state($network);
            if (Importer::running($s) && $s['error'] === '') {
                return true;
            }
        }

        return false;
    }

    public function associate(int $programId): void
    {
        $program = Program::findOrFail($programId);
        $choice = $this->assoc[$programId] ?? '';
        $store = $choice === 'new' || $choice === ''
            ? Store::create(['name' => $program->name, 'url' => $program->site_url ?: null])
            : Store::findOrFail((int) $choice);
        $store->update(['network' => $program->network, 'program_id' => $program->program_id]);
        $program->update(['store_id' => $store->id]);
        Notification::make()->title('Asociat cu „'.$store->name.'”. Rulează importul din nou pentru oferte.')->success()->send();
    }

    public function summary(array $s): string
    {
        $st = $s['stats'];

        return sprintf(
            'Programe acceptate: %d (%d magazine noi). Magazine-placeholder noi: %d (ascunse până au oferte). Oferte: %d noi (ciorne), %d actualizate, %d neschimbate, %d la magazine neasociate, %d expirate, %d ignorate.',
            $st['programs'], $st['stores_created'], $st['placeholders'] ?? 0, $st['created'], $st['updated'], $st['unchanged'], $st['unmapped'], $st['expired'], $st['skipped']
        );
    }

    protected function getViewData(): array
    {
        $networks = [];
        foreach (Importer::connectors() as $id => $c) {
            $networks[$id] = ['label' => $c->label(), 'configured' => $c->isConfigured(), 'enabled' => Importer::enabled($id), 'state' => Importer::state($id)];
        }

        return [
            'networks' => $networks,
            'unmapped' => Program::whereNull('store_id')->where(fn ($query) => $query->whereNull('accepted')->orWhere('accepted', true))->orderBy('name')->get(),
            'stores' => Store::orderBy('name')->pluck('name', 'id'),
        ];
    }
}
