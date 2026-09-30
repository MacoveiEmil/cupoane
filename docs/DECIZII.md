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

## Temă și frontend (Faza 2)

| Decizie | Motiv |
|---|---|
| Paginile structurale (`/magazine/`, `/categorii/`, `/deal-uri/`, `/top-oferte/`, `/cauta/`) și paginile statice sunt **pagini WordPress reale**, create automat de plugin | Rank Math le tratează nativ (titlu editabil, sitemap, canonical); paginarea `/deal-uri/page/2/` funcționează fără reguli proprii. Tema le afișează prin `page-{slug}.php`. Nu schimba slug-urile lor. |
| Homepage = pagina „Acasă” setată ca prima pagină; **conținutul ei = textul SEO** de sub secțiuni | Textul SEO se editează direct din admin, cu editorul obișnuit. |
| Căutarea: `/cauta/?q=` (noindex); `?s=` nativ face 301 către ea | Căutăm magazine și categorii, nu articole. |
| Categoriile ofertelor includ și categoriile-părinte ale magazinului | Numărul de oferte pe o categorie-părinte e exact, fără dubluri. |
| **Meniul mobil: panou dropdown peste conținut** (`<details>`, închis la click în afară / Esc) | Cerut explicit de proprietar după primul design; înlocuiește regula inițială „fără overlay”. Fără JS rămâne un `<details>` funcțional. |
| Iconițe: un singur sprite SVG inline + `<use>` | HTML-ul homepage-ului a scăzut cu ~20 KB față de SVG-uri repetate în fiecare card. |
| Culoarea de accent `#c2410c` (nu un portocaliu deschis) | Contrast WCAG AA cu text alb (Accessibility 100). |
| CSS scris compact (o regulă pe linie) | Un singur fișier sub 25 KB fără pas de build (~6 KB comprimat). |
| Cu Rank Math activ: template-urile noastre de title/description sunt implicite, un titlu setat manual în Rank Math are prioritate; schema lui e completată (SearchAction, BreadcrumbList, FAQPage, Organization), fără duplicate | Cerința „suprascriibile per pagină” + fără schema dublă. Fără Rank Math (sau înainte de wizard) pluginul scrie singur meta, canonical și schema. |
| În Rank Math, tipul entității trebuie setat pe **Organization** (wizard → „Organization”) | Implicit Rank Math folosește „Person”; atunci nu mai adăugăm noi Organization, ca să nu existe două entități. |
| Magazinele/categoriile/etichetele cu < 3 oferte active: noindex + scoase din sitemap-ul Rank Math | Pagini subțiri. |
| Măsurătorile Lighthouse locale se fac printr-un proxy care cache-uiește și comprimă HTML-ul | Docker pe Windows răspunde în 6–9 s fără cache; în producție LiteSpeed Cache servește HTML-ul din cache, comprimat. |

## Import din rețele (Faza 3)

