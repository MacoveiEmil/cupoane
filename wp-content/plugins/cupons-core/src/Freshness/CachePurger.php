<?php

declare(strict_types=1);

namespace Cupons\Core\Freshness;

use Cupons\Core\Keys;
use Cupons\Core\Query\Offers;

/**
 * Colectează URL-urile afectate în timpul unei cereri și face un singur purge la final.
 * Integrare: LiteSpeed Cache (acțiunile lui publice). Alte cache-uri pot asculta „cupons_purge_urls”.
 */
final class CachePurger
{
    /** Peste acest număr de URL-uri, un purge complet e mai ieftin. */
    private const PURGE_ALL_THRESHOLD = 150;

    private static ?self $instance = null;

    /** @var array<string,true> */
    private array $urls = [];
    private bool $all   = false;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function register_hooks(): void
    {
        add_action('shutdown', [self::instance(), 'flush'], 0);
    }

    public function add_url(string $url): void
    {
        if ($url !== '') {
            $this->urls[$url] = true;
        }
    }

    public function add_all(): void
    {
        $this->all = true;
    }

    /** Paginile unde apare o ofertă: magazin, categorii (+ părinți), etichete, homepage, indexuri. */
    public function add_post(int $post_id): void
    {
        foreach ([Keys::STORE, Keys::CATEGORY, Keys::TAG] as $tax) {
            $terms = get_the_terms($post_id, $tax);
            if (is_array($terms)) {
                foreach ($terms as $term) {
                    $this->add_term($term);
                }
            }
        }
        if (get_post_type($post_id) === Keys::DEAL) {
            $this->add_url(home_url('/deal-uri/'));
        }
        $this->add_common();
    }

    public function add_term(\WP_Term $term): void
    {
        $link = get_term_link($term);
        if (is_string($link)) {
            $this->add_url($link);
            // Paginile 2..N ale listării.
            $pages = (int) ceil(Offers::count_for_term($term->term_id, $term->taxonomy) / Offers::PER_PAGE);
            for ($i = 2; $i <= min($pages + 1, 20); $i++) {
                $this->add_url(trailingslashit($link) . 'page/' . $i . '/');
            }
        }
        if ($term->parent) {
            $parent = get_term($term->parent, $term->taxonomy);
            if ($parent instanceof \WP_Term) {
                $this->add_term($parent);
            }
        }
        $this->add_common();
    }

    private function add_common(): void
    {
        $this->add_url(home_url('/'));
        $this->add_url(home_url('/magazine/'));
        $this->add_url(home_url('/categorii/'));
    }

    public function has_pending(): bool
    {
        return $this->all || $this->urls !== [];
    }

    public function flush(): void
    {
        if (!$this->has_pending()) {
            return;
        }

        Offers::flush_counts();
        $urls = array_keys($this->urls);

        if ($this->all || count($urls) > self::PURGE_ALL_THRESHOLD) {
            do_action('litespeed_purge_all', 'cupons: actualizare oferte');
        } else {
            foreach ($urls as $url) {
                do_action('litespeed_purge_url', $url);
            }
        }

        /** Pentru alte integrări (ex.: Cloudflare, dacă se cache-uiește HTML acolo). */
        do_action('cupons_purge_urls', $urls, $this->all);

        $this->urls = [];
        $this->all  = false;
    }
}
