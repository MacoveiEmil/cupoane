<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Setări și texte editabile din admin (tabela settings). Cheile secrete (parole, chei API) sunt criptate cu APP_KEY.
 */
final class Settings
{
    public const SECRET = ['tp_password', 'tp_user_key', 'ps_api_key'];

    public static function defaults(): array
    {
        return [
            // Texte
            'hero_title' => 'Vouchere, reduceri și cupoane verificate',
            'hero_subtitle' => 'Coduri de reducere pentru magazinele online din România, într-un singur loc. Copiezi codul, mergi în magazin, plătești mai puțin.',
            'footer_about' => 'Vouchere, reduceri și cupoane pentru magazinele online din România. Ofertele expirate dispar automat.',
            'affiliate_note' => 'Unele linkuri de pe site sunt linkuri de afiliere: dacă faci o comandă prin ele, putem primi un comision, fără niciun cost în plus pentru tine.',
            'home_seo_text' => '',
            // Reguli
            'recheck_days' => 30,
            'vote_min' => 5,
            'trust_cf_ip' => false,
            'auto_stores' => true,
            // 2Performant
            'tp_enabled' => true,
            'tp_email' => '',
            'tp_password' => '',
            'tp_user_key' => '',
        ];
    }

    public static function all(): array
    {
        return Cache::rememberForever('settings', function () {
            $out = self::defaults();
            foreach (Setting::all() as $row) {
                $value = $row->value;
                if (in_array($row->key, self::SECRET, true) && $value) {
                    try {
                        $value = Crypt::decryptString($value);
                    } catch (\Throwable) {
                        $value = '';
                    }
                }
                $default = $out[$row->key] ?? null;
                $out[$row->key] = match (true) {
                    is_bool($default) => (bool) $value,
                    is_int($default) => (int) $value,
                    default => (string) $value,
                };
            }

            return $out;
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            if (in_array($key, self::SECRET, true) && $value !== '' && $value !== null) {
                $value = Crypt::encryptString((string) $value);
            }
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('settings');
        PageCache::flush();
    }
}
