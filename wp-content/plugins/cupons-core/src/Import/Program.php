<?php

declare(strict_types=1);

namespace Cupons\Core\Import;

/** Un program (magazin) dintr-o rețea de afiliere. */
final class Program
{
    public function __construct(
        public readonly string $network,
        public readonly string $id,
        public readonly string $name,
        public readonly string $url,
        public readonly string $unique_code = '',
        public readonly string $category = '',
        public readonly string $logo = '',   // URL absolut al logo-ului din rețea
        public readonly ?bool $accepted = true, // true = acceptat și activ; false = nu; null = necunoscut
    ) {
    }
}
