# GNOSIX 0.17.0 — Smart QR, Secure Access & Technician Portal

Release-ul extinde Laravel 13 / Blade / Vite. Reutilizează identitatea User/web verificată, capabilitățile și permisiunile pe proprietate, WorkOrder/Workflow, stocarea privată Evidence și auditul append-only. Administrarea platformei rămâne separată. Auditul inițial: [FIELD-017-AUDIT.md](FIELD-017-AUDIT.md).

## Utilizare

1. În organizație, deschide Mentenanță → QR Studio. Selectează proprietatea și tipul: clădire, nivel, spațiu sau activ. Sistemele tehnice sunt active cu indicatorul existent `is_system`, fără tabel paralel.
2. Generează codul; descarcă SVG/PNG sau selectează țintele din pagina curentă și deschide previzualizarea etichetelor GNOSIX. Tipărește prin dialogul browserului. Limita unei cereri bulk este 100; lista este paginată la 24 și are căutare server-side.
3. În Mentenanță → Acces tehnicieni, selectează proprietatea, ordinele și/sau un activ, expirarea și eventual firma/emailul destinatarului. Costurile sunt interzise implicit. Un scop pe activ permite ordinele acelui activ; documentele se partajează separat, pe versiuni explicite.
4. Copiază linkul afișat o singură dată și transmite-l destinatarului prin canalul ales de tine. Aplicația nu trimite automat invitația. Nu copia codul în ticketuri publice, analytics sau loguri de suport. Dacă l-ai pierdut, revocă invitația și creează alta.
5. Tehnicianul se autentifică/înregistrează prin identitatea existentă și își verifică emailul, apoi revine la linkul invitației, verifică organizația și acceptă. Nu are nevoie de abonament sau membership în organizație. Pentru o firmă de mentenanță creează invitații individuale angajaților; eticheta firmei nu este cont partajat.
6. `/field` listează numai ordinele atribuite membrului sau permise de invitațiile lui. Scanarea QR conduce către datele permise în acel moment; codul nu acordă drepturi. Un coordonator poate deschide portalul și din ordinul existent, pentru review.
7. Tehnicianul completează checklistul, lucrările, materialele, timpul și observațiile; încarcă fotografii/documente, salvează ciorna sau trimite cu declarația de finalizare. Opțiunea „Solicită fotografie la finalizare” se setează pe ordin. Formularul nativ `capture=environment` permite folosirea camerei pe browserele mobile compatibile.
8. Coordonatorul revizuiește raportul, îl poate returna cu motiv sau îl acceptă după review. Acceptarea verifică și închide ordinul. Corectarea produce o revizie nouă, fără rescrierea celei trimise. Seria/data instalării propuse rămân candidați Evidence pentru review distinct.

## Arhitectură și migrare

Migrarea aditivă `2026_11_17_100000_create_secure_field_service` creează:

- `qr_identities` și `qr_locators`: UUID intern permanent separat de locatorul public aleator de 192 biți. Rotirea/revocarea nu modifică identitatea țintei. Nu există ID de bază de date, token de acces sau date private în payload.
- `field_invitations`, `field_invitation_work`, `field_documents`: granturi cu scop, expirare, identitate verificată, email opțional de legare și versiuni explicit autorizate. Tokenul de 256 biți se stochează doar SHA-256; un cont îl poate răscumpăra idempotent, alt cont nu îl poate reutiliza.
- `service_reports` și `service_report_reviews`: snapshoturi și decizii append-only, cu număr de revizie/secvență unic, autor, timestamp, referințe UUID și surse PDF.
- Pe `work_orders`: `minutes_spent`, `observed_condition`, `require_photo`. Datele vechi sunt păstrate; nu este necesar un backfill.

Serviciile `app/Field/Access`, `Invitations`, `QrRegistry`, `Reports`, `Settings` centralizează regulile. `FieldController` oferă portalul și review-ul; `QrController` oferă generarea/exportul/etichetele. Modelele noi sunt în `app/Models`; rutele în `routes/field.php` și `field-admin.php`. Interfața reutilizabilă este în `resources/views/field`, `resources/css/field.css`, `resources/js/field.js`, cu mesaje `lang/ro/field.php` și `lang/en/field.php`.

Integrarea existentă modifică Engine, Maintenance Workflow/Documents/Retention, MaintenancePolicy, WorkOrder, CustomerIdentity, headerele/rate limiterele și navigația. `Engine::uploads`/`propose` acceptă contextul unui ordin numai după autorizare în scop; nu extind accesul general Evidence. Propunerile de teren sunt limitate la chei permise și activul ordinului.

Rapoartele PDF folosesc Evidence ca documente/versiuni private: originalul trimis rămâne sursă, acceptarea adaugă o versiune cu verificarea managerului și legătură în Asset Passport. Snapshotul păstrează identitatea, declarația, checksumurile atașamentelor și limba trimiterii. Checkboxul este acknowledgment, nu semnătură electronică calificată; verificarea managerului nu este verificare profesională independentă.

Dependențe Composer: `endroid/qr-code ~6.0.0` pentru encoder SVG/PNG și `dompdf/dompdf ^3.1.6` pentru PDF local. Nu sunt dependențe NPM noi. Encoderul este limitat la linia compatibilă cu PHP-ul proiectului. Dompdf nu primește HTML liber, URL-uri sau markup de la utilizator: Blade escapează conținutul, remote/PHP/JavaScript sunt dezactivate și chroot-ul este limitat la fonturi/cache.

## Securitate și retenție — review al implementării

