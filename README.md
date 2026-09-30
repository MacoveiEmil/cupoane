# {NUME_SITE} — vouchere, reduceri și cupoane ({DOMENIU})

Site WordPress de cupoane pentru magazinele online din România, monetizat prin afiliere (2Performant, Profitshare).
Prioritățile sunt **viteza** și **prospețimea**: nicio ofertă expirată pe site, pagini complet cacheabile, 3 requesturi la prima încărcare.

| Folder | Conținut |
|---|---|
| `wp-content/plugins/cupons-core/` | **Toată logica**: date, `/go/`, voturi, curățare zilnică, SEO/schema, import, admin |
| `wp-content/themes/cupons-theme/` | **Doar afișare**: template-uri PHP, un CSS, un JS |
| `docs/DECIZII.md` | Deciziile tehnice și motivele lor |
| `docs/CHECKLIST.md` | Verificări înainte de lansare |
| `scripts/` | `seed.sh` (date demo), `build-zip.sh` (zip-uri pentru upload) |

Numele site-ului și domeniul nu sunt scrise în cod: se iau din **Setări → General**.

---

## 1. Dezvoltare locală (wp-env)

Cerințe: Docker Desktop (motorul WSL 2 pe Windows) și Node 18+.

```bash
npm install
npm run env:start        # http://localhost:8888  (admin / password)
npm run seed             # 20 magazine, 60 cupoane, 10 deal-uri (inclusiv expirate), limba română
npm run wp -- cupons cleanup --dry-run
```

- Prima pornire durează (se descarcă imaginile Docker); următoarele durează ~1 minut.
- `bash scripts/seed.sh reset` șterge datele demo. `npm run env:stop` oprește containerele. `npm run env:reset` șterge tot mediul.
- Local, Docker pe Windows e lent (6–9 s/pagină fără cache): e normal. Pe hosting, LiteSpeed Cache servește paginile în milisecunde.

## 2. Instalare pe cPanel (upload de zip-uri)

1. Pe calculatorul tău, după commit: `bash scripts/build-zip.sh` → `build/cupons-core.zip` și `build/cupons-theme.zip`.
2. WordPress → **Module → Adaugă → Încarcă modul** → `cupons-core.zip` → **Activează**.
   La activare se creează tabelele, etichetele, paginile (Acasă, Magazine, Categorii, Deal-uri, Top oferte, Caută + paginile statice) și programarea zilnică.
3. **Aspect → Teme → Adaugă → Încarcă tema** → `cupons-theme.zip` → **Activează**.
4. Din directorul oficial WordPress: **Rank Math SEO** și **LiteSpeed Cache** → Activează (setările mai jos).
5. **Setări → General**: titlul site-ului (= {NUME_SITE}), limba **Română**, fus orar **București**.
6. **Setări → Legături permanente**: „Nume articol” (`/%postname%/`) → Salvează.
7. **Setări → Discuții**: debifează comentariile.
8. **Aspect → Personalizează → Identitatea site-ului**: logo (opțional) și iconița site-ului (favicon).
9. **Cupoane → Setări**: praguri, opțiunea Cloudflare, cheile API (secțiunea 8).

**Actualizare**: construiești din nou zip-urile și le încarci; WordPress oferă „Înlocuiește versiunea curentă”. Datele nu se pierd.

**Nu schimba slug-urile** paginilor create automat (`magazine`, `categorii`, `deal-uri`, `top-oferte`, `cauta`): tema le recunoaște după ele.
Conținutul paginii **Acasă** este textul SEO de pe homepage; conținutul paginilor statice îl scrii tu.

## 3. Cron de sistem (obligatoriu pe producție)

WP-Cron pornește doar la vizite, iar cu paginile servite din cache pornește rar. Curățarea zilnică (00:05, ora României) trebuie să ruleze sigur.

**a) În `wp-config.php`**, deasupra liniei `/* That's all, stop editing! */`:

```php
define('DISABLE_WP_CRON', true);
```

**b) cPanel → Cron Jobs → Add New Cron Job**, „Once Per Five Minutes” (`*/5 * * * *`), comanda:

```
cd /home/UTILIZATOR/public_html && /usr/local/bin/php wp-cron.php >/dev/null 2>&1
```

