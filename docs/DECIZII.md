# Decizii tehnice — varianta fără WordPress

| Decizie | Motiv |
|---|---|
| **Laravel 12 + Filament 5** (PHP 8.3, MySQL/MariaDB) | Rulează pe același tip de hosting cPanel/LiteSpeed; Filament dă un admin complet (liste, formulare, filtre, upload) cu puțin cod. |
| Docker local cu `serversideup/php` + extensiile `intl`, `gd`; `vendor/` într-un volum Docker | Fără instalări pe Windows; citirea a mii de fișiere prin montarea Windows era de ~10× mai lentă. |
| O singură definiție a ofertei active (`Coupon::scopeActive`) | publicată, `starts_on ≤ azi` (sau gol), `expires_on ≥ azi` (sau gol), în fusul Europe/Bucharest; expirarea e inclusivă. |
| Coș = soft delete; golit definitiv după 30 de zile de `cupons:cleanup` | Echivalentul Trash-ului WordPress, fără pluginuri. |
| Cache complet de pagini în aplicație (store `pages` pe fișiere, golit integral la orice modificare, o singură dată per import / curățenie; expiră singur la miezul nopții) | Viteză pe orice hosting; o pagină din cache = **zero interogări în baza de date** (cache-ul rulează înaintea încărcării modelelor din URL, CSS-ul și CSP-ul sunt compilate în `bootstrap/cache/assets.php`). Cheia include versiunea CSS/JS și doar parametrii `q` / `page`. Căutarea nu intră în cache. |
| Rute publice **fără sesiune, cookie-uri și CSRF** | Paginile pot fi cache-uite peste tot (aplicație, LiteSpeed, Cloudflare). Adminul are stiva proprie Filament. |
| Vot fără token CSRF; protecție same-origin + cookie `cv` + un vot per cupon per IP per zi (HMAC cu APP_KEY) + throttle | Un token CSRF ar expira în HTML-ul din cache. |
| Proxy-uri de încredere: în producție **doar intervalele IP Cloudflare**, doar pentru IP și https; domeniul fixat din `APP_URL` | `$request->ip()` e IP-ul real al vizitatorului (voturi, limitări) și nu poate fi falsificat direct la server; antetul `X-Forwarded-Host` nu poate schimba canonical-ul sau linkurile. Lista IP: cloudflare.com/ips (de actualizat dacă o schimbă). |
| `noindex` pe tot ce nu e producție (`APP_ENV`), nu după host | Tunelul / localul nu se indexează; în producție nu poate rămâne activ din greșeală (test automat). |
| CSP strict pe site-ul public (doar resurse proprii, hash pentru CSS-ul și scriptul inline) + antete de securitate; adminul cu autentificare în doi pași (obligatorie în producție) | Fără scripturi terțe, CSP-ul nu costă nimic. |
| CSS inline (minificat automat, cache după data fișierului), un singur JS cu `defer`, fără build (fără Vite/npm pe server) | Zero requesturi blocante, LCP ~1 s; deploy simplu pe cPanel. |
| Design simplu, luminos: alb, **verde `#0f7a5c` = acțiune și încredere** (butoane, „Verificat”, linkuri), **portocaliu `#e4570f` = economie** (procente, „până la”, număr de oferte, accente). Portocaliul doar pe text mare / îngroșat (contrast 3,7:1). Un singur fișier `resources/css/site.css` | Ochiul găsește întâi reducerea; butoanele rămân clar separate de cifre. Fără imagini decorative sau fonturi web (fonturi de sistem). |
| Import 2Performant pe pași de ~15 s, cu stare în cache și lock | Compatibil cu limitele de timp de pe shared hosting; reluabil după erori. |
| Magazine create automat din programele acceptate; logo descărcat și convertit WebP (max 320×160) | Mai puțină muncă manuală; imagini mici. Titlurile importate pot fi adresate afiliaților — ofertele intră ca **ciorne** tocmai ca să fie verificate. |
| Datele de acces 2Performant criptate în baza de date (Crypt, APP_KEY); copiate local din varianta WordPress fără afișare | Niciodată în cod sau în repo. |
| `/go/{id}` cupoane, `/go/d{id}` deal-uri, `/go/{slug}` magazine | ID-urile cupoanelor și deal-urilor sunt în tabele diferite. |
| Click-uri și voturi agregate zilnic prin `upsert` | Portabil (MySQL în producție, SQLite în teste). |
| Teste automate (`php artisan test`) | Paginile publice, regula de ofertă activă, SEO, `/go/`, voturi, toate ecranele de admin, curățarea zilnică; `LaunchSafetyTest`: indexare în producție, `APP_DEBUG` oprit, `X-Forwarded-Host`, IP real doar prin Cloudflare, expirare la miezul nopții, cache, „Verificat”, CSP, upload fără SVG. |
