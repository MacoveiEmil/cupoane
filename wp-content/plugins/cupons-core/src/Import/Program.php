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
    ) {
    }
}