Înlocuiește `UTILIZATOR` și calea cu cele reale (calea PHP o vezi în cPanel → „Select PHP Version” sau întreabă hostingul).
Alternativ, dacă hostingul are WP-CLI: `wp --path=/home/UTILIZATOR/public_html cron event run --due-now --quiet`.

Verificare: **Cupoane → Setări** arată „Următoarea rulare: … 00:05” și rezultatul ultimei curățări.
Rulare manuală: butonul „Rulează curățarea acum” sau `wp cupons cleanup`.

## 4. Cum funcționează prospețimea

| Situație | Ce se întâmplă |
|---|---|
| Oferta are expirare și ziua a trecut | Dispare imediat din toate listările. La 00:05 e mutată în Trash (golit automat după 30 de zile). |
| Oferta nu are expirare și n-a fost verificată de 30 de zile (setabil) | La 00:05 trece în ciornă și apare la **Cupoane → De reverificat**. „Verificat azi” o republică. |
| Oferta expiră azi sau mâine | Paginile ei se regenerează, ca eticheta „Expiră azi / mâine” să fie corectă. |
| După orice curățare, editare sau import | Purge LiteSpeed Cache pe magazin, categorii, etichete, homepage, indexuri. |

`EMPTY_TRASH_DAYS` are implicit 30 în WordPress: **nu îl suprascrie** în `wp-config.php`.
Cupoanele nu au URL-uri proprii (apar doar în listări, cu ancora `#c-{id}`), deci ștergerea lor nu creează 404-uri.

## 5. Comenzi WP-CLI

```bash
wp cupons cleanup [--dry-run]           # curățarea zilnică, la cerere
wp cupons import                        # import din toate rețelele configurate
wp cupons import --network=profitshare  # doar o rețea
wp cupons import --resume               # continuă un import întrerupt
```

## 6. LiteSpeed Cache

Pluginul face singur purge la fiecare schimbare. Setări recomandate (**LiteSpeed Cache → …**):

| Unde | Setare |
|---|---|
| Cache → Cache | Enable Cache: **ON**; Cache Logged-in Users: OFF; Cache REST API: **OFF** |
| Cache → TTL | Default Public Cache TTL: `604800` (o săptămână; purge-ul face restul) |
| Cache → Excludes → Do Not Cache URIs | `/go/` și `/wp-json/cupons/` (pe rânduri separate) |
| Cache → Excludes → Do Not Cache Cookies | **nu** adăuga `cupons_v` (paginile sunt identice pentru toți; cookie-ul e citit de JS) |
| Cache → Purge | „Purge All On Upgrade”: ON |
| Cache → Browser | Browser Cache: **ON** (CSS/JS au versiune în URL, pot fi cache-uite mult) |
| Page Optimization | **Totul OFF** (CSS/JS minify/combine, UCSS, lazy load, guest mode): tema are deja un singur CSS și un singur JS, iar imaginile au lazy load nativ. Opțional: HTML Minify ON. |
| Image Optimization | Opțional; logo-urile sunt deja generate WebP de WordPress |
| Crawler | Opțional ON, ca paginile să fie calde după purge-ul de la 00:05 |

`/go/` și endpoint-ul de vot trimit oricum antete `no-cache` și `X-LiteSpeed-Cache-Control: no-cache`; excluderile sunt plasă de siguranță.

## 7. Cloudflare

| Unde | Setare |
|---|---|
| SSL/TLS | Full (strict); **Always Use HTTPS** ON |
| Speed → Optimization | **Brotli** ON; **Rocket Loader OFF**; Early Hints ON (opțional) |
| Scrape Shield | **Email Address Obfuscation OFF** (adaugă un script pe fiecare pagină) |
| Caching → Cache Rules | Regula 1: *URI Path starts with* `/go/` **sau** `/wp-json/` → **Bypass cache**. HTML-ul îl cache-uiește LiteSpeed; nu activa „Cache Everything” fără APO. |
| Caching → Browser Cache TTL | „Respect Existing Headers” |
| Security → Bots | Fără „Bot Fight Mode” agresiv pe `/wp-json/cupons/` (voturile vin din browserul vizitatorului) |