- QR public: răspuns generic identic pentru cod cunoscut/necunoscut când vizitatorul este anonim. Fără nume, UUID intern, documente sau metadate de clădire. Destinația folosește URL-ul canonic salvat în administrare, nu Host-ul cererii.
- Token: fragmentul nu este trimis serverului în URL; formularul face preview/redeem prin POST cu CSRF. Codul este eliminat din fragment la preview, nu se salvează în flash/sessionStorage și este exclus din old input. Headere no-referrer/no-store/noindex, CSP same-origin, fără terți/analytics pe portal. Nu configura APM/proxy să înregistreze corpurile POST sau răspunsurile paginilor de invitație.
- Autorizare: verified User/web + sesiunea existentă, fără guard nou. Fiecare ordin, fișier, raport, mutație și review verifică scopul și proprietatea. Invitația nu acordă acces la Asset Passport general, note interne sau Evidence general. Semnătura temporară a unui fișier este legată de viewer și nu înlocuiește verificarea grantului curent.
- Compatibilitate cu rutele existente: un membru fără acces la proprietate, dar cu invitație de teren, poate folosi numai portalul restrâns. Rutele generale de mentenanță verifică separat dreptul de proprietate; grantul de teren nu poate extinde lista atașamentelor interne prin acele rute. Cazul este acoperit de un test cu membru real, invitație și link semnat valid.
- Retragere: expirarea, revocarea, pierderea drepturilor coordonatorului, anularea ordinului sau arhivarea clădirii blochează accesul. Ștergerea reversibilă păstrează identitatea și restaurarea QR; purjarea definitivă revocă invitațiile asociate înainte de detașarea structurii. Un grant cu mai multe ordine se revocă integral la purjarea uneia dintre clădirile lui.
- Documente: numai versiuni explicit partajate sau încărcate în scop; răspuns attachment/no-store/nosniff/sandbox, semnătură de 10 minute și verificare SHA-256. Sursele eliminate prin purjarea explicită devin indisponibile, UUID-ul sursei și snapshotul raportului rămân în istoric.
- Concurență: lock pe proprietate înaintea ordinului/grantului; tranzacții și revizii pentru status/raport/review; secvențe unice. Resursele PDF scrise înainte de un rollback sunt curățate, cu outbox existent pentru eșecul ștergerii. Migrarea nu dezactivează FK.
- Abuz: rate limiting pentru citire, preview/redeem și mutații; validare server, costuri refuzate fără permisiune și atașamente limitate de quota Evidence. Setarea duratei maxime este în DB → Admin → QR & Tehnicieni, cu recent administrator authentication, între 1 și 365 zile, implicit 90.
- Proveniență: observațiile sunt deterministe și se bazează pe istoricul autorizat, documentele partajate, declarațiile activului și conflictele Evidence. Nu produc diagnostic, fapte inventate sau confirmare automată. Nu există apeluri AI noi.

Acesta este review-ul regulilor și al rutelor modificate, susținut de teste de autorizare; nu reprezintă un audit extern sau certificare.

## Verificări și limite

Rezultat local final: **250 teste PHP / 2.235 aserțiuni**, inclusiv 20 teste dedicate Field; **10 scenarii JS** ale generatorului existente trecute. Build production, Pint/lint, cache rute/Blade și Composer audit au trecut. Composer audit: zero advisories și zero pachete abandonate la verificarea release-ului. Versiuni efectiv blocate: Endroid QR 6.0.9 și Dompdf 3.1.6. Pe Windows testele passkey au necesitat `OPENSSL_CONF=C:\php-8.4.14-nts\extras\ssl\openssl.cnf` pentru configurația instalării locale PHP; nu este o setare de produs sau o cerință nouă pe Forge.

Comenzi: `php vendor/bin/phpunit`, `node --test tests/building-generator.mjs`, `npm run build`, Pint pe fișierele PHP modificate, lint, `composer audit`, cache rute și Blade pe configurație izolată. Testele dedicate sunt în `tests/Feature/FieldServiceTest.php`: QR/anonimat/rotație/restaurare, invitații/replay/identitate/expirare/revocare, grant pe activ, cross-tenant/alte ordine, documente semnate și retrase, foto/costuri, ciorne/revizii/review/PDF/conflicte, concurență, purjare, setări admin și dataset de 10.000 active cu paginare/limită bulk.

Browser local cu DB SQLite izolată și identități sintetice: creare invitație și partajare manual, etichetă print preview, scan anonim, răscumpărare externă, formular mobil cu fotografie, ciornă/refresh, trimitere, review și acceptare manager, PDF descărcat, RO/EN, focus tastatură și lipsa overflow-ului la 390px. Fără erori în consola paginilor verificate. PDF-urile de test au fost randate și inspectate pe toate paginile, inclusiv diacritice și verificarea managerului.

Nu au fost executate deploy VPS, validare MariaDB/MySQL, SMTP/AI live, scanare cu cameră fizică sau tipărire pe imprimantă. Captura foto depinde de browser, HTTPS și permisiunea dispozitivului. Nu există aplicație nativă, PWA/offline cache, semnătură calificată, cont comun al firmei, SLA sau calendar avansat. PDF-ul se generează sincron, numai la submit/accept; monitorizează timpul pe VPS pentru texte/atașamente voluminoase. Instantaneul referă cel mult 100 atașamente, istoricul arată ultimele 20 intervenții autorizate; listele sunt paginate. Granturile devenite invalide sunt filtrate după interogarea paginii și pot lăsa pagini mai scurte; următoarea pagină rămâne disponibilă.

## Deploy și următorul pas

Pașii de Forge și smoke test sunt în [DEPLOY-FORGE.md](DEPLOY-FORGE.md). Nu rula `migrate:fresh` sau rollback pe date reale. Recomandare ulterioară: SLA/escaladări și calendar de intervenții, după smoke pe VPS și testare cu tehnicieni pe dispozitive reale.
