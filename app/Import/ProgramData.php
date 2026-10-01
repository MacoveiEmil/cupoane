<?php

namespace App\Import;

final class ProgramData
{
    public function __construct(
        public readonly string $network,
        public readonly string $id,
        public readonly string $name,
        public readonly string $url,
        public readonly string $uniqueCode = '',
        public readonly string $category = '',
        public readonly string $logo = '',
        public readonly ?bool $accepted = true, // null = necunoscut
    ) {
    }
}