Apoi, în WordPress: **Cupoane → Setări → Cloudflare** bifat, ca limitarea voturilor să folosească IP-ul real al vizitatorului.

## 8. Rank Math

Parcurge wizard-ul (modul „Easy” e suficient), apoi:

- **Tip site / Knowledge Graph**: alege **Organization** (nu „Person”), cu numele și logo-ul site-ului.
- **Titles & Meta**: lasă titlurile implicite. Pluginul aplică template-urile „Cod reducere {Magazin} {luna} {an} — vouchere, reduceri și cupoane” etc.; un titlu/descriere setat manual în Rank Math pe o pagină sau pe un magazin **are prioritate**.
- **Sitemap**: include taxonomiile *Magazine*, *Categorii*, *Etichete* și paginile. Magazinele/categoriile cu sub 3 oferte active sunt noindex și ies automat din sitemap.
- **Breadcrumbs**: lasă-le dezactivate (tema are breadcrumbs proprii + schema BreadcrumbList).

Fără Rank Math (sau înainte de wizard), pluginul scrie singur title, description, canonical, robots și schema.

## 9. Chei API și import din rețele

Cheile se introduc doar în admin: **Cupoane → Setări → Rețele de afiliere** (se salvează în baza de date, nu în cod și nu în repo).

| Rețea | Ce îți trebuie | Unde le găsești |
|---|---|---|
| 2Performant | email + parolă cont afiliat + **user key** | network.2performant.com → Settings |
| Profitshare | **API user** + **API key** | contul de afiliat Profitshare → secțiunea API |

Apoi **Cupoane → Import → Importă acum** pentru fiecare rețea:

1. se importă programele (magazinele) la care ești acceptat; cele al căror domeniu coincide cu URL-ul unui magazin se asociază automat;
2. restul apar la **Neasociate**: le legi de un magazin existent sau creezi unul nou;
3. se importă ofertele programelor asociate, ca **ciorne** (deduplicate după ID-ul extern). Le verifici și le publici din **Cupoane → Ciorne**.

Rulează importul din nou după asocieri. Importul lucrează în pași de ~15 s (compatibil cu shared hosting) și poate fi reluat dacă se întrerupe.

**Adaugă deal rapid**: lipești URL-ul produsului, alegi magazinul; deeplink-ul se generează după rețeaua magazinului, restul câmpurilor le completezi.

Profitshare verifică ora cererii (toleranță 20 s): serverul trebuie să aibă ora sincronizată (standard pe hosting).

## 10. Buget de performanță

Măsurat cu Lighthouse mobil (HTML servit din cache + gzip, ca pe LiteSpeed):

| Pagină | Performance | Accessibility | Best Practices | SEO | LCP | CLS | Requesturi | Transfer |
|---|---|---|---|---|---|---|---|---|
| Homepage | 98–99 | 100 | 100 | 100 | 1,3–1,7 s | 0 | 3 | 17 KB |
| Pagină de magazin | 99–100 | 100 | 100 | 100 | 1,0–1,5 s | 0 | 3 | 15 KB |

Reguli care păstrează bugetul: un singur `style.css` (< 25 KB), un singur `app.js` (< 10 KB, `defer`), fonturi de sistem, iconițe într-un sprite SVG inline, logo-uri WebP cu dimensiuni explicite și `loading="lazy"` sub fold, fără scripturi terțe.
Dacă adaugi ceva pe frontend, verifică din nou cu Lighthouse.

## 11. Repo GitHub privat (pași manuali)

`gh` nu e instalat pe acest calculator, deci repo-ul se creează din browser:

1. Pe [github.com/new](https://github.com/new): nume `cupons-site`, **Private**, **fără** README / .gitignore / licență → *Create repository*.
2. În folderul proiectului (Git Bash sau terminal):

```bash
git remote add origin https://github.com/UTILIZATORUL-TAU/cupons-site.git
git push -u origin main
```

La primul push, Git îți cere autentificarea în browser (Git Credential Manager).
Alternativ, cu GitHub CLI: `winget install GitHub.cli`, apoi `gh auth login` și `gh repo create cupons-site --private --source=. --push`.
