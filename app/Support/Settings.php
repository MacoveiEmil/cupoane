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
            'home_seo_text' => '<h2>Coduri de reducere verificate zilnic, pentru cumpărături mai ieftine</h2>'
                .'<h3>Cele mai bune coduri de reducere pentru magazinele tale preferate</h3>'
                .'<p>Strângem într-un singur loc vouchere, reduceri și cupoane de la magazinele online din România. Ofertele expirate dispar automat, așa că pe site găsești doar coduri valabile.</p>'
                .'<h3>Cum folosești un cod de reducere</h3>'
                .'<p>Apasă „Arată codul”, copiază-l și introdu-l în coșul magazinului, înainte de plată. Pentru ofertele fără cod, reducerea se aplică direct pe site-ul magazinului.</p>',
            // Texte pagini: blocuri cu titlu (H2) pe primul rând, apoi „Subtitlu (H3) | text”, câte unul pe rând.
            // Variabile: {magazin}, {categorie}, {data}. Un bloc gol nu se afișează.
            'store_h1' => 'Coduri reducere {magazin} actualizate pe {data}',
            'store_howto' => "Cum folosești codurile de reducere {magazin}?\n"
                ."Alege un cod de reducere {magazin} din lista de mai sus | Uită-te la valoare, la condiții și la data de expirare, apoi apasă „Arată codul” sau „Vezi oferta”.\n"
                ."Copiază codul promoțional {magazin} și accesează site-ul | Codul se copiază automat, iar site-ul {magazin} se deschide într-o filă nouă.\n"
                .'Introdu codul de reducere {magazin} la checkout | Lipește codul în câmpul pentru voucher din coș, înainte de plată, și verifică dacă reducerea a fost aplicată.',
            'store_method' => "Cum ajunge un cod {magazin} pe pagină?\n"
                ."De unde vin codurile | Codurile vin direct de la {magazin}, prin rețeaua de afiliere 2Performant, sau sunt adăugate manual de echipa noastră.\n"
                ."Cum le verificăm | Ofertele verificate de noi au pe card „Verificat pe” și data. Cele preluate automat apar cu „Adăugat pe”, până le verificăm. Ofertele fără dată de expirare sunt reverificate periodic.\n"
                ."Cum calculăm rata de succes | După fiecare cod poți vota „A funcționat?”. Rata de succes apare când există suficiente voturi.\n"
                .'Când le retragem | Ofertele expirate dispar automat de pe site, din aceeași zi.',
            'store_tips' => "Cum economisești la {magazin}\n"
                ."Folosește codul în coș înainte de plată | Majoritatea codurilor {magazin} se aplică în coș, înainte de finalizarea comenzii.\n"
                ."Verifică pragul minim și produsele incluse | Unele coduri au o valoare minimă a comenzii sau se aplică doar la anumite produse. Detaliile sunt la „Condiții”.\n"
                .'Combină codul cu reducerile din site | Pe lângă codurile de reducere, urmărește și ofertele fără cod de pe pagina {magazin}.',
            'store_faq' => "Întrebări frecvente despre codurile {magazin}\n"
                ."Cum folosesc un cod de reducere {magazin}? | Apasă „Arată codul”, copiază-l și introdu-l în coș, pe site-ul {magazin}, înainte de plată.\n"
                ."De ce nu funcționează codul {magazin}? | Verifică data de expirare, valoarea minimă a comenzii și produsele incluse, la „Condiții”. Dacă tot nu merge, votează „Nu” ca să-i anunți și pe ceilalți.\n"
                .'Cât de des sunt actualizate codurile {magazin}? | Ofertele sunt actualizate zilnic, iar cele expirate dispar automat.',
            'category_h1' => 'Coduri reducere {categorie}',
            'category_faq' => "Întrebări frecvente\n"
                ."Unde găsesc coduri de reducere pentru {categorie}? | Pe această pagină sunt toate ofertele active de la magazinele din categoria {categorie}, actualizate zilnic.\n"
                .'Sunt valabile codurile de reducere {categorie}? | Da. Ofertele expirate dispar automat, iar fiecare card arată data ultimei verificări.',
            'listing_faq' => "Întrebări frecvente\n"
                ."Sunt gratuite codurile de reducere? | Da. Toate codurile și ofertele de pe site sunt gratuite.\n"
                ."Cât de des sunt actualizate ofertele? | Zilnic. Ofertele expirate dispar automat, din aceeași zi.\n"
                .'De ce unele oferte nu au cod? | Unele reduceri se aplică direct pe site-ul magazinului, fără cod. Apeși „Vezi oferta” și reducerea apare acolo.',
            // Reguli
            'recheck_days' => 30,
            'vote_min' => 5,
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

    /** Text scurt cu variabile înlocuite ({magazin}, {categorie}, {data}). */
    public static function text(string $key, array $vars = []): string
    {
        return strtr((string) self::get($key, ''), self::vars($vars));
    }

    /**
     * Bloc de text editabil: primul rând = titlul (H2), restul „Subtitlu | text” (fără „|” = paragraf simplu).
     * @return array{title:string, items:array<int, array{0:?string, 1:string}>}|null
     */
    public static function block(string $key, array $vars = []): ?array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', self::text($key, $vars)))));
        if (count($lines) < 2) {
            return null;
        }
        $items = array_map(function ($l) {
            $parts = array_map('trim', explode('|', $l, 2));

            return count($parts) === 2 ? [$parts[0], $parts[1]] : [null, $parts[0]];
        }, array_slice($lines, 1));

        return ['title' => $lines[0], 'items' => $items];
    }

    private static function vars(array $vars): array
    {
        $out = [];
        foreach ($vars as $k => $v) {
            $out['{'.$k.'}'] = (string) $v;
        }

        return $out;
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
