# Brief pentru verificare — site de cupoane (fără WordPress)

## Ce este
Site românesc de vouchere, reduceri și cupoane, în stilul Cuponeria. Monetizare prin linkuri de afiliere **2Performant** (Profitshare dezactivat deocamdată). Numele și domeniul sunt încă placeholdere: `{NUME_SITE}`, `{DOMENIU}`.

Obiective, în ordine: **poziții de top în Google**, viteză maximă, oferte mereu proaspete (expiratele dispar imediat).

## Tehnologie (stack-ul real)
- **Nu e Next.js / Astro / SPA.** E randare pe server, clasic: **Laravel 12** (PHP 8.3), șabloane **Blade**.
- Bază de date: **MySQL / MariaDB**.
- Admin: **Filament 5** la `/admin` (cupoane, magazine, categorii, deal-uri, pagini, texte, import 2Performant).
- Frontend: HTML generat pe server, **un singur CSS inline** (~25 KB minificat, ~5,5 KB gzip), **un singur JS vanilla** (`public/js/site.js`, cu `defer`). Fără build (fără Vite/npm), fără jQuery, fără fonturi web, fără scripturi terțe.
- Cache: cache complet de pagini în aplicație (golit la orice modificare din admin) + headere pentru LiteSpeed și Cloudflare. Rutele publice nu au sesiune, cookie-uri sau CSRF, ca să poată fi cache-uite oriunde.
- SEO în cod: `app/Support/Seo.php` (title, description, canonical, noindex sub 3 oferte, schema JSON-LD: Organization, WebSite+SearchAction, BreadcrumbList, FAQPage), `sitemap.xml` și `robots.txt` generate dinamic.
- Programări (cron): curățenie zilnică la 00:05 (oferte expirate, cu lock), import 2Performant la 06:30, ora României.

## Hosting
- **Producție (planificat):** hosting partajat **cPanel cu LiteSpeed**, PHP 8.3, MariaDB, cron la fiecare minut pentru scheduler-ul Laravel, **Cloudflare** în față (proxy, Brotli, cache rules cu bypass pe `/go/*`, `/api/*`, `/admin*`).
- **Acum:** rulează local în Docker (`serversideup/php:8.3-fpm-nginx` + MariaDB). Linkul public de preview e un tunel temporar Cloudflare (`*.trycloudflare.com`), cu `noindex` pe toate paginile.

## Restricții de design (impuse intenționat)
Fără modale, overlay-uri, caruseluri, reclame, newsletter, conturi de utilizator, comentarii, fonturi web sau icon fonts. Buget: Lighthouse ≥ 95, maximum ~10 requesturi pe pagină, CSS < 25 KB, JS < 10 KB.

## Unde să te uiți în sursă
| Ce | Fișier |
|---|---|
| Rute | `routes/web.php` |
| Pagini publice | `app/Http/Controllers/SiteController.php`, `resources/views/*.blade.php` |
| Layout (head, meta, schema, header, footer) | `resources/views/layouts/app.blade.php` |
| Cardul de cupon | `resources/views/partials/coupon.blade.php` |
| Toate interogările și regula de „ofertă activă” | `app/Support/Catalog.php`, `app/Models/Coupon.php` (`scopeActive`) |
| SEO | `app/Support/Seo.php`, `app/Http/Controllers/SitemapController.php` |
| CSS / JS | `resources/css/site.css`, `public/js/site.js` |
| Cache de pagini | `app/Support/PageCache.php`, `app/Http/Middleware/CachePage.php` |
| Redirect afiliere `/go/` și voturi | `app/Http/Controllers/GoController.php`, `VoteController.php` |
| Import 2Performant | `app/Import/` |
| Decizii tehnice explicate | `docs/DECIZII.md`, `README.md` |

## Ce aș vrea să verifici
1. **SEO tehnic și on-page** pentru un site de cupoane în România: structura URL-urilor (`/magazin/{slug}`, `/categorie/{slug}`), title-uri și descrieri, schema, noindex, sitemap, linkare internă.
2. **Performanță** (Core Web Vitals) pe stack-ul de mai sus: Laravel pe cPanel/LiteSpeed + Cloudflare.
3. **UX / conversie**: cardul de cupon (afișarea codului, „Verificat”, voturi), homepage, pagina de magazin, mobil.
4. Orice risc de securitate evident în redirectul `/go/`, voturi sau admin.

Te rog ca recomandările să fie pentru **Laravel + Blade pe cPanel/LiteSpeed cu Cloudflare**, nu pentru Next.js, Astro sau Vercel.
