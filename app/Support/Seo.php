<?php

namespace App\Support;

/**
 * Meta-urile unei pagini: title, description, canonical, robots, breadcrumbs, FAQ.
 * Layout-ul le scrie în <head> împreună cu schema JSON-LD (Organization, WebSite, BreadcrumbList, FAQPage).
 */
final class Seo
{
    /** Sub acest număr de oferte active, pagina de categorie / etichetă e noindex (magazinele: Store::indexable). */
    public const MIN_OFFERS_INDEX = 3;

    public function __construct(
        public string $title,
        public string $description = '',
        public ?string $canonical = null,
        public bool $noindex = false,
        public array $breadcrumbs = [],   // [[nume, url], …] fără „Acasă”
        public array $faq = [],           // [[q, a], …]
        public string $type = 'website',
    ) {
        $this->canonical ??= url()->current();
        if (self::isPreview()) {
            $this->noindex = true; // tot ce nu e producție (local, tunel de preview) nu se indexează
        }
    }

    /** Orice mediu în afară de producție (APP_ENV). Nu depinde de host, ca să nu poată fi păcălit prin antete. */
    public static function isPreview(): bool
    {
        return ! app()->isProduction();
    }

    /**
     * Răspunsurile FAQ: text simplu cu linkuri [text](/adresa) → HTML sigur (orice alt HTML e escapat).
     * Aceeași listă alimentează pagina și schema FAQPage (fără desincronizări).
     * @param  array<int, array{0:string,1:string}>  $items
     */
    public static function faqHtml(array $items): array
    {
        return array_map(fn ($r) => [$r[0], self::md($r[1])], $items);
    }

    public static function md(string $text): string
    {
        return trim((string) \Illuminate\Support\Str::inlineMarkdown($text, ['html_input' => 'escape', 'allow_unsafe_links' => false]));
    }

    public static function site(): string
    {
        return (string) config('app.name');
    }

    public function fullTitle(): string
    {
        return $this->title;
    }

    /** Graful JSON-LD complet. */
    public function schema(): array
    {
        $home = url('/');
        $graph = [
            ['@type' => 'Organization', '@id' => $home.'#org', 'name' => self::site(), 'url' => $home, 'logo' => asset('favicon.svg')],
            [
                '@type' => 'WebSite', '@id' => $home.'#web', 'url' => $home, 'name' => self::site(), 'inLanguage' => 'ro-RO',
                'publisher' => ['@id' => $home.'#org'],
            ],
        ];
        if ($this->breadcrumbs) {
            $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Acasă', 'item' => $home]];
            foreach ($this->breadcrumbs as $i => [$name, $url]) {
                $items[] = array_filter(['@type' => 'ListItem', 'position' => $i + 2, 'name' => $name, 'item' => $url]);
            }
            $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
        }
        if ($this->faq) {
            $graph[] = ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($r) => [
                '@type' => 'Question', 'name' => $r[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($r[1])],
            ], $this->faq)];
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    /** „1 ofertă”, „2 oferte”, „20 de oferte” */
    public static function plural(int $n, string $one, string $many): string
    {
        if ($n === 1) {
            return '1 '.$one;
        }

        return $n.(($n === 0 || $n % 100 < 20) ? ' ' : ' de ').$many;
    }
}
