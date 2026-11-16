# Genesis 005 · Evidence Engine — 0.13.0

## Flux operațional

Din Building Studio/dashboard sau navigarea **Memoria clădirii**, alege o clădire autorizată. Încarcă PDF, PNG sau JPEG, declară tipul documentului și, opțional, selectează documentul existent pentru versiunea următoare. Același hash în aceeași clădire reutilizează originalul, nu consumă o versiune nouă. Versiunile vechi nu sunt rescrise.

Fără furnizor configurat, memoria funcționează prin introducere manuală. Selectează cheia semantică, subiectul, valoarea tipizată și versiunea sursă; citează pagina/textul cunoscut. O afirmație despre echipament cere un identificator explicit (de exemplu CT-01); una despre spațiu cere un spațiu al clădirii. Necunoscut înseamnă lipsă, nu o valoare inventată. Introducerea manuală nu primește o încredere AI.

Cu un furnizor activ, o cerere explicită și consimțământul pentru versiunea respectivă trimit originalul către extracție în coadă. Stările sunt queued/processing/retrying/succeeded/failed, fără procente inventate. Toate rezultatele sunt propuneri. Classification reprezintă estimarea furnizorului; tipul documentului declarat rămâne separat. Review-ul uman confirmă, respinge, marchează pentru investigare, corectează printr-o nouă propunere sau înlocuiește cu justificare valorile în conflict. Confirmarea simplă nu poate ocoli un conflict. Selecția în lot acceptă maximum 20 de propuneri fără conflicte într-o tranzacție.

Studioul leagă afirmațiile de versiunea exactă și pagina sursă; dacă există coordonate normalizate valide, evidențiază zona. Sursele istorice pot fi deschise chiar dacă lista este paginată. Corectarea și înlocuirea păstrează valorile originale, reviewerul, justificarea, timestampul și legătura către înlocuitor. Setările limbii păstrează filtrul/versiunea selectată. Mobilul stivuiește panourile; tastatura folosește controale native, etichete, focus și stări explicite; motion redus este respectat.

Inventarul, stările de review, conflictele și cronologia reprezintă memoria documentară. Indicatorul de acoperire numără prezența celor șapte tipuri declarate de documente, nu evaluează validitatea, exhaustivitatea sau siguranța clădirii. Confirmarea unui utilizator nu este verificare independentă profesională sau certificare.

## Arhitectură și fișiere

Nu se introduce Vue, alt guard sau alt sistem de permisiuni: Laravel 13/PHP 8.4 + Blade/Vite reutilizează User/web, PropertyPolicy, PropertyPermissions și CapabilityService. Hospitality activează capabilitățile evidence.upload/review/conflicts din registrul central; viitoarele industrii rămân dezactivate. Vizualizarea cere upload SAU review și acces la proprietate; mutațiile cer drept de editare și capabilitatea specifică. Un viewer nu devine reviewer.

