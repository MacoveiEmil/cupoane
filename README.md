# {NUME_SITE} — vouchere, reduceri și cupoane ({DOMENIU})

Site WordPress de cupoane pentru magazinele online din România, monetizat prin afiliere (2Performant, Profitshare).
Prioritățile sunt **viteza** și **prospețimea**: nicio ofertă expirată pe site, pagini complet cacheabile.

- `wp-content/plugins/cupons-core/`: toată logica (date, `/go/`, voturi, curățare, import).
- `wp-content/themes/cupons-theme/`: doar afișare (Faza 2).
- `docs/DECIZII.md`: deciziile tehnice și motivele lor. `docs/CHECKLIST.md`: verificările înainte de lansare.

> README-ul se completează la fiecare fază. Secțiunile marcate *(Faza N)* urmează.

---

## 1. Dezvoltare locală (wp-env)

Cerințe: Docker Desktop pornit, Node 18+.

```bash
npm install
npm run env:start        # http://localhost:8888  (admin / password)
npm run seed             # date demo: 20 magazine, 60 cupoane, 10 deal-uri
npm run wp -- cupons cleanup --dry-run
```

- `bash scripts/seed.sh reset` șterge datele demo.
- `npm run env:stop` oprește containerele. `npm run env:reset` șterge tot mediul local.

## 2. Instalare pe cPanel (upload de zip-uri)

1. Pe calculatorul tău: `bash scripts/build-zip.sh` → `build/cupons-core.zip`, `build/cupons-theme.zip`.
2. WordPress → **Module → Adaugă → Încarcă modul** → `cupons-core.zip` → Activează.
3. **Aspect → Teme → Adaugă → Încarcă tema** → `cupons-theme.zip` → Activează. *(Faza 2)*
4. Instalează din directorul oficial: **Rank Math SEO** și **LiteSpeed Cache**.
5. **Setări → General**: titlul site-ului, fus orar **București**, format dată `j F Y`.
6. **Setări → Legături permanente**: „Nume articol” (`/%postname%/`) → Salvează.
7. **Cupoane → Setări**: pragul de reverificare, pragul de voturi, opțiunea Cloudflare.

Actualizare: încarci din nou zip-ul (WordPress oferă „Înlocuiește versiunea curentă”).

## 3. Cron de sistem (recomandat)

WP-Cron pornește doar la vizite, iar cu paginile servite din cache pornește rar. Curățarea zilnică
(00:05, ora României) trebuie să ruleze sigur, deci folosește cron-ul de sistem.

**a) În `wp-config.php`**, deasupra liniei `/* That's all, stop editing! */`:

```php
define('DISABLE_WP_CRON', true);
```

**b) cPanel → Cron Jobs → Add New Cron Job**, la fiecare 5 minute (`*/5 * * * *`), comanda:

```
cd /home/UTILIZATOR/public_html && /usr/local/bin/php wp-cron.php >/dev/null 2>&1
```

Înlocuiește `UTILIZATOR` și calea cu cele reale. Dacă hostingul are WP-CLI, merge și:
`wp --path=/home/UTILIZATOR/public_html cron event run --due-now --quiet`.

Verificare: **Cupoane → Setări** arată „Următoarea rulare” și rezultatul ultimei curățări.
Rulare manuală: butonul „Rulează curățarea acum” sau `wp cupons cleanup`.

## 4. Cum funcționează prospețimea

| Situație | Ce se întâmplă |
|---|---|
| Oferta are expirare și ziua a trecut | Dispare imediat din toate listările. La 00:05 e mutată în Trash (golit automat după 30 de zile). |
| Oferta nu are expirare și n-a fost verificată de 30 de zile (setabil) | La 00:05 trece în ciornă și apare la **Cupoane → De reverificat**. „Verificat azi” o republică. |
| Oferta expiră azi sau mâine | Paginile ei se regenerează, ca eticheta „Expiră mâine” să fie corectă. |
| După orice curățare sau editare | Purge LiteSpeed Cache pe magazin, categorii, etichete, homepage și indexuri. |

`EMPTY_TRASH_DAYS` are implicit valoarea 30 în WordPress. Nu îl suprascrie în `wp-config.php`.

## 5. Comenzi WP-CLI

```bash
wp cupons cleanup [--dry-run]   # curățarea zilnică, la cerere
wp cupons import                # (Faza 3)
```

## 6. LiteSpeed Cache și Cloudflare *(detalii complete în Faza 4)*

Esențial de la început:

- **LiteSpeed Cache → Cache → Excludes → Do Not Cache URIs**: adaugă `/go/` și `/wp-json/cupons/`.
  Pluginul trimite oricum antete `no-cache` pe aceste rute; excluderea e plasă de siguranță.
- **Cloudflare → Rules → Cache Rules**: *Bypass cache* pentru `URI Path starts with /go/` și `/wp-json/`.
- Dacă site-ul e în spatele Cloudflare: bifează **Cupoane → Setări → Cloudflare**.

## 7. Chei API

Cheile rețelelor de afiliere se introduc doar în admin (**Cupoane → Setări**), niciodată în cod sau în repo. *(Faza 3)*
