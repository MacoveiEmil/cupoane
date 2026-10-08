# Handover — site de cupoane (Laravel)

Document de predare: tot ce trebuie să știe cineva (om sau AI) care preia proiectul.
Starea curentă și pașii următori sunt în **[TRACKING.md](TRACKING.md)**; deciziile tehnice în **[DECIZII.md](DECIZII.md)**.

## 1. Ce este

Site românesc de vouchere, reduceri și cupoane, monetizat prin afiliere **2Performant** (Profitshare dezactivat deocamdată).
Numele și domeniul sunt încă placeholdere: `{NUME_SITE}`, `{DOMENIU}`.

Obiective, în ordine: poziții de top în Google, viteză maximă (Lighthouse ≥ 95), oferte mereu proaspete (expiratele dispar automat).

## 2. Repo-ul

| Ramură | Conținut |
|---|---|
| `main` | **Site-ul activ**: Laravel 12 + Filament 5 (acest folder) |
| `wordpress` | Varianta inițială pe WordPress (tema `cupons-theme` + pluginul `cupons-core`), oprită. Păstrată pentru referință. |

Reguli de lucru:
- **Commit după fiecare funcționalitate terminată și testată**, cu mesaj în română care spune *ce* și *de ce*.
- După fiecare commit important se actualizează **TRACKING.md** (secțiunile „Făcut” / „Urmează”).
- Înainte de o schimbare riscantă: `git tag inainte-de-X` (revenire: `git checkout inainte-de-X` sau `git revert <commit>`).
- Nu intră niciodată în repo: `.env`, parole, chei API, `vendor/`, `storage/` cu date, `.tools/` (cloudflared), arhive `.zip`, `capturi/`.

## 3. Start rapid (local, Docker)

```bash
git clone https://github.com/MacoveiEmil/cupoane.git && cd cupoane
cp .env.example .env        # apoi: APP_ENV=local, APP_DEBUG=true, APP_URL=http://localhost:8080
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
```

- Site: http://localhost:8080 · Admin: http://localhost:8080/admin (contul din `ADMIN_EMAIL` / `ADMIN_PASSWORD` din `.env`)
- Teste: `docker compose exec app php artisan test` (31 de teste; toate trebuie să treacă înainte de commit)
- Import manual 2Performant: `docker compose exec app php artisan cupons:import`
- Curățenie manuală: `docker compose exec app php artisan cupons:cleanup --dry-run`
- Conținut magazine (JSON, pentru AI): `php artisan cupons:content export|import <fișier>`

Datele de acces 2Performant se pun **doar din admin** (Setări și texte → 2Performant); se salvează criptat cu `APP_KEY`.
Dacă se schimbă `APP_KEY`, trebuie reintroduse.

### Link de preview (opțional)
`cloudflared` (descărcat local în `.tools/`, nu e în repo): `./.tools/cloudflared.exe tunnel --url http://localhost:8080`.
Adresa `*.trycloudflare.com` se schimbă la fiecare pornire; tot ce nu e producție are automat `noindex`.
Cloudflare blochează roboții AI pe aceste adrese (pentru verificări cu AI: arhiva sursei + capturi).

## 4. Arhitectura pe scurt

- **Randare pe server** (Blade), fără SPA, fără build: un singur CSS inline (`resources/css/site.css`, ≤ 25 KB minificat) și un singur JS (`public/js/site.js`, ~6,5 KB).
- **Cache de pagini** (`app/Support/PageCache.php`, `CachePage`): pagina din cache = zero interogări în baza de date; se golește la orice modificare din admin și expiră singur la miezul nopții. Antete pentru LiteSpeed și Cloudflare (`s-maxage=300`).
- **Regula de ofertă activă**: un singur loc, `Coupon::scopeActive` (publicată, început ≤ azi, expirare ≥ azi, ora României).
- **Interogările site-ului**: `app/Support/Catalog.php`.
- **SEO**: `app/Support/Seo.php` (title, description, canonical, noindex, JSON-LD), `SitemapController` (sitemap + robots).
- **Afiliere**: `/go/{id}` (cupon), `/go/d{id}` (deal), `/go/{slug}` (magazin) → `GoController`, numără click-urile reale.
- **Import 2Performant** (`app/Import`): pași de ~15–25 s; faze: programe acceptate → placeholdere (programe neaplicate, România) → oferte (ca ciorne) → logo-uri.
  - Program acceptat → magazinul și ofertele primesc automat linkul de afiliere (`Importer::syncAffiliateLinks`).
  - Program neacceptat → magazin-placeholder **ascuns** (fără oferte publicate: noindex, lipsă din liste și sitemap); linkurile duc direct la magazin.
- **Programări** (`routes/console.php`): curățenie zilnică 00:05, import 06:30 (Europe/Bucharest). În producție: cron la fiecare minut pentru `schedule:run`.
- **Securitate**: în producție proxy-uri de încredere doar Cloudflare (`app/Http/Middleware/TrustProxies.php`), domeniu fix din `APP_URL`, CSP strict (`SecurityHeaders`), 2FA obligatoriu în admin, upload fără SVG.

## 5. Unde e fiecare lucru

| Ce | Fișier |
|---|---|
| Rute | `routes/web.php` |
| Pagini publice | `app/Http/Controllers/SiteController.php`, `resources/views/*.blade.php` |
| Layout (head, meta, schema, header, footer) | `resources/views/layouts/app.blade.php` |
| Cardul de cupon (listă) / căsuța (homepage) | `resources/views/partials/coupon.blade.php`, `coupon-tile.blade.php` |
| Texte editabile + valori implicite | `app/Support/Settings.php` (admin: Setări și texte) |
| Conținut unic per magazin | câmpurile magazinului (admin) + `app/Console/Commands/ContentCommand.php` |
| Pagina de metodologie | `/cum-verificam-codurile` (admin: Pagini; textul inițial în `resources/content/`) |
| Teste | `tests/Feature/*` (`LaunchSafetyTest` = verificările de lansare) |
| Lansare în producție | `README.md` §3 + `docs/CHECKLIST.md` |

## 6. Design (reguli)

- Trei culori, fiecare cu un singur sens: **verde `#0f7a5c`** = acțiune / încredere (butoane, „Verificat”), **portocaliu `#e4570f`** = reduceri cu cifră, **albastru-cerneală `#2d5b8c`** = oferte fără cifră („Ofertă”, „Transport gratuit”).
- Interzise prin specificație: modale, overlay-uri, carusele, reclame, newsletter, conturi de utilizator, comentarii, fonturi web / icon fonts, jQuery, scripturi terțe.
- Homepage: titlu + paragraf + căutare → „Cele mai noi vouchere” (4 căsuțe) → „Top coduri și oferte” (4) → Magazine populare → Coduri exclusive → Categorii → text SEO → FAQ.

## 7. Conturi și acces (fără parole aici)

- **GitHub**: `MacoveiEmil/cupoane`.
- **2Performant**: contul de afiliat al proprietarului; datele se introduc doar în admin.
- **Admin site**: utilizatorul creat de seeder din `.env`; în producție se schimbă parola și se activează 2FA din Profil.
