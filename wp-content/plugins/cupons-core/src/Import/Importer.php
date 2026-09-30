<?php

declare(strict_types=1);

namespace Cupons\Core\Import;

use Cupons\Core\Content\Normalizer;
use Cupons\Core\Content\Sync;
use Cupons\Core\Freshness\CachePurger;
use Cupons\Core\Freshness\Lock;
use Cupons\Core\Keys;
use Cupons\Core\Query\Offers;
use Cupons\Core\Support\Dates;

/**
 * Import pe pași scurți (compatibil cu limitele de timp de pe shared hosting):
 * fiecare step() lucrează maxim ~15 s, salvează cursorul și poate fi reluat.
 * Fazele: programs (programe + asociere) → offers (oferte, doar pentru programele asociate) → done.
 * Ofertele noi intră ca CIORNĂ; cele existente (după ID extern) își actualizează doar datele.
 */
final class Importer
{
    public const BUDGET = 15;
    private const STATE = 'cupons_import_';

    /** @var array<string,NetworkConnectorInterface>|null */
    private static ?array $connectors = null;

    /** @var array<string,array<string,int>> rețea => (ext_id => post_id) */
    private static array $known = [];

    /** @return array<string,NetworkConnectorInterface> */
    public static function connectors(): array
    {
        if (self::$connectors === null) {
            $list = apply_filters('cupons_connectors', [new TwoPerformant(), new Profitshare()]);
            self::$connectors = [];
            foreach ($list as $c) {
                if ($c instanceof NetworkConnectorInterface) {
                    self::$connectors[$c->id()] = $c;
                }
            }
        }
        return self::$connectors;
    }

    public static function connector(string $id): ?NetworkConnectorInterface
    {
        return self::connectors()[$id] ?? null;
    }

    public static function state(string $network): array
    {
        $s = get_option(self::STATE . $network);
        return is_array($s) ? $s : self::fresh('idle');
    }

    private static function fresh(string $phase): array
    {
        return [
            'phase'    => $phase,
            'cursor'   => null,
            'error'    => '',
            'started'  => $phase === 'idle' ? 0 : time(),
            'finished' => 0,
            'stats'    => ['programs' => 0, 'mapped' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'unmapped' => 0, 'expired' => 0, 'skipped' => 0],
        ];
    }

    public static function start(string $network): array
    {
        $state = self::fresh('programs');
        update_option(self::STATE . $network, $state, false);
        return $state;
    }

    public static function is_running(array $state): bool
    {
        return in_array($state['phase'], ['programs', 'offers', 'logos'], true);
    }

    /** Un pas de import. Reluabil: dacă apare o eroare, cursorul rămâne și „Continuă” pornește de acolo. */
    public static function step(string $network, int $budget = self::BUDGET): array
    {
        $connector = self::connector($network);
        $state     = self::state($network);
        if (!$connector || !self::is_running($state)) {
            return $state;
        }
        if (!Lock::acquire('import_' . $network, 120)) {
            $state['busy'] = true;
            return $state;
        }

        $t0 = microtime(true);
        @set_time_limit(max(30, $budget + 15)); // phpcs:ignore -- unele gazde nu permit

        try {
            $state['error'] = '';
            while (self::is_running($state) && (microtime(true) - $t0) < $budget) {
                if ($state['phase'] === 'programs') {
                    $page = $connector->programs($state['cursor']);
                    foreach ($page->items as $program) {
                        // Programele respinse / inactive se ignoră; cele cu status necunoscut ajung la „Neasociate”.
                        if ($program instanceof Program && $program->accepted !== false) {
                            $state['stats']['programs']++;
                            if (Programs::upsert($program) > 0) {
                                $state['stats']['mapped']++;
                            }
                        }
                    }
                    $state['cursor'] = $page->next;
                    if ($page->next === null) {
                        $state['phase'] = 'offers';
                    }
                    continue;
                }

                if ($state['phase'] === 'logos') {
                    if (Programs::fetch_logos($t0 + $budget) === 0) {
                        $state['phase']    = 'done';
                        $state['finished'] = time();
                    }
                    continue;
                }

                $page = $connector->offers($state['cursor'], Programs::map($network));
                foreach ($page->items as $offer) {
                    if ($offer instanceof Offer) {
                        $state['stats'][self::save_offer($offer, $network)]++;
                    }
                }
                $state['cursor'] = $page->next;
                if ($page->next === null) {
                    $state['phase'] = 'logos';
                }
            }
        } catch (ImportException $e) {
            $state['error'] = $e->getMessage();
        } finally {
            update_option(self::STATE . $network, $state, false);
            Lock::release('import_' . $network);
        }

        return $state;
    }

