# Setările GNOSIX se administrează din interfață

Acces: **Admin → Setări platformă → GNOSIX · Site & contact** (`/admin/settings?tab=gnosix`). Nu este necesară editarea `.env`, refacerea cache-ului de configurare sau publicarea unui release pentru modificarea acestor opțiuni.

| Opțiune în administrare | Efect |
| --- | --- |
| Adresa publică canonică | Baza URL pentru canonical, hreflang, sitemap, Open Graph/Twitter și datele structurate, în ambele limbi |
| Email pentru primirea mesajelor de contact | Destinatarul mesajelor formularului RO/EN |
| Activează formularul de contact | Permite sau oprește trimiterea, cu condiția ca SMTP să fie configurat |
| Permite indexarea în motoarele de căutare | Controlează robots.txt, meta robots și X-Robots-Tag pentru paginile publice |

SMTP se configurează separat în **Email & SMTP**. Administratorii platformei sunt singurii care pot modifica setările globale. La salvare este necesară o autentificare/reconfirmare din ultimele zece minute. Zona admin rămâne exclusă de la indexare chiar dacă site-ul public este indexabil.

Adresa canonică trebuie să fie o adresă de bază, fără `/en`, cale, parametri, fragment sau credențiale. În producție este obligatoriu HTTPS. Câmpul schimbă adresele publicate în metadatele SEO; nu modifică DNS, certificatul, domeniul Forge, `APP_URL` sau domeniul passkeys.

## Instalare nouă

Migrațiile creează în baza de date configurația inițială. Contactul și indexarea sunt dezactivate implicit; adresa canonică inițială este `https://gnosix.zion3d.ro`, iar destinatarul inițial este `raduta.alex@gmail.com`.

După crearea administratorului, verifică și salvează valorile din tabul GNOSIX. Configurează SMTP, apoi activează formularul și indexarea când site-ul este pregătit. `php artisan gnosix:check --launch` verifică valorile din baza de date, fără să trimită email.

## Migrare pentru o instalare existentă

Scriptul Forge include deja `php artisan migrate --force`. Migrația `2026_11_15_120000_move_gnosix_settings_to_administration` importă o singură dată:

- `GNOSIX_CANONICAL_URL` → adresa canonică.
- `GNOSIX_INDEXABLE` → indexare publică.
- `GNOSIX_CONTACT_ENABLED` → activarea formularului.
- `GNOSIX_CONTACT_RECIPIENT` → destinatar contact.
- Valorile mai vechi `GNOSIX_PILOT_ENABLED` și `GNOSIX_PILOT_RECIPIENT` sunt acceptate doar ca fallback de import.

Importul citește mediul procesului și vechiul `.env` inclusiv când Laravel are configurația cache-uită. Lasă valorile vechi disponibile până la executarea migrării, apoi verifică rezultatul în admin. Valorile invalide sunt înlocuite cu valorile inițiale sigure. Dacă există deja o configurație GNOSIX în baza de date, aceasta este păstrată.

Pentru o instalare cu administratori existenți, în lipsa unui flag vechi de activare, este păstrat comportamentul anterior de activare implicită a contactului; trimiterea rămâne condiționată de SMTP. Indexarea rămâne oprită dacă nu exista o valoare veche explicită.

După import, variabilele vechi nu mai sunt citite de aplicație și pot fi eliminate din Forge Environment. Schimbarea lor nu modifică opțiunile din admin. Rerularea migrării sau un nou deploy nu resetează opțiunile salvate. Revenirea acestei migrări păstrează configurația, pentru a nu șterge modificările administratorului.

## Persistență și configurare viitoare

Valorile sunt păstrate în înregistrarea `gnosix` din `platform_settings`, criptată cu cheia aplicației și asociată administratorului care a salvat-o ultima dată. Datele sunt citite din baza de date, nu din configurația cache-uită; o cerere nouă vede noile valori. Păstrează backup pentru baza de date și `APP_KEY`.

Convenția proiectului este în `AGENTS.md`: noile setări de produs se adaugă în administrare, cu validare și persistență în baza de date. `.env` rămâne pentru infrastructură și inițializare: conexiunea la baza de date, cheia aplicației, mediul de execuție, URL-ul infrastructurii, originea passkeys și credențialele inițiale ale administratorului. SMTP are deja propria interfață de configurare.

Au trecut 56 de teste / 354 de aserțiuni, inclusiv autorizare, reconfirmare, validarea valorilor, import idempotent, respectarea setărilor din baza de date în locul configurației vechi, aplicarea pe contact și SEO și regresiile RO/EN/admin/passkeys. În browser au fost verificate salvarea cu configurația Laravel cache-uită, modificarea efectivă a robots/canonical/contact și pagina la 390 px. Nu au fost schimbate setări pe VPS.
