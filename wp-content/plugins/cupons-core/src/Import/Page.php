<?php

declare(strict_types=1);

namespace Cupons\Core\Import;

/** O pagină de rezultate de la o rețea + cursorul pentru pagina următoare (null = gata). */
final class Page
{
    /**
     * @param array<int,Program|Offer> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $next,
    ) {
    }
}
