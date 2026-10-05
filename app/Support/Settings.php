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
            'hero_subtitle' => 'Coduri de reducere, vouchere și cupoane pentru magazinele online din România, verificate și actualizate zilnic: haine, încălțăminte, cărți, electronice.',
            'footer_about' => 'Vouchere, reduceri și cupoane pentru magazinele online din România. Ofertele expirate dispar automat.',
            'affiliate_note' => 'Unele linkuri de pe site sunt linkuri de afiliere: dacă faci o comandă prin ele, putem primi un comision, fără niciun cost în plus pentru tine.',
            'home_seo_text' => '<h2>Coduri de reducere verificate zilnic, pentru cumpărături mai ieftine</h2>'
                .'<h3>Cele mai bune coduri de reducere pentru magazinele tale preferate</h3>'
                .'<p>Strângem într-un singur loc vouchere, reduceri și cupoane de la magazinele online din România. Ofertele expirate dispar automat, așa că pe site găsești doar coduri valabile.</p>'
                .'<h3>Cum folosești un cod de reducere</h3>'
                .'<p>Apasă „Arată codul”, copiază-l și introdu-l în coșul magazinului, înainte de plată. Pentru ofertele fără cod, reducerea se aplică direct pe site-ul magazinului.</p>',
            // H1-uri cu variabile ({magazin}, {data}, {categorie}).
            'store_h1' => 'Coduri reducere {magazin} actualizate pe {data}',
            'category_h1' => 'Coduri reducere {categorie}',
            // FAQ-ul homepage-ului (singurul FAQ general de pe site): primul rând = titlul (H2),
            // apoi „Întrebare | Răspuns”, câte unul pe rând. Linkuri: [text](/adresa).
            'home_faq' => "Întrebări frecvente
"
                ."Cum verificați codurile de reducere? | Ofertele vin direct de la magazine, prin rețeaua de afiliere 2Performant, sau le adăugăm noi. Înainte să apară pe site, fiecare ofertă e citită de echipa noastră: valoare, condiții, dată de expirare. Pe card vezi „Verificat pe” cu data, când am testat-o, sau „Adăugat pe”, când a fost doar preluată. Ofertele fără dată de expirare le reverificăm periodic, iar cele expirate dispar automat. Toți pașii sunt descriși în [Cum verificăm codurile](/cum-verificam-codurile).
"
                ."De ce nu funcționează un cod? | Cele mai multe coduri au condiții: o valoare minimă a comenzii, doar anumite produse sau branduri, doar prima comandă sau doar în aplicația magazinului. Unele nu se cumulează cu produsele deja reduse. Deschide „Condiții” de pe card înainte să plasezi comanda și verifică data de expirare. Dacă tot nu merge, apasă „Nu” la „A funcționat?”, ca să-i avertizezi pe ceilalți și să-l reverificăm noi.
"
                ."Cât de des actualizați ofertele? | Zilnic. În fiecare dimineață preluăm ofertele noi de la magazinele partenere, iar la miezul nopții scoatem automat tot ce a expirat, așa că pe site nu rămân coduri moarte. Ofertele fără dată de expirare le reverificăm cel puțin o dată pe lună, iar data din titlul fiecărei pagini de magazin îți arată când a fost actualizată ultima dată.
"
                ."Cum câștigați bani? | Din comisioane de afiliere. Când intri pe un magazin printr-un link de pe site și plasezi o comandă, magazinul ne poate plăti un comision prin rețeaua de afiliere. Tu nu plătești nimic în plus: prețul și reducerea sunt aceleași. Comisionul nu decide ce oferte afișăm: publicăm ofertele active pe care le găsim și le scoatem pe cele expirate, indiferent cât câștigăm din ele.
"
                ."Codurile sunt gratuite? | Da, complet. Nu ai nevoie de cont, nu îți cerem adresa de email și nu există abonamente. Apeși „Arată codul”, codul se copiază automat, iar site-ul magazinului se deschide într-o filă nouă. Îl lipești în coș, înainte de plată. La ofertele fără cod, reducerea se aplică direct pe site-ul magazinului, după ce apeși „Vezi oferta”.
"
                ."Pot trimite un cod de reducere? | Da. Scrie-ne prin pagina de [Contact](/contact) și spune-ne magazinul, codul, valoarea reducerii, condițiile și până când e valabil. Îl verificăm înainte să-l publicăm, iar dacă funcționează îl adăugăm pe pagina magazinului cu data verificării. Nu publicăm coduri personale, generate pentru un singur cont, sau coduri obținute încălcând regulile magazinului.
"
                .'Cum aflu când apare un cod nou pentru un magazin? | Nu trimitem newslettere și nu avem notificări. Pagina fiecărui magazin e actualizată zilnic, iar data din titlu îți arată ultima actualizare. Cel mai simplu e să salvezi pagina magazinului preferat în favoritele browserului și să revii înainte de fiecare comandă. Codurile noi apar primele și în secțiunea „Cele mai noi vouchere” de pe prima pagină.',
            // Reguli
            'recheck_days' => 30,
            'vote_min' => 5,
            'auto_stores' => true,
            'placeholder_stores' => true,
            // 2Performant
            'tp_enabled' => true,
            'tp_email' => '',
            'tp_password' => '',
            'tp_user_key' => '',
        ];
    }

    public static function all(): array
    {
        // În cache stau doar valorile salvate din admin; valorile implicite din cod se adaugă la fiecare citire,
        // ca o setare nouă (după un deploy) să apară imediat, fără golirea manuală a cache-ului.
        $saved = Cache::rememberForever('settings', function () {
            $out = [];
            $defaults = self::defaults();
            foreach (Setting::all() as $row) {
                $value = $row->value;
                if (in_array($row->key, self::SECRET, true) && $value) {
                    try {
                        $value = Crypt::decryptString($value);
                    } catch (\Throwable) {
                        $value = '';
                    }
                }
                $default = $defaults[$row->key] ?? null;
                $out[$row->key] = match (true) {
                    is_bool($default) => (bool) $value,
                    is_int($default) => (int) $value,
                    default => (string) $value,
                };
            }

            return $out;
        });

        return $saved + self::defaults();
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
