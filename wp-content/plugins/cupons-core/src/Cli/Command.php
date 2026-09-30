<?php

declare(strict_types=1);

namespace Cupons\Core\Cli;

use Cupons\Core\Freshness\Cleanup;

/**
 * Comenzi WP-CLI pentru site-ul de cupoane.
 */
final class Command
{
    public static function register(): void
    {
        \WP_CLI::add_command('cupons', self::class);
    }

    /**
     * Rulează curățarea zilnică: expiratele în Trash, ofertele neverificate în ciornă, purge de cache.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Doar numără ce s-ar modifica, fără a schimba nimic.
     *
     * ## EXAMPLES
     *
     *     wp cupons cleanup
     *     wp cupons cleanup --dry-run
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public function cleanup(array $args, array $assoc): void
    {
        $dry    = (bool) \WP_CLI\Utils\get_flag_value($assoc, 'dry-run', false);
        $report = Cleanup::run($dry);

        if (!empty($report['locked'])) {
            \WP_CLI::error('Curățarea rulează deja (lock activ). Încearcă din nou peste câteva minute.');
        }

        $prefix = $dry ? '[dry-run] ' : '';
        \WP_CLI::log($prefix . sprintf('Expirate %s: %d', $dry ? 'de mutat în coș' : 'mutate în coș', $report['expired']));
        \WP_CLI::log($prefix . sprintf('Fără expirare, neverificate %s: %d', $dry ? 'de trecut în ciornă' : 'trecute în ciornă', $report['stale']));
        \WP_CLI::log($prefix . sprintf('Oferte care expiră azi/mâine (pagini regenerate): %d', $report['labels']));
        \WP_CLI::log($prefix . sprintf('Intrări vechi de rate-limit șterse: %d', $report['guard']));
        \WP_CLI::success($dry ? 'Simulare încheiată.' : 'Curățare încheiată.');
    }

    /**
     * Importă programele și ofertele din rețelele de afiliere (ofertele noi intră ca ciornă).
     *
     * ## OPTIONS
     *
     * [--network=<id>]
     * : Doar o rețea: 2performant sau profitshare. Implicit: toate rețelele configurate.
     *
     * [--resume]
     * : Continuă importul întrerupt, în loc să înceapă de la zero.
     *
     * ## EXAMPLES
     *
     *     wp cupons import
     *     wp cupons import --network=profitshare
     *
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public function import(array $args, array $assoc): void
    {
        $only   = (string) \WP_CLI\Utils\get_flag_value($assoc, 'network', '');
        $resume = (bool) \WP_CLI\Utils\get_flag_value($assoc, 'resume', false);
        $list   = \Cupons\Core\Import\Importer::connectors();

        if ($only !== '' && !isset($list[$only])) {
            \WP_CLI::error('Rețea necunoscută. Disponibile: ' . implode(', ', array_keys($list)));
        }

        $failed = false;
        foreach ($list as $id => $connector) {
            if ($only !== '' && $id !== $only) {
                continue;
            }
            if (!$connector->is_configured()) {
                \WP_CLI::warning($connector->label() . ': neconfigurat (Cupoane → Setări). Sar peste.');
                continue;
            }
            \WP_CLI::log($connector->label() . ': pornesc importul…');
            $state = \Cupons\Core\Import\Importer::run($id, static function (array $s) {
                \WP_CLI::log('  ' . \Cupons\Core\Admin\ImportPage::summary($s));
            }, !$resume);

            if ($state['error'] !== '') {
                \WP_CLI::warning($connector->label() . ': ' . $state['error'] . ' (reia cu --resume)');
                $failed = true;
            } elseif (!empty($state['busy'])) {
                \WP_CLI::warning($connector->label() . ': un alt import rulează deja.');
            } else {
                \WP_CLI::success($connector->label() . ': gata.');
            }
        }
        if ($failed) {
            \WP_CLI::halt(1);
        }
    }
}
