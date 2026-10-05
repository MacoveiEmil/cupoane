<?php

namespace App\Import;

use Illuminate\Support\Str;

/** Categoriile 2Performant (în engleză) → nume și slug în română, pentru site. */
final class Categories
{
    public const RO = [
        'Home & Garden' => 'Casă și grădină',
        'Fashion' => 'Modă',
        'Beauty' => 'Frumusețe',
        'Electronics' => 'Electronice',
        'Health' => 'Sănătate',
        'Babies Kids & Toys' => 'Copii și jucării',
        'Online Mall' => 'Magazine generale',
        'Books' => 'Cărți',
        'Automotive' => 'Auto',
        'Food' => 'Alimente și băuturi',
        'Sports & outdoors' => 'Sport și timp liber',
        'Pet supplies' => 'Animale de companie',
        'Pharma' => 'Farmacie',
        'Jewelry' => 'Bijuterii',
        'Gifts & Flowers' => 'Cadouri și flori',
        'Office supplies' => 'Birou și papetărie',
        'Software' => 'Software',
        'Games' => 'Jocuri',
        'Insurance' => 'Asigurări',
        'Watches' => 'Ceasuri',
        'Telecom' => 'Telecom',
        'Others' => 'Altele',
    ];

    /** Categorii pe care nu le afișăm pe site (siguranța brandului). */
    public const SKIP = ['Adult'];

    /** @return array{0:string,1:string} [nume, slug] */
    public static function ro(string $en): array
    {
        $name = self::RO[trim($en)] ?? trim($en);

        return [$name, Str::slug($name)];
    }
}
