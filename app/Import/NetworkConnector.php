<?php

namespace App\Import;

/**
 * Contractul comun al conectorilor de rețea. Fiecare conector folosește DOAR endpoint-urile
 * din documentația oficială a rețelei (vezi docs/DECIZII.md).
 */
interface NetworkConnector
{
    public function id(): string;

    public function label(): string;

    public function isConfigured(): bool;

    /** @return array{0: ProgramData[], 1: ?string} [programe, cursor pagina următoare] */
    public function programs(?string $cursor): array;

    /** @return array{0: OfferData[], 1: ?string} [oferte, cursor pagina următoare] */
    public function offers(?string $cursor): array;

    /** Link de afiliere pentru un URL anume din magazin. */
    public function deeplink(string $url, string $programId): string;
}
