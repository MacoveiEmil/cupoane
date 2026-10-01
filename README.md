# {NUME_SITE} — vouchere, reduceri și cupoane (varianta fără WordPress)

Site de cupoane pentru magazinele online din România, construit pe **Laravel 12** cu admin **Filament 5**.
Prioritățile sunt **viteza** (Lighthouse mobil 100) și **prospețimea** (nicio ofertă expirată pe site).

- `app/Models`: date (magazine, cupoane, deal-uri, categorii, etichete, pagini, setări)
- `app/Filament`: panoul de administrare (în română)
- `app/Import`: conectorul 2Performant și importul pe pași
- `app/Http/Controllers` + `resources/views`: site-ul public
- `resources/css/site.css`: tot CSS-ul (scris inline în pagină, minificat automat)
- `public/js/site.js`: singurul JS (cod, voturi, sugestii de căutare)
- `docs/DECIZII.md`: deciziile tehnice și motivele lor

---

## 1. Rulare locală (Docker)

Cerințe: Docker Desktop (motorul WSL 2 pe Windows).

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
```

- Site: http://localhost:8080 · Admin: http://localhost:8080/admin
- Contul de admin local vine din `.env` (`ADMIN_EMAIL` / `ADMIN_PASSWORD`, vezi `.env.example`).
- Teste: `docker compose exec app php artisan test`
- Import manual: `docker compose exec app php artisan cupons:import`
- Curățare manuală: `docker compose exec app php artisan cupons:cleanup --dry-run`

## 2. Ghid de administrare (/admin)

| Meniu | Ce faci acolo |
|---|---|
| **Pe scurt** (prima pagină) | Oferte active, ciorne de verificat, oferte de reverificat, click-uri pe 7 zile, oferte care expiră curând |
| **Conținut → Cupoane** | Taburi: *Toate / Active pe site / Ciorne / De reverificat / Expiră în 7 zile*. Butonul **„Verificat azi”** publică oferta și îi pune data verificării. Ofertele importate apar pe site cu „Adăugat pe …” până le verifici; „Verificat pe …” apare doar după „Verificat azi”. Selectezi mai multe → „Verificat azi și publică” sau „Mută în coș”. |
| **Conținut → Magazine** | Nume, adresă, link de afiliere, logo (se convertește automat în WebP), categorii, **„Ordine pe homepage”** (1, 2, 3 = cardurile mari), texte „Despre”, informații utile, întrebări frecvente |
| **Conținut → Deal-uri** | **„Adaugă deal rapid”**: lipești URL-ul produsului, alegi magazinul → deeplink 2Performant generat automat; completezi prețuri și imagine |
| **Organizare → Categorii / Etichete** | Categorii pe 2 niveluri; etichetele tematice (`/oferte/transport-gratuit` etc.) |
| **Site → Pagini** | Despre noi, Contact, Termeni… (editor de text; titlurile H2 devin cuprins); bifezi ce apare în footer |
| **Site → Setări și texte** | **Texte site**: titlul mare de pe homepage, subtitlul, textul SEO, textul din footer, mențiunea de afiliere. **Reguli**: zile până la reverificare, pragul de voturi, Cloudflare, creare automată de magazine. **2Performant**: datele de acces (criptate) |
| **Site → Import din rețele** | „Importă acum” cu progres live; programele neasociate cu un magazin |

Orice salvare în admin golește automat cache-ul de pagini, deci modificarea apare imediat pe site.

## 3. Instalare pe cPanel

Cerințe hosting: **PHP 8.3** cu extensiile `intl`, `gd`, `pdo_mysql`, `mbstring`, `fileinfo`, `zip` (cPanel → *Select PHP Version*), MySQL/MariaDB, acces SSH sau Terminal în cPanel (recomandat).

1. **Baza de date**: cPanel → *MySQL Databases* → creezi baza, utilizatorul, îi dai toate drepturile.
2. **Codul**: urci proiectul într-un folder din afara `public_html` (ex. `/home/UTILIZATOR/cupoane`), fără `vendor/` și fără `.env`.
3. **Domeniul**: cPanel → *Domains* → „Document Root” al domeniului = `/home/UTILIZATOR/cupoane/public`.
   (Dacă nu se poate schimba: copiază conținutul din `public/` în `public_html` și ajustează căile din `public_html/index.php`.)
4. **Configurare**: copiezi `.env.example` → `.env` și completezi: `APP_NAME`, `APP_URL=https://domeniu.ro`, `APP_ENV=production`, `APP_DEBUG=false`, datele bazei (`DB_HOST=localhost`, …), `ADMIN_EMAIL`, `ADMIN_PASSWORD` (puternică).
5. **Instalare** (Terminal cPanel, în folderul proiectului):
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan migrate --seed --force
   php artisan storage:link
   php artisan filament:assets
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
6. **Cron** (cPanel → *Cron Jobs*, „Once Per Minute”):
   ```
   cd /home/UTILIZATOR/cupoane && php artisan schedule:run >> /dev/null 2>&1
   ```
   Programările din `routes/console.php`: curățarea zilnică la **00:05** și importul 2Performant la **06:30** (ora României).
