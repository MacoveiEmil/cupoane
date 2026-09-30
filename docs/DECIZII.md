# Decizii tehnice

Fiecare decizie are motivul ei. Când se schimbă ceva, se actualizează aici.

## Mediu și structură

| Decizie | Motiv |
|---|---|
| **wp-env** (nu DDEV) pentru mediul local | Are nevoie doar de Docker + Node (deja instalate); PHP și WP-CLI rulează în containere. DDEV ar fi necesitat instalare separată. |
| Slug tehnic **`cupons`** (plugin `cupons-core`, temă `cupons-theme`, comenzi `wp cupons …`, prefix `cupons_`) | Stabilit cu proprietarul. Numele site-ului și domeniul rămân placeholdere: se iau din Setări → General (`blogname`, `home_url`), deci nu sunt scrise în cod. |
| Toată logica în plugin, tema doar afișează | Tema poate fi înlocuită fără a pierde date sau funcționalitate. |
| Autoloader PSR-4 propriu, fără Composer | Zero dependențe, zip-uri mici, nimic de rulat pe shared hosting. |
| `build-zip.sh` folosește `git archive` | Zip-uri curate (doar ce e comis, fără secrete), portabil pe Windows/Git Bash fără `zip`. |

## Model de date

| Decizie | Motiv |
|---|---|
| Nume interne prefixate (`cupons_magazin`, `cupons_cupon`…); URL-urile rămân cele cerute (`/magazin/`, `/categorie/`, `/oferte/`) | Evită coliziuni cu alte pluginuri/query vars. |
| Categoriile se aleg **pe magazin** (term meta) și se **copiază automat** pe ofertele magazinului | WordPress nu poate atașa o taxonomie la alt termen. Copierea permite interogări native (`tax_query`), paginare corectă și fără 404 pe `/page/2/`. |
| Textul descriptiv al magazinului = descrierea nativă a termenului | Un câmp mai puțin; editor existent. |
| Meta normalizate `_cupons_from` / `_cupons_until` (mereu prezente; `1970-01-01` / `9999-12-31` când lipsesc) | „Activ” devine două comparații simple, fără `NOT EXISTS`/`OR` lente; sortările nu pierd oferte. Se recalculează automat la orice schimbare de start/expirare. |
| Datele stocate `Y-m-d`, ziua în fusul **Europe/Bucharest** | Expirarea e inclusivă: oferta e validă toată ziua respectivă. Comparație lexicografică corectă și în SQL. |
| „Top ofertă” = bifă manuală pe cupon, completată cu cele mai accesate oferte din ultimele 30 de zile | Stabilit cu proprietarul. Control editorial + fallback pe date reale. |
| Tabele suplimentare: `cupons_vote_guard` (rate-limit IP) și `cupons_programs` (programe din rețele / „Neasociate”) | Rate-limit fără a umple `wp_options` cu transient-uri; lista „Neasociate” are nevoie de stocare proprie. |
| Tabelele `clicks` / `votes` agregate pe zi prin `INSERT … ON DUPLICATE KEY UPDATE` | Un rând per obiect per zi; scriere atomică, tabel mic. |

## Redirect de afiliere

| Decizie | Motiv |
|---|---|
| `/go/{id}/` pentru cupoane și deal-uri, `/go/{slug-magazin}/` pentru „Mergi la magazin” | ID-urile de post și de termen se pot suprapune; slug-ul e lizibil (`/go/emag/`). Slug-urile numerice de magazin sunt interzise automat (primesc prefix `magazin-`). Pagina magazinului rămâne `/magazin/{slug}/`. |
| De ce toate ieșirile trec prin `/go/` | Click-uri numărate, linkul de afiliere se schimbă într-un singur loc, un singur URL exclus din cache/index, HTML-ul nu conține URL-uri lungi de tracking. |
| Handler pe `parse_request` (înainte de query-ul principal) | Redirect instant, fără interogări inutile. |
| Oferta expirată/nepublicată → redirect la pagina magazinului, fără click numărat | Vizitatorul nu ajunge la o ofertă moartă. |
| Roboții (UA gol, bot, crawl, Lighthouse…) nu sunt numărați | Statistici oneste. |

## Voturi

| Decizie | Motiv |
|---|---|
| **Fără nonce WP** pe endpoint-ul de vot | Paginile sunt complet cacheabile: nonce-ul din HTML ar expira în cache, iar pentru vizitatori anonimi nu protejează nimic. Protecția reală: same-origin (Origin/Referer), cupon activ, cookie funcțional `cupons_v`, un vot per cupon per IP per zi (HMAC cu salt, IP-ul nu se stochează). Nonce-uri se folosesc peste tot în admin. |
| Pragul ratei de succes: **≥ 5 voturi** (setabil) | Stabilit cu proprietarul. Sub prag nu se afișează nicio cifră. |
| Opțiune „Site în spatele Cloudflare” | Fără ea, toți vizitatorii ar avea IP-ul Cloudflare și rate-limit-ul ar bloca voturi legitime. E opțională pentru că header-ul poate fi falsificat dacă originea e accesibilă direct. |
| Rata afișată se actualizează la următorul purge | Un purge la fiecare vot ar anula cache-ul; JS-ul afișează imediat rezultatul pentru cel care votează. |

## Freshness

| Decizie | Motiv |
|---|---|
| Filtru global pe `pre_get_posts` pentru frontend | Nicio listare nu poate afișa o ofertă inactivă, chiar dacă un template „uită” condiția. |
| Job zilnic la **00:05** ora României | Imediat după ce ofertele de ieri au expirat; purge-ul regenerează paginile afectate. |
| Lock atomic prin `INSERT IGNORE` în `wp_options`, cu preluare după 30 min | Funcționează pe orice shared hosting, fără object cache; un job mort nu blochează definitiv. |
| Purge țintit (magazin, categorii + părinți, etichete, paginile 2..N, homepage, indexuri); purge total peste 150 URL-uri | Cache cald pentru restul site-ului. |
| Purge și pentru ofertele care expiră azi/mâine | Eticheta „Expiră mâine” trebuie să apară/dispară corect în paginile din cache. |
| La ștergerea expiratelor, „Actualizat la” al magazinului devine data curentă | Pagina s-a schimbat efectiv. |
| `EMPTY_TRASH_DAYS` rămâne la valoarea implicită WordPress (30) | Exact cerința; nu trebuie setat nimic (doar să nu fie suprascris în `wp-config.php`). |
| Validare la publicare: fără magazin sau cu expirare în trecut → rămâne ciornă | Nu se pot publica oferte invalide din greșeală. |
