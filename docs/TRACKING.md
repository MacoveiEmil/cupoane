# Tracking — ce s-a făcut / ce urmează

Se actualizează la fiecare commit important. Format: cea mai nouă intrare sus.
Legendă: ✅ făcut · 🔄 în lucru · ⏳ urmează · 🧑 necesită acțiunea proprietarului

## Starea curentă (8 octombrie 2026)

- Site funcțional local (Docker), 31 de teste trec, Lighthouse ~100 local.
- 2Performant: 12 programe acceptate (9 magazine cu link de afiliere), 7 în așteptare, ~445 neaplicate importate ca placeholdere ascunse.
- 107 oferte importate: 5 publicate, **102 ciorne de verificat** (de la 64 de magazine).
- Încă nelansat: lipsesc numele, domeniul și hostingul.

## ⏳ Urmează (în ordinea priorității)

### Înainte de lansare
- [ ] 🧑 Alege **numele** și **domeniul**; înlocuiește `{NUME_SITE}` / `{DOMENIU}` (`.env` `APP_NAME`, `APP_URL`; imaginea `public/og.jpg`, favicon).
- [ ] 🧑 Hosting cPanel / LiteSpeed + Cloudflare, după `README.md` §3 și **`docs/CHECKLIST.md`** (document root = `public/`, `APP_DEBUG=false`, cron, OPcache).
- [ ] 🧑 Admin în producție: parolă nouă, **2FA** din Profil, opțional regulă Cloudflare pentru `/admin`.
- [ ] 🧑 Paginile statice sunt goale: *Despre noi, Cum funcționează, Transparență și afiliere, Calendar reduceri, Contact, Termeni și condiții, Confidențialitate (GDPR), Cookies*. Cele legale sunt obligatorii la lansare.
- [ ] 🧑 Verifică și publică **ciornele** (Admin → Cupoane → Ciorne → „Verificat azi și publică”): fiecare magazin cu o ofertă publicată devine vizibil și indexabil.
- [ ] Validare JSON-LD pe validator.schema.org (homepage + un magazin cu FAQ).
- [ ] După lansare: verifică IP-ul real al vizitatorilor (voturi), antetele de cache (`X-LiteSpeed-Cache: hit`, `cf-cache-status`), PageSpeed pe domeniu.

### Conținut și SEO
- [ ] 🧑 Text unic pentru primele 20–30 de magazine: „Text sub oferte” + 3–5 întrebări proprii (`php artisan cupons:content export … --empty`, completat manual sau cu AI, apoi `import`).
- [ ] 🧑 Textul SEO de pe homepage și paragraful de sub H1 (admin → Setări și texte).
- [ ] Search Console: proprietate pe domeniu + sitemap.

### Afiliere
- [ ] 🧑 Aplică în 2Performant la programele care **acceptă site-uri de cupoane** (admin → Magazine → filtrul „Acceptă site-uri de cupoane”: 398). Recomandat **după** ce site-ul e pe domeniul final (magazinele verifică site-ul).
- [ ] 🧑 Întreabă suportul 2Performant dacă permit aplicarea la programe prin API (nu e documentată public).
- [ ] Profitshare: reactivare conector când e nevoie (codul există în varianta WordPress).

### Idei amânate (decise)
- Carusel pe homepage: **nu** (decizie 5 oct.; impact mic pe viteză, dar UX slab; în specificație interzis).
- „Top coduri și oferte” repetă ofertele din „Cele mai noi” cât timp sunt puține oferte active (dispare singur).

## ✅ Făcut

| Data | Commit | Ce |
|---|---|---|
| 2026-10-08 | — | Repo GitHub, HANDOVER + TRACKING, README actualizat |
| 2026-10-05 | `34f6883` | Magazine-placeholder din programele 2Performant neaplicate (ascunse până au oferte); oferte din toată rețeaua ca ciorne; afiliere automată la acceptare; filtre Afiliere / „Acceptă site-uri de cupoane”; categorii în română |
| 2026-10-05 | `d9cab6f` | **Fix**: magazinele acceptate trimiteau direct la magazin (fără comision) → acum prin linkul de afiliere 2Performant |
| 2026-10-01 | `04959f7` | Homepage: 4 căsuțe pe secțiune (2×2 pe mobil) |
| 2026-10-01 | `d9b44e8` | Homepage: căsuțe mici pentru „Cele mai noi vouchere” / „Top”; magazine populare compacte |
| 2026-10-01 | `c583797` | Prospețimea întâi pe homepage; FAQ homepage + FAQPage; conținut unic per magazin (fără texte generale); `/cum-verificam-codurile`; „Folosit de X ori”; a 3-a culoare; `cupons:content` |
| 2026-10-01 | `27b748f` | Review 2: proxy-uri doar Cloudflare, domeniu fix, zero interogări pe cache, ?nocache doar local, căutarea scoasă din cache, og:image, UX card (al doilea click doar copiază) |
| 2026-10-01 | `68271e5` | Review 1: indexare magazine de la 1 ofertă, „Adăugat” vs „Verificat”, cache până la miezul nopții, 2FA, CSP, upload fără SVG, footer cu magazine |
| 2026-10-01 | `5455052` | Ierarhia de headings ca la cuponeria |
| 2026-10-01 | `3f4943e` | robots.txt deschis pe preview; brief pentru verificare externă |
| 2026-10-01 | `44f5155` | O singură temă (verde + portocaliu); pagini Magazine / Categorii refăcute; Top oferte cu 3 carduri mari |
| 2026-10-01 | `d580588`…`e56a3a4` | Variante de design (3 teme), portocaliu complementar |
| 2026-10-01 | `b5aedb1`, `b6c83c3` | Site-ul Laravel 12 + Filament 5: date, admin, import 2Performant, cache, SEO, README, DECIZII |
| 2026-09-30 | ramura `wordpress` | Varianta WordPress (temă + plugin), 4 faze; oprită în favoarea Laravel |
