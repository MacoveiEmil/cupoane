<?php

declare(strict_types=1);

namespace Cupons\Core\Front;

use Cupons\Core\Content\Pages;
use Cupons\Core\Keys;

/**
 * Schema.org: Organization, WebSite (SearchAction), BreadcrumbList, FAQPage pe magazine.
 * Cu Rank Math: completăm graful lui (fără duplicate). Fără Rank Math: scriem graful complet.
 */
final class Schema
{
    public static function register_hooks(): void
    {
        add_action('wp', static function (): void {
            if (is_admin()) {
                return;
            }
            if (Seo::rank_math()) {
                add_filter('rank_math/json_ld', [self::class, 'rank_math'], 99, 2);
            } else {
                add_action('wp_head', [self::class, 'print'], 20);
            }
        });
    }

    public static function rank_math(array $data, mixed $jsonld = null): array
    {
        $search = self::search_action();
        $types  = [];
        foreach ($data as $key => &$node) {
            if (!is_array($node)) {
                continue;
            }
            $type = (array) ($node['@type'] ?? []);
            $types = array_merge($types, $type);
            if (in_array('WebSite', $type, true) && $search) {
                $node['potentialAction'] = $search;
            }
        }
        unset($node);

        if (!in_array('Organization', $types, true) && !in_array('Person', $types, true)) {
            $data['cuponsOrganization'] = self::organization();
        }
        if (!in_array('BreadcrumbList', $types, true) && ($bc = self::breadcrumbs())) {
            $data['cuponsBreadcrumbs'] = $bc;
        }
        if (!in_array('FAQPage', $types, true) && ($faq = self::faq())) {
            $data['cuponsFaq'] = $faq;
        }
        return $data;
    }

    public static function print(): void
    {
        $graph = [self::organization(), self::website()];
        if ($bc = self::breadcrumbs()) {
            $graph[] = $bc;
        }
        if ($faq = self::faq()) {
            $graph[] = $faq;
        }
        $json = wp_json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore -- JSON codificat
    }

    private static function organization(): array
    {
        $org = [
            '@type' => 'Organization',
            '@id'   => home_url('/#organization'),
            'name'  => get_bloginfo('name'),
            'url'   => home_url('/'),
        ];
        $logo = get_theme_mod('custom_logo') ?: get_option('site_icon');
        if ($logo && ($src = wp_get_attachment_image_url((int) $logo, 'full'))) {
            $org['logo'] = $src;
        }
        return $org;
    }

    private static function website(): array
    {
        $site = [
            '@type'     => 'WebSite',
            '@id'       => home_url('/#website'),
            'url'       => home_url('/'),
            'name'      => get_bloginfo('name'),
            'inLanguage'=> 'ro-RO',
            'publisher' => ['@id' => home_url('/#organization')],
        ];
        if ($search = self::search_action()) {
            $site['potentialAction'] = $search;
        }
        return $site;
    }

    private static function search_action(): ?array
    {
        $url = Pages::url('cauta');
        if ($url === '') {
            return null;
        }
        return [
            '@type'       => 'SearchAction',
            'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => add_query_arg('q', '{search_term_string}', $url)],
            'query-input' => 'required name=search_term_string',
        ];
    }

    private static function breadcrumbs(): ?array
    {
        $items = Breadcrumbs::items();
        if (count($items) < 2) {
            return null;
        }
        $list = [];
        foreach ($items as $i => $item) {
            $el = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $item['name']];
            if ($item['url'] !== '') {
                $el['item'] = $item['url'];
            }
            $list[] = $el;
        }
        return ['@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    private static function faq(): ?array
    {
        if (!is_tax(Keys::STORE)) {
            return null;
        }
        $faq = array_filter((array) get_term_meta(get_queried_object_id(), Keys::T_FAQ, true), 'is_array');
        if (!$faq) {
            return null;
        }
        return [
            '@type'      => 'FAQPage',
            'mainEntity' => array_values(array_map(static fn($r) => [
                '@type'          => 'Question',
                'name'           => (string) $r['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => wp_strip_all_tags((string) $r['a'])],
            ], $faq)),
        ];
    }
}