- `database/migrations/2026_11_16_200000_create_evidence_engine.php`: sources, documents, versions, extractions, assertions, references, reviews, conflicts, audits și outbox evidence_file_deletions. UUID pentru document/versiune/extracție/afirmație, chei externe restrictive și indexuri explicite scurte.
- `app/Models/Evidence/*`: relații/casts și protecție împotriva actualizării/ștergerii obișnuite a originalelor, afirmațiilor/provenienței și evenimentelor istorice. Starea review-ului se schimbă controlat prin Engine; classification pe versiune este metadată de procesare, nu modificare a originalului.
- `app/Evidence/Engine.php`: autorizare, upload/versionare/deduplicare, tranzacții și lock Property → Building, propuneri/review/audit, conflicte și cereri de extracție după commit.
- `app/Evidence/Facts.php`, `config/evidence.php`: chei și tipuri permise, subiecte, date/numere/boolean, text fără markup/control characters și regiuni valide. Valorile extrase nu modifică Building sau reviziile declarațiilor manuale.
- `app/Evidence/Extractor.php`, `OpenAiExtractor.php`, `app/Jobs/ExtractEvidence.php`: contract independent de furnizor; adapter OpenAI Responses, strict JSON schema, fără tools/URL-uri din documente, output limitat, maximum 100 de afirmații/extracție. Jobul serializează numai ID-ul cererii, revalidează accesul/configurația/hash-ul și aplică rezultatul atomic. Repetarea succesului nu dublează afirmațiile. Retry 30/120 secunde, maximum trei încercări, timeout HTTP 90/job 120/retry_after 180.
- `app/Evidence/Settings.php`, admin SettingsController/view/routes: setări PlatformSetting criptate, gardă platform_admin și autentificare recentă; cheia nu este randată sau flash-uită. Configurarea nu face cereri AI automat.
- `app/Evidence/Retention.php`, `BuildingDeclarationStudio.php`, `routes/console.php`: integrare cu coș/restaurare/purjare și cleanup idempotent al fișierelor prin scheduler.
- `app/Http/Controllers/Identity/EvidenceController.php`, `routes/evidence.php`, integrarea în routes/organizations.php: rute tenant-scoped, validare server, CSRF, throttling și fișiere semnate cu expirare de zece minute, legate de utilizator.
- `resources/views/evidence/*`, `resources/js/evidence.js`, `resources/css/evidence.css`, `lang/{ro,en}/evidence.php`: studio/dashboard, PDF/image preview, review și traduceri. Integrarea layoutului/navigation/building links este în views/layouts/identity și views/buildings. Politica de confidențialitate RO/EN include notificarea despre documente și procesarea externă.
- `config/filesystems.php`, `config/queue.php`, `AppServiceProvider.php`, `bootstrap/app.php`: disc privat, coadă dedicată, binding adapter și protejarea API key la validare.
- `tests/Feature/EvidenceEngineTest.php`, `EvidenceQueueTest.php`, adaptarea OrganizationProfilingTest: teste noi și regresii; auditul inițial este în GENESIS-005-AUDIT.md.

## Integritate, securitate și retenție

Originalele sunt stocate ca binare private în `storage/app/private/evidence/<organization>/<building UUID>/<version UUID>/original.bin`, cu hash SHA-256 verificat la descărcare și înainte de extracție. Nu există storage link către acestea. URL-ul semnat nu înlocuiește autentificarea/grantul/capabilitatea, este legat de utilizator și nu mai funcționează după ștergerea logică a clădirii. Răspunsul este attachment, private/no-store, nosniff și CSP sandbox.

Se validează dimensiunea, MIME, semnătura și imaginea, cu plafon de pixeli; PDF cere header/EOF și respinge tokenuri active/criptare cunoscute. HTML/SVG/scripturi sunt refuzate. PDF.js desenează în canvas, fără execuție eval/XFA și fără layer de acțiuni/linkuri/annotation. Documentele sunt date neîncrezute, niciodată instrucțiuni sau cod. Adapterul nu folosește tools sau rețeaua indicată de document. Outputul AI este neîncrezut, validat și supus review-ului. Aceste măsuri reduc riscul de prompt injection; nu demonstrează imunitate semantică a modelului.

Limite: implicit 5 fișiere × 8 MB, 512 MB/clădire; administratorul ajustează limitele. Plafoane tehnice: 500 versiuni, 10.000 afirmații și 2.000 conflicte deschise/clădire; 300 de pagini accesibile în preview; maximum 100 afirmații/job. Consultă DEPLOY-FORGE pentru limitele PHP/Nginx și worker/scheduler.

Arhivarea documentului păstrează sursele și blochează extracțiile noi. Ștergerea logică/restaurarea clădirii păstrează toate metadatele și originalele. Numai purjarea autorizată a unei clădiri deja în coș șterge metadatele, în aceeași tranzacție cu un outbox de fișiere. Schedulerul șterge fizic în loturi de maximum 500; eșecurile se reiau și nu expun documentele. Backup-urile și obligațiile proprii de retenție sunt responsabilitatea operatorului. Nu se aplică o ștergere automată bazată pe o durată presupusă.

## Dependențe și surse

