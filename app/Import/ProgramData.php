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
        public readonly string $relation = 'accepted', // accepted | pending | not_applied
        public readonly ?bool $allowsCoupons = null, // programul acceptă site-uri de cupoane (null = necunoscut)
    ) {
    }
}