    /** Import complet (WP-CLI). $tick primește starea după fiecare pas. */
    public static function run(string $network, ?callable $tick = null, bool $restart = true): array
    {
        $state = $restart || !self::is_running(self::state($network)) ? self::start($network) : self::state($network);
        while (self::is_running($state) && $state['error'] === '') {
            $state = self::step($network, 25);
            if ($tick) {
                $tick($state);
            }
            if (!empty($state['busy'])) {
                break;
            }
        }
        return $state;
    }

    /** @return string cheia statisticii: created|updated|unchanged|unmapped|expired|skipped */
    private static function save_offer(Offer $o, string $network): string
    {
        $term_id = Programs::term_for($network, $o->program_id);
        if ($term_id === 0) {
            return 'unmapped';
        }
        if ($o->end !== '' && $o->end < Dates::today()) {
            return 'expired';
        }
        $title = sanitize_text_field($o->title);
        if ($title === '') {
            return 'skipped';
        }

        $meta = [
            Keys::M_CODE       => $o->code,
            Keys::M_VALUE      => $o->value,
            Keys::M_VALUE_TYPE => $o->value_type,
            Keys::M_UPTO       => $o->upto ? '1' : '0',
            Keys::M_TERMS      => $o->terms,
            Keys::M_LINK       => $o->link,
            Keys::M_START      => $o->start,
            Keys::M_EXPIRES    => $o->end,
        ];

        $existing = self::known($network)[$o->ext_id] ?? 0;
        if ($existing) {
            $status = get_post_status($existing);
            if (!$status || $status === 'trash') {
                return 'skipped'; // ștearsă sau expirată intenționat: nu o readucem
            }
            $changed = false;
            foreach ($meta as $key => $value) {
                if ((string) get_post_meta($existing, $key, true) !== (string) $value) {
                    update_post_meta($existing, $key, $value);
                    $changed = true;
                }
            }
            if ($changed) {
                Normalizer::normalize($existing);
                if ($status === 'publish') {
                    CachePurger::instance()->add_post($existing);
                }
            }
            return $changed ? 'updated' : 'unchanged';
        }

        $id = wp_insert_post([
            'post_type'   => Keys::COUPON,
            'post_status' => 'draft',
            'post_title'  => $title,
            'meta_input'  => $meta + [
                Keys::M_EXT_ID    => $o->ext_id,
                Keys::M_EXCLUSIVE => $o->exclusive ? '1' : '0',
            ],
        ], true);
        if (is_wp_error($id)) {
            return 'skipped';
        }

        wp_set_object_terms($id, [$term_id], Keys::STORE);
        if ($o->value_type === 'transport' && ($tag = get_term_by('slug', 'transport-gratuit', Keys::TAG))) {
            wp_set_object_terms($id, [(int) $tag->term_id], Keys::TAG);
        }
        Sync::sync_categories($id);
        Normalizer::normalize($id);
        self::$known[$network][$o->ext_id] = $id;

        return 'created';
    }

    /**
     * Toate ID-urile externe deja importate pentru o rețea (inclusiv în Trash), într-un singur query.
     * @return array<string,int>
     */
    private static function known(string $network): array
    {
        if (!isset(self::$known[$network])) {
            global $wpdb;
            self::$known[$network] = [];
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT pm.meta_value, pm.post_id FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = %s AND pm.meta_value LIKE %s AND p.post_type IN (%s, %s)",
                Keys::M_EXT_ID, $wpdb->esc_like($network . ':') . '%', Keys::COUPON, Keys::DEAL
            ));
            foreach ($rows as $r) {
                self::$known[$network][(string) $r->meta_value] = (int) $r->post_id;
            }
        }
        return self::$known[$network];
    }

    /** Resetează cache-urile interne (teste / rulări multiple în același proces). */
    public static function reset_cache(): void
    {
        self::$known = [];
    }
}