Surse: exclusiv documentația oficială — [2Performant](https://doc.2performant.com/) și [Profitshare](https://doc.profitshare.com/affiliates/docs/). Niciun endpoint inventat.

| Decizie | Motiv |
|---|---|
| **2Performant**: `POST /users/sign_in` (antet `user-key` + email/parolă) → `access-token`/`client`/`uid` din antetele răspunsului + `user.unique_code` (= `aff_code`); `GET /affiliate/programs?filter[relation]=accepted`; `GET /affiliate/advertiser_promotions?filter[affrequest_status]=accepted` | Doar programele și promoțiile la care contul e acceptat. Documentația cere antetele `access-token`, `client`, `uid` la cereri, dar nu descrie explicit răspunsul de sign-in: le citim din antetele lui (convenția API-ului). **De confirmat la primul import real.** |
| Linkul ofertelor 2Performant = quicklink `event.2performant.com/events/click?ad_type=quicklink&aff_code=…&unique=…&redirect_to=…` spre `landing_page_link` | Formatul apare în documentația oficială; `unique` = `unique_code` al programului, salvat la importul programelor (coloana nouă `unique_code`, schema v2). |
| **Profitshare**: semnătura `X-PS-Auth` = HMAC-SHA1(API key, `METODĂ + rută + "?" + query + "/" + API user + Date`) | Exact ca exemplul PHP din documentație. Ora serverului trebuie sincronizată (toleranță 20 s). |
| Profitshare: voucherele se cer **per advertiser asociat** (`affiliate-vouchers/?advertiser_id=…&valid=1&page=…`) | Răspunsul voucherelor nu conține ID-ul magazinului, doar numele; filtrarea după `advertiser_id` e singura mapare sigură. Bonus: mai puține requesturi (limita e 60/min). |
| Profitshare: ID extern = hash(advertiser + tip + cod + nume) | API-ul nu dă un ID de voucher. Datele de valabilitate nu intră în hash, ca o prelungire să fie o actualizare, nu o ofertă nouă. |
| Deeplink Profitshare prin `POST affiliate-links/` | Singura metodă documentată de generare a linkurilor. |
| Import pe pași de ~15 s, cu cursor salvat în `wp_options` și lock per rețea | Compatibil cu `max_execution_time` de pe shared hosting; o eroare (ex. limita de requesturi) nu pierde progresul („Continuă” / `--resume`). |
| Ofertele noi = **ciornă**; cele existente își actualizează doar cod/date/link/condiții, fără schimbare de status; cele din Trash nu sunt readuse; cele deja expirate nu sunt importate | Controlul editorial rămâne la tine; curățarea zilnică nu e anulată de import. |
| Asociere automată program ↔ magazin după domeniu (fără `www`), doar dacă e un singur magazin potrivit și nu are deja alt program | Mai puțină muncă manuală, fără asocieri greșite. Restul apar la „Neasociate”, cu opțiunea „Creează magazin nou”. |
| Un magazin = o rețea + un ID de program | Conform modelului de date inițial. Dacă un magazin e în ambele rețele, alegi una. |
| Credențialele se țin în opțiunea `cupons_settings` (baza de date), câmpurile secrete nu se mai afișează după salvare | Cerința: chei doar în admin, niciodată în cod/repo. |

## Redesign homepage (după feedback)

| Decizie | Motiv |
|---|---|
| Paletă nouă: violet `#5b3df5` + cerneală `#14112e` (în locul verdelui) | Cerut de proprietar: aspect mai premium. Contrast AA păstrat (Accessibility 100). |
| Homepage: fără bloc hero mare; sus doar H1 + căutare (compact, fundal deschis), apoi „Magazine populare” = **3 carduri mari** (primele 3 din „Ordine pe homepage”) cu oferte active și cea mai bună reducere reală | Cerut de proprietar (înlocuiește grila de 12/8 logo-uri din spec). H1 și căutarea rămân deasupra fold-ului pentru SEO și utilizare. |
| Mesajul „ofertele expirate dispar automat” apare o singură dată (footer), nu în bara de sus / hero / footer | Repetat de 3 ori părea insistent; bara de sus a fost scoasă. |

## Import live — ce am aflat din API-urile reale (30 sept. 2026)

| Observație | Ce am făcut |
|---|---|
| 2Performant: sign-in returnează `access-token`, `client`, `uid`, `token-type` în antete, exact cum presupusesem; listele au `metadata.pagination` (programe) și `pagination` (promoții) | Nimic de schimbat; ambele variante erau deja tratate. |
| Profitshare: voucherele vin sub cheia **`data`**, nu `result` (cum arată documentația) | Conectorul acceptă ambele. |
| Profitshare: statusurile de afiliere sunt în `commissions.affiliate_statuses`, iar **aproximativ jumătate din răspunsuri vin fără ele** | Până la 3 cereri, cu statusurile combinate; se importă doar programele active + aprobate. |
| Profitshare: o valoare cu virgulă în query (`type=code,promotion`) strică semnătura HMAC | Nu trimitem astfel de parametri. |
| Profitshare: toate voucherele din cont erau expirate la data testului | Corect: `valid=1` nu întoarce nimic, nu se importă nimic. |
| Magazinele se creează automat din programele acceptate (setare „La import, creează automat magazine”, implicit activă), cu logo descărcat și convertit WebP | PNG-urile cu paletă rămân PNG (GD nu le poate converti). Categoriile vin cu numele din rețea (ex. „IT&C”, „Retail”): le poți redenumi/uni din Cupoane → Categorii. |
| Titluri „Reduceri de până la 47%” | Marcaj „până la” (import automat + bifă în admin); badge-ul afișează „până la −47%”, nu „−47%”. |
