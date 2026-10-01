# Decizii tehnice — varianta fără WordPress

| Decizie | Motiv |
|---|---|
| **Laravel 12 + Filament 5** (PHP 8.3, MySQL/MariaDB) | Rulează pe același tip de hosting cPanel/LiteSpeed; Filament dă un admin complet (liste, formulare, filtre, upload) cu puțin cod. |
| Docker local cu `serversideup/php` + extensiile `intl`, `gd`; `vendor/` într-un volum Docker | Fără instalări pe Windows; citirea a mii de fișiere prin montarea Windows era de ~10× mai lentă. |
| O singură definiție a ofertei active (`Coupon::scopeActive`) | publicată, `starts_on ≤ azi` (sau gol), `expires_on ≥ azi` (sau gol), în fusul Europe/Bucharest; expirarea e inclusivă. |
| Coș = soft delete; golit definitiv după 30 de zile de `cupons:cleanup` | Echivalentul Trash-ului WordPress, fără pluginuri. |
| Cache complet de pagini în aplicație (store separat `pages`, golit integral la orice modificare) | Viteză pe orice hosting, fără dependență de LiteSpeed; fără risc de pagini vechi. Cheia include versiunea CSS/JS. |
| Rute publice **fără sesiune, cookie-uri și CSRF** | Paginile pot fi cache-uite peste tot (aplicație, LiteSpeed, Cloudflare). Adminul are stiva proprie Filament. |
| Vot fără token CSRF; protecție same-origin + cookie `cv` + un vot per cupon per IP per zi (HMAC cu APP_KEY) + throttle | Un token CSRF ar expira în HTML-ul din cache. |
| CSS inline (minificat automat, cache după data fișierului), un singur JS cu `defer`, fără build (fără Vite/npm pe server) | Zero requesturi blocante, LCP ~1 s; deploy simplu pe cPanel. |
| Design „luminos și jucăuș”: crem, coral, galben, mentă, lila; contururi groase cu umbre decalate; reduceri ca abțibilduri | Identitate complet diferită de varianta WordPress; fără imagini decorative sau fonturi web (doar `ui-rounded` + fonturi de sistem). |
| Import 2Performant pe pași de ~15 s, cu stare în cache și lock | Compatibil cu limitele de timp de pe shared hosting; reluabil după erori. |
| Magazine create automat din programele acceptate; logo descărcat și convertit WebP (max 320×160) | Mai puțină muncă manuală; imagini mici. Titlurile importate pot fi adresate afiliaților — ofertele intră ca **ciorne** tocmai ca să fie verificate. |
| Datele de acces 2Performant criptate în baza de date (Crypt, APP_KEY); copiate local din varianta WordPress fără afișare | Niciodată în cod sau în repo. |
| `/go/{id}` cupoane, `/go/d{id}` deal-uri, `/go/{slug}` magazine | ID-urile cupoanelor și deal-urilor sunt în tabele diferite. |
| Click-uri și voturi agregate zilnic prin `upsert` | Portabil (MySQL în producție, SQLite în teste). |
| Teste automate (`php artisan test`) | Paginile publice, regula de ofertă activă, SEO, `/go/`, voturi, toate ecranele de admin, curățarea zilnică. |
