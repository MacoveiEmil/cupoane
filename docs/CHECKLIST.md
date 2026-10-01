# Checklist de lansare (producție)

Bifează fiecare punct înainte să trimiți domeniul în Google Search Console.

## Server (cPanel / LiteSpeed)
- [ ] Proiectul stă **în afara** `public_html` (ex. `/home/UTILIZATOR/cupoane`), iar **Document Root**-ul domeniului e folderul `public/` al proiectului. Test: `https://domeniu.ro/.env` și `https://domeniu.ro/composer.json` trebuie să dea 404.
- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://domeniu.ro`, `APP_KEY` generat.
- [ ] PHP 8.3 cu `intl`, `gd`, `pdo_mysql`, `mbstring`, `fileinfo`, `zip`; **OPcache activ** (cPanel → Select PHP Version → Options).
- [ ] La fiecare deploy: `composer install --no-dev -o`, `php artisan migrate --force`, `php artisan optimize`.
- [ ] **Cron**: întreabă providerul care e frecvența minimă. Ideal la fiecare minut; merge și la 5 minute (curățarea de la 00:05 și importul de la 06:30 rulează atunci la prima trecere a cron-ului).

## Admin
- [ ] Parolă nouă, puternică (Profil).
- [ ] **Autentificarea în doi pași** activată din Profil (în producție e cerută automat la primul login). Păstrează codurile de recuperare.
- [ ] Opțional, cel mai sigur: regulă Cloudflare (WAF sau Zero Trust Access, gratuit) care permite `/admin*` doar de pe IP-urile / email-urile voastre.
- [ ] Datele 2Performant completate în **Setări și texte → 2Performant** (se salvează criptat).

## Cloudflare
- [ ] Proxy activ (nor portocaliu), SSL **Full (strict)**, HSTS activ.
- [ ] Brotli ON, **Rocket Loader OFF**, **Email Obfuscation OFF**, fără Web Analytics injectat automat (CSP-ul site-ului blochează scripturile externe).
- [ ] Cache Rules: **bypass** pentru `/go/*`, `/api/*`, `/admin*`, `/livewire*`, `/cauta*`. Dacă activezi cache pentru HTML, respectă `s-maxage` de la server (5 minute).
- [ ] **Security → Bots / AI Crawl Control**: decide explicit dacă blochezi roboții AI. Pentru apariția în răspunsurile ChatGPT / Perplexity / Google AI, lasă permise cel puțin `OAI-SearchBot`, `ChatGPT-User`, `PerplexityBot`, `Claude-User`.
- [ ] Bifează **Setări și texte → Reguli → Cloudflare** (IP-ul real pentru voturi).

## SEO (verificare după lansare)
- [ ] `https://domeniu.ro/robots.txt` **nu** conține `Disallow: /` simplu și are linia `Sitemap:`.
- [ ] Sursa homepage-ului **nu** conține `noindex`.
- [ ] `https://domeniu.ro/sitemap.xml` se deschide și conține magazinele.
- [ ] Search Console: proprietate pe domeniu, sitemap trimis.
- [ ] Rich Results Test pe o pagină de magazin: Breadcrumb valid.

## Viteză
- [ ] Antetele unei pagini (a doua încărcare): `X-LiteSpeed-Cache: hit` (dacă LiteSpeed Cache e activ pe server) sau `X-Page-Cache: HIT`.
- [ ] PageSpeed Insights pe homepage și o pagină de magazin, mobil: ≥ 95.

Testele automate (`php artisan test`, `tests/Feature/LaunchSafetyTest.php`) verifică deja: robots / noindex pe domeniul de producție, expirarea la miezul nopții, cache-ul, eticheta „Verificat”, antetele de securitate și blocarea SVG.
