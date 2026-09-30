# Checklist înainte de lansare

## WordPress
- [ ] Setări → General: titlu, descriere, fus orar **București**, limbă română
- [ ] Legături permanente: `/%postname%/`
- [ ] Utilizator admin cu parolă puternică; niciun utilizator „admin”
- [ ] `WP_DEBUG` = false pe producție
- [ ] `DISABLE_WP_CRON` = true + cron de sistem la 5 minute (README §3)
- [ ] `EMPTY_TRASH_DAYS` nu e suprascris (implicit 30)
- [ ] Comentariile dezactivate global (Setări → Discuții)
- [ ] Setări → Citire: „Descurajează motoarele de căutare” **debifat** la lansare

## Plugin
- [ ] Cupoane → Setări: praguri verificate; „Cloudflare” bifat dacă e cazul
- [ ] „Următoarea rulare” a curățării apare și e la 00:05
- [ ] `wp cupons cleanup --dry-run` rulează fără erori
- [ ] `/go/{id}/` face 302, are `X-Robots-Tag: noindex` și nu e cache-uit (verifică antetele de 2 ori)
- [ ] `/robots.txt` conține `Disallow: /go/`
- [ ] Votul: un al doilea vot pe același cupon e refuzat; rata apare doar de la pragul setat
- [ ] Nicio ofertă expirată vizibilă (magazin, categorie, etichetă, homepage, căutare)
- [ ] Toate linkurile externe au `rel="sponsored nofollow"`

## Conținut
- [ ] Magazinele au logo WebP, link de afiliere și categorii
- [ ] Ordinea „Magazine populare” setată
- [ ] Paginile statice completate (Despre, Metodologie, Transparență și afiliere, Contact, Termeni, Confidențialitate, Cookies…)
- [ ] Mențiunea de afiliere în footer
- [ ] Date demo șterse (`bash scripts/seed.sh reset` rulat doar local; pe producție nu există)

## SEO *(Faza 2)*
- [ ] Rank Math: sitemap activ, fără duplicare de schema
- [ ] Meta title/description conform template-urilor
- [ ] noindex: căutare, `/go/`, pagini cu < 3 oferte, paginări ≥ 2
- [ ] Un singur H1 pe pagină, canonical corect

## Performanță *(Faza 4)*
- [ ] Lighthouse mobil ≥ 95 (Performance, SEO, Accessibility) pe homepage și o pagină de magazin
- [ ] ≤ ~10 requesturi la prima încărcare; CSS < 25 KB; JS < 10 KB
- [ ] LiteSpeed Cache configurat; `/go/` și `/wp-json/cupons/` excluse
- [ ] Cloudflare: bypass pentru `/go/` și `/wp-json/`

## Securitate
- [ ] Cheile API doar în admin; `.env` / `wp-config.php` nu sunt în repo
- [ ] XML-RPC dezactivat
- [ ] Backup automat (cPanel / hosting) activ și testat
