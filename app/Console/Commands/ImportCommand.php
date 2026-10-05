<?php

namespace App\Console\Commands;

use App\Import\Importer;
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    protected $signature = 'cupons:import {--network=2performant}';

    protected $description = 'Importă programele și ofertele din rețeaua de afiliere (ofertele noi intră ca ciornă)';

    public function handle(): int
    {
        $network = (string) $this->option('network');
        $connector = Importer::connectors()[$network] ?? null;
        if (! $connector) {
            $this->error('Rețea necunoscută: '.$network);

            return self::FAILURE;
        }
        if (! Importer::enabled($network)) {
            $this->warn($connector->label().': dezactivat în Setări.');

            return self::SUCCESS;
        }

        $this->line($connector->label().': pornesc importul…');
        $s = Importer::run($network, fn ($s) => $this->line('  faza: '.$s['phase']));
        $st = $s['stats'];
        $this->line(sprintf('Magazine-placeholder noi: %d (ascunse până au oferte).', $s['stats']['placeholders'] ?? 0));
        $this->line(sprintf('Programe: %d (%d magazine noi). Oferte: %d noi, %d actualizate, %d neschimbate, %d neasociate, %d expirate, %d ignorate.',
            $st['programs'], $st['stores_created'], $st['created'], $st['updated'], $st['unchanged'], $st['unmapped'], $st['expired'], $st['skipped']));

        if ($s['error'] !== '') {
            $this->error($s['error']);

            return self::FAILURE;
        }
        $this->info('Gata.');

        return self::SUCCESS;
    }
}
