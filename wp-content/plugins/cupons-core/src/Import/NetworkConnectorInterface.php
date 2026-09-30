<?php

declare(strict_types=1);

namespace Cupons\Core\Import;

/**
 * Contractul comun al conectorilor de rețea. Fiecare conector folosește DOAR
 * endpoint-urile din documentația oficială a rețelei (vezi docs/DECIZII.md).
 */
interface NetworkConnectorInterface
{
    /** Identificator stabil: „2performant”, „profitshare” (același cu Keys::NETWORKS). */
    public function id(): string;

    public function label(): string;

    /** Are toate credențialele necesare în Setări. */
    public function is_configured(): bool;

    /**
     * Câmpurile de configurare afișate în Setări.
     * @return array<string,array{label:string,secret:bool,help:string}> cheie setare => definiție
     */
    public function settings_fields(): array;

    /**
     * O pagină de programe (magazine) din rețea.
     * @param string|null $cursor null = prima pagină
     */
    public function programs(?string $cursor): Page;

    /**
     * O pagină de oferte (cupoane / promoții).
     * @param array<string,int> $mapped program_id din rețea => term_id magazin (doar programele asociate)
     */
    public function offers(?string $cursor, array $mapped): Page;

    /**
     * Link de afiliere pentru un URL anume din magazin (deeplink), după formatul rețelei.
     * @throws ImportException
     */
    public function deeplink(string $url, string $program_id): string;
}