7. Intri în `/admin`, schimbi parola din profil și completezi **Setări și texte → 2Performant**.

Înainte de lansare: **[docs/CHECKLIST.md](docs/CHECKLIST.md)**.

**Actualizare**: urci fișierele noi, apoi `composer install --no-dev -o`, `php artisan migrate --force`, `php artisan optimize`.

## 4. Viteză și cache

- **Cache complet de pagini în aplicație**: prima vizită generează HTML-ul, următoarele îl primesc în milisecunde. Se golește automat la orice modificare din admin, la import și la curățarea zilnică; o schimbare de CSS/JS regenerează singură paginile. Orice pagină din cache (aplicație și LiteSpeed) **expiră singură la miezul nopții**, când pot expira oferte; Cloudflare primește maximum 5 minute (`s-maxage=300`).
- Paginile publice **nu setează cookie-uri și nu pornesc sesiuni**, deci pot fi cache-uite și de server/CDN.
- **LiteSpeed**: răspunsurile trimit `X-LiteSpeed-Cache-Control`; la modificări se trimite `X-LiteSpeed-Purge`. Nu e nevoie de pluginul LiteSpeed Cache.
- **Cloudflare**: *Cache Rules* → bypass pentru `/go/*`, `/api/*`, `/admin*`; Brotli ON, Rocket Loader OFF, Email Obfuscation OFF. Apoi bifează **Setări și texte → Reguli → Cloudflare**.
- CSS inline (~6 KB comprimat), un singur JS mic cu `defer`, fără fonturi web, logo-uri WebP cu dimensiuni explicite.

Lighthouse mobil (local): homepage 100/100/100/100.

## 5. SEO

- Titluri și descrieri generate automat cu luna, anul și numărul de oferte (ex. „Cod reducere Answear.ro octombrie 2026 — vouchere, reduceri și cupoane”).
- `noindex` pe: căutare, paginări ≥ 2, categorii / etichete cu **sub 3 oferte active**, magazine **fără nicio ofertă activă și fără text „Despre”** (se poate forța indexarea din fișa magazinului); `/go/` e exclus din `robots.txt`.
- Schema JSON-LD: Organization, WebSite, BreadcrumbList, FAQPage (din întrebările afișate pe pagină).
- `sitemap.xml` cu doar paginile indexabile → trimite-l în Google Search Console.
- Un singur H1 pe pagină, canonical corect, linkurile de ieșire `rel="sponsored nofollow"`.

## 6. Repo GitHub (manual)

```bash
git remote add origin https://github.com/UTILIZATORUL-TAU/cupoane-laravel.git
git push -u origin main
```
(creezi întâi repo-ul **Private** pe github.com/new, fără README).