Singura dependență runtime nouă este `pdfjs-dist` 6.5.438 (Apache-2.0; Node >=22.13 sau >=24). Parserul și workerul se încarcă separat, numai la un PDF, fără CDN. `scripts/sync-pdf-assets.mjs` copiază fonturi/cmaps/wasm/iccs și LICENSE în public/vendor/pdfjs. Nu există dependențe Composer noi; HTTP, queue și validarea sunt Laravel native. Nicio bibliotecă de animație/AI nu este instalată decorativ.

Referințe: [Mozilla PDF.js](https://github.com/mozilla/pdf.js), [input PDF OpenAI](https://developers.openai.com/api/docs/guides/file-inputs), [structured outputs](https://developers.openai.com/api/docs/guides/structured-outputs), [politici date](https://developers.openai.com/api/docs/guides/your-data). `store:false` nu este sinonim cu Zero Data Retention. Configurarea reală necesită un model compatibil și condiții adecvate pentru documentele transmise.

## Verificări și limite

Rezultat final local: **181 teste PHP / 1670 aserțiuni, toate trecute**, 10 verificări ale generatorului Building Studio trecute, build production reușit, audit NPM runtime fără vulnerabilități raportate și cache-uri Laravel generate cu succes. Cele 18 teste noi Evidence au 112 aserțiuni.

Comenzi: `php vendor/bin/phpunit`, `node --test tests/building-generator.mjs`, `npm run build`, `npm audit --omit=dev`, Pint pe fișierele PHP modificate, `php artisan route:cache` și `php artisan view:cache` cu cache-uri locale izolate. Pe Windows, suita folosește OPENSSL_CONF=C:\php-8.4.14-nts\extras\ssl\openssl.cnf. Nu se rulează migrate:fresh pe baza utilizatorului.

Testele acoperă upload HTTP și service, v2/deduplicare, invalid MIME/active PDF, semnături expirate și legate de utilizator, cross-tenant/viewer/capabilități, afirmații manuale și spații, provider absent/revocat, extracție eșuată/retry/idempotence, rollback output invalid, integritate, conflicte/supersession/corecție, review atomic în lot, criptarea setărilor și coș/restaurare/purjare/cleanup. Testul de worker folosește conexiunea database reală cu extractor de test și interzice HTTP; contractul OpenAI folosește Http::fake. Falsurile nu sunt în codul de producție.

Browser local cu bază izolată: PDF real cu două pagini, highlight al citatului, încărcare v2, introducere manuală, conflict și înlocuire justificată, sursa istorică, review în lot, RO/EN și mobil 390 px fără overflow orizontal; fără erori/warnings în consolă. Cererile preview sunt AJAX, iar mutațiile redirecționează explicit în memorie, ca descărcarea să nu devină destinația după review.

Nu s-au folosit chei sau cereri AI live și nu s-a făcut deploy pe VPS. Extracția reală trebuie validată pe modelul configurat și documente reprezentative înainte de activare. Nu există OCR local, scanare antivirus dedicată sau verificare profesională independentă. Asocierea echipamentului este un identificator explicit în afirmație; nu este încă un registru complet de active. AI nu asociază automat spații din planuri; afirmațiile despre spații se introduc manual. Un document dificil sau peste limita de output poate eșua și cere review/manual sau o versiune mai mică, fără succes simulat.

Următorul milestone recomandat: registru de echipamente și sisteme cu identități stabile, benchmark de extracție/OCR pe un corpus controlat, scanare antivirus și workflow de verificare profesională independentă, înainte de automatizarea mentenanței.


Extensie 0.14.0: adapterele Gemini/OpenRouter/Ollama și setările per furnizor sunt documentate în [AI-PROVIDERS.md](AI-PROVIDERS.md). Paragrafele de mai sus descriu livrarea inițială 0.13.0.


Extensie 0.15.0: registrul real de active, asocierea manuală a surselor și stratul distinct de aprobări sunt descrise în [SMART-ASSET-REGISTRY.md](SMART-ASSET-REGISTRY.md). Nu se mai aplică limitarea inițială privind lipsa registrului de echipamente.
