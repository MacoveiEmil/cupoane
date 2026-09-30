<?php

declare(strict_types=1);

namespace Cupons\Core\Import;

/** Decodarea răspunsurilor HTTP, cu mesaje de eroare clare în română. */
final class Http
{
    public const TIMEOUT = 20;

    /**
     * @param array|\WP_Error $response
     * @return array<mixed>
     */
    public static function json(array|\WP_Error $response, string $network): array
    {
        if (is_wp_error($response)) {
            throw new ImportException(sprintf('%s: conexiune eșuată (%s).', $network, $response->get_error_message()));
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($code === 429) {
            throw new ImportException(sprintf('%s: limita de requesturi a fost atinsă. Reîncearcă peste câteva minute.', $network));
        }
        if ($code === 401 || $code === 403) {
            throw new ImportException(sprintf('%s: acces refuzat (HTTP %d). Verifică datele de acces din Setări.', $network, $code));
        }
        if ($code < 200 || $code >= 300) {
            throw new ImportException(sprintf('%s: răspuns neașteptat (HTTP %d): %s', $network, $code, self::snippet($body)));
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new ImportException(sprintf('%s: răspunsul nu e JSON valid: %s', $network, self::snippet($body)));
        }
        return $data;
    }

    private static function snippet(string $body): string
    {
        return mb_substr(trim(wp_strip_all_tags($body)), 0, 200);
    }
}
