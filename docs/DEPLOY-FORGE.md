# Publicare GNOSIX pe Laravel Forge

Destinație: `https://gnosix.zion3d.ro` · Repository: `matrixn/gnosix-building-management` · Ramură: `main`.

## Prima instalare

1. Creează site Laravel cu PHP 8.4, document root `/public` și zero-downtime. Selectează repository-ul și ramura `main`.
2. Partajează `storage` între release-uri; `.env` este partajat implicit de Forge. Configurează o bază de date persistentă MySQL/PostgreSQL și valorile `DB_*`. Adminul, passkey-urile și setările SMTP necesită migrații; landing-ul nu mai este o aplicație fără bază de date.
3. Instalează Node 22.13+ sau 24 LTS și npm. Folosește `npm ci`, cu lockfile-ul din repository.
4. Configurează DNS către VPS, inclusiv eventualul AAAA, certificatul TLS și redirecționarea HTTP către HTTPS.
5. Pornește de la `.env.production.example`. Generează `APP_KEY` o singură dată și păstreaz-o între deploy-uri. Completează `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `PASSKEY_RP_ID` și `PASSKEY_ORIGIN` conform [instrucțiunilor admin](ADMIN.md). Contactul și indexarea sunt dezactivate implicit la instalare nouă; se configurează ulterior din administrare.
6. Lipește `deploy/forge-zero-downtime.sh` în editorul Deploy Script din Forge. Macro-urile sunt interpretate de Forge; nu executa fișierul ca script Bash independent.
7. Efectuează primul deploy. Scriptul instalează dependențele, compilează frontend-ul, rulează `migrate --force`, cache-uiește rutele/view-urile și verifică setările structurale de producție înainte de activare.
8. După inițializare rulează din Forge Commands:

```bash
php artisan gnosix:admin
```

9. Autentifică-te la `/admin/login`, salvează SMTP din **Setări → Email & SMTP** și testează livrarea către adresa administratorului. Adaugă passkey din **Securitate & passkeys**. Pașii de înregistrare, domeniul și recuperarea sunt în [ADMIN.md](ADMIN.md).
10. După verificarea livrării și a conținutului, deschide **Setări → GNOSIX · Site & contact**, verifică adresa canonică și destinatarul, apoi activează contactul și indexarea. Se aplică imediat. Verifică lansarea:

```bash
php artisan gnosix:check --launch
```

Deploy-ul folosește `gnosix:check --production`, care permite primul acces la admin înaintea configurării SMTP. `--launch` verifică suplimentar activarea pilotului și indexării. Niciuna dintre comenzi nu trimite email și nu dovedește livrarea în inbox.

`storage` și `bootstrap/cache` trebuie să fie inscriptibile de utilizatorul site-ului. Nu folosi `chmod 777`. Păstrează baza de date și `APP_KEY` în backup: fără cheia inițială, configurația SMTP criptată nu poate fi citită. Landing-ul trimite sincron; nu este necesar un worker sau scheduler. Dacă adaugi ulterior emailuri în queue, și acestea folosesc managerul SMTP centralizat.

## Migrarea setărilor existente

Migrațiile importă o singură dată vechile valori de produs din mediu în baza de date. Lasă-le disponibile până la primul deploy al acestei versiuni, apoi verifică-le în **GNOSIX · Site & contact**. Ulterior, administrarea este singura sursă pentru aceste opțiuni și variabilele vechi pot fi eliminate. Detalii în [SYSTEM-SETTINGS.md](SYSTEM-SETTINGS.md).

## SMTP și cereri pilot

Configurația salvată în admin are prioritate față de `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` și `MAIL_FROM_NAME`. Valorile din mediu servesc doar la inițializare dacă nu există încă o configurație salvată. `MAIL_MAILER` nu poate comuta sistemul pe log/array/sendmail: toate mailerele aplicației folosesc SMTP.

Folosește TLS implicit sau STARTTLS conform furnizorului. În producție TLS și verificarea certificatului sunt obligatorii. Configurează SPF, DKIM și DMARC după instrucțiunile furnizorului. Destinatarul contactului este configurat din tabul GNOSIX; contul SMTP și emailul administratorului pot fi diferite.

O eroare SMTP nu afișează succes; datele cererii pilot sunt păstrate temporar în sesiune pentru reîncercare. Nu există retry automat. Într-o situație în care acceptarea SMTP are loc înaintea unei erori de conexiune, o reîncercare manuală poate produce un duplicat.

## Identitate client Genesis

Deploy-ul migrează aditiv tabela `users`; nu este necesară o comandă nouă sau reinițializarea administratorilor. După configurarea SMTP, verifică `/app/register`, confirmarea emailului, onboardingul și resetarea parolei pe un cont propriu. Pagina de login este `/app/login`, separată de `/admin/login`. Vezi [GENESIS-001.md](GENESIS-001.md) pentru sesiuni, rute, limitări și verificări.

## Organizații Genesis 002

Migrarea creează organizațiile, apartenențele, profilele, opțiunile de capabilități, preferințele și ciornele. Nu recreează utilizatorii și nu cere provisioning suplimentar. Testează `/app/organizations`: creare Hospitality, refresh al ciornei, switching și acces cu un al doilea cont. Construction și Facility rămân indisponibile; dovezile și mentenanța rămân în dezvoltare; proprietățile și spațiile sunt operaționale din Genesis 003. Vezi [GENESIS-002.md](GENESIS-002.md).

## Workspace Studio Genesis 003

Migrarea `2026_11_16_120000_create_adaptive_workspace` adaugă structura de proprietăți, clădiri, etaje și spații, grupuri, entități juridice, granturi per proprietate și ciorne Studio. Completează organizațiile și apartenențele existente fără a le recrea; membrii existenți păstrează accesul `all`, iar rolul `manager` continuă să funcționeze. Nu este necesară o comandă de provisioning suplimentară.

Păstrează întregul `storage` partajat între release-uri. Logo-urile sunt în discul privat `local` (`storage/app/private/organization-logos`), se servesc autentificat și nu necesită `storage:link`. PHP trebuie să aibă extensiile uzuale Laravel, fileinfo și GD sau Imagick pentru imagini. Nu configura o rută publică către discul privat.

După deploy verifică `/app/organizations/{id}/studio`: ciornă, refresh, confirmare, listarea proprietăților și editarea unui spațiu. Verifică setările, un logo și accesul unui al doilea membru limitat la o singură proprietate. `gnosix:check --production` verifică și prezența structurii Studio. Nu rula rollback al acestei migrări pe date reale: ar șterge structura creată. Vezi [GENESIS-003.md](GENESIS-003.md).

## Building Studio Genesis 004

Migrarea `2026_11_16_160000_add_building_declarations` completează tabelele existente buildings/floors/spaces cu identități stabile și metadate/arhivare, apoi creează ciornele, reviziile/afirmațiile, paturile și unitățile de cazare. Datele necunoscute rămân NULL. Nu se recreează conturile, organizațiile sau structura Task 003.

Sunt folosite PHP/DB și frontend-ul existent, fără servicii sau pachete noi. Importul JSON acceptă maximum 2 MB de structură; permite minimum 3 MB pentru corpul cererii în Nginx/PHP. Pentru clădiri mari măsoară timpul pe motorul DB și VPS-ul real. Suita automată folosește SQLite, iar recuperarea migrației și salvarea declarației au fost verificate separat pe MySQL 8.4.13 local. Înregistrările sunt scrise în loturi.

După deploy deschide o proprietate și **Building Studio**, declară o clădire, verifică reluarea ciornei și dashboardul, apoi o revizie cu export/reimport. Verifică și un membru viewer/editor limitat la acea proprietate. `gnosix:check --production` verifică tabelele și coloanele declarațiilor. Fă backup înaintea migrării; nu executa rollback pe date reale, deoarece down elimină noile identități, istoricul, paturile și unitățile.

Nu există încă upload/extragere de documente, geometrii BIM sau rezervări PMS. Vezi [GENESIS-004.md](GENESIS-004.md).

## Recuperare eroare MySQL 1059 — release 0.9.1

Dacă deploy-ul s-a oprit în `2026_11_16_160000_add_building_declarations` cu indexul `building_declaration_drafts_property_id_user_id_building_id_index`, cauza este numele automat de 65 de caractere, peste limita MySQL de 64. Numele explicit nou este `bld_draft_property_user_building_idx`.

MySQL poate păstra modificările DDL anterioare erorii. Migrarea corectată detectează coloanele/tabelele deja create, păstrează identitățile și datele existente, completează indexurile/cheile externe lipsă și evită duplicarea reviziilor inițiale.

După actualizarea codului, rulează din nou **Deploy** în Forge. Scriptul existent execută `php artisan migrate --force` și reia migrarea neînregistrată ca finalizată. Nu modifica manual tabela `migrations`, nu șterge tabele și nu folosi `migrate:fresh` sau rollback pe baza reală. Păstrează backup-ul obișnuit înaintea deploy-ului.

Verificare locală MySQL 8.4.13: migrațiile până la Task 003, clădire/nivel/spațiu preexistente, eroarea originală 1059, două reluări ale corecției, păstrarea UUID-urilor și datelor, indexuri și chei externe complete, salvare declarație și retry idempotent, apoi `artisan migrate --force` cu succes. Aceasta nu confirmă executarea pe VPS.

Referințe: [limitele identificatorilor MySQL](https://dev.mysql.com/doc/refman/8.0/en/identifier-length.html), [DDL și commit implicit](https://dev.mysql.com/doc/refman/8.0/en/atomic-ddl.html).

## Building Studio unificat — 0.10.0

Nu adaugă migrații, variabile de mediu sau dependențe. Build-ul Vite și cache-ul rutelor trebuie regenerate prin deploy-ul existent. Verifică **Camere și spații → clădire → Studio**, generarea 5 × 10 camere cu suprafață/paturi/locuri, editarea unei camere și salvarea/reintrarea în clădire. Pentru spații vechi fără clădire, verifică asocierea explicită și accesul viewer/editor. Detalii în [BUILDING-STUDIO-WORKFLOWS.md](BUILDING-STUDIO-WORKFLOWS.md).

## Ștergerea clădirilor — 0.10.1

Deploy-ul aplică migrarea `2026_11_16_180000_add_building_soft_deletion`, care adaugă deleted_at nullable la buildings. Nu sunt setări sau dependențe noi. Build-ul și cache-ul rutelor se regenerează prin scriptul existent.

Verifică în Camere și Spații pe o clădire de test: Șterge → Anulează (clădirea rămâne), apoi Șterge → Da (clădirea dispare din liste și Studio). Viewerii nu trebuie să vadă butonul. Istoricul rămâne păstrat; nu rula rollback al migrării pe date reale, deoarece elimină marcajele ștergerilor. Din 0.11.0, restaurarea este disponibilă în Coșul de gunoi.

## Coș de gunoi și dialog — 0.11.0

Nu există migrații sau dependențe noi față de 0.10.1. Deploy-ul regenerează build-ul și cache-ul rutelor: ruta GET separată de confirmare a ștergerii a fost eliminată, iar confirmarea este un dialog în listă.

Verifică pe date de test **Camere și spații → Șterge → Anulează/Escape**, apoi confirmă ștergerea, deschide **Coș de gunoi**, restaurează și verifică structura în Studio. Restaurarea funcționează și cu ștergerile existente care au istoricul creat de 0.10.1. Ștergerea obișnuită păstrează datele; din 0.12.0 există și ștergere definitivă individuală, prin dialog. Nu există golire în masă a coșului. Viewerii nu pot restaura. Nu modifica sau șterge reviziile: restaurarea folosește structura ultimei ștergeri.

## Ștergere definitivă și proprietăți vizuale — 0.12.0

Nu sunt migrații sau dependențe noi. Deploy-ul obișnuit regenerează build-ul și rutele; cele nouă assets SVG sunt livrate în public/illustrations/properties și nu necesită servicii sau permisiuni speciale.

Verifică pagina Proprietăți: imagine distinctă după tip, click pe card, totaluri și mobil. Testează dialogul de ștergere definitivă numai pe o clădire de test din coș: Anulează/Escape păstrează clădirea, confirmarea o elimină împreună cu structura/ciornele/istoricul. După confirmare nu mai poate fi restaurată din aplicație. Verifică și accesul unui viewer; acesta nu poate purja. Politicile și backup-urile bazei rămân cele ale infrastructurii Forge.

## Verificare după deploy

- `/` și `/confidentialitate`: conținut, linkuri, fonturi și formular.
- `/robots.txt`, `/sitemap.xml`: domeniul final și indexare permisă după lansare.
- `/up`: HTTP 200, utilizabil ca health check Forge.
- `/admin/login`: parola funcționează, logoutul închide accesul, paginile au `noindex`.
- Admin SMTP: mesaj de test primit în căsuța administratorului, inclusiv verificarea Spam.
- Passkey: înregistrare și login pe domeniul final, cu un dispozitiv real și o cheie de rezervă.
- Formular pilot: verifică primirea efectivă în Gmail și funcționarea Reply-To.
- Mobil: meniul public, formularul și setările admin.

Înaintea colectării datelor reale, confirmă că informarea de confidențialitate corespunde furnizorilor și practicilor efective de operare.

## Revenire

Folosește mecanismul Forge de revenire la un release anterior. `.env`, `storage` și baza de date sunt partajate; revenirea codului nu le revine automat. Migrația admin adaugă tabele și nu modifică utilizatorii existenți. Nu rula automat `migrate:rollback` la revenire: ar șterge conturile platformei, passkey-urile și setările. Poți opri formularul public direct din tabul GNOSIX, fără modificarea mediului sau a cache-ului de configurare.

Referințe: [Forge deployments](https://laravel.com/forge/docs/sites/deployments), [Laravel deployment](https://laravel.com/framework/docs/13.x/deployment), [Laravel mail](https://laravel.com/framework/docs/13.x/mail).


## Evidence Engine — 0.13.0

Deploy-ul aplică migrarea aditivă `2026_11_16_200000_create_evidence_engine`. Nu rula rollback sau migrate:fresh pe date reale. Păstrează backup pentru baza de date și storage privat, împreună. Nu sunt chei noi de produs în `.env`.

1. Folosește Node 22.13+ sau 24 LTS. `npm ci && npm run build` copiază assets oficiale PDF.js în `public/vendor/pdfjs`, apoi compilează preview-ul lazy. `storage` trebuie partajat între release-uri; originalele sunt în `storage/app/private/evidence`, niciodată în public/storage. Păstrează APP_KEY pentru setările criptate.
2. În Forge creează un worker/daemon separat, directorul aplicației curente, cu comanda:

```bash
php artisan queue:work evidence --queue=evidence --sleep=3 --tries=3 --timeout=120
```

Conexiunea `evidence` folosește tabela jobs și retry_after 180 secunde. Timeout HTTP este 90 secunde, timeout job 120. Nu folosi conexiunea sync sau un worker cu timeout implicit 60. Permite oprirea grațioasă minimum 130 secunde. Scriptul de deploy existent folosește `$RESTART_QUEUES()` după activare; verifică repornirea acestui worker la fiecare release.

3. Configurează schedulerul Laravel în Forge la fiecare minut (`php artisan schedule:run`). Acesta rulează cleanup-ul fișierelor originale după purjare. Poți verifica manual:

```bash
php artisan gnosix:evidence-cleanup
php artisan queue:failed
```

Purjarea din coș elimină metadatele într-o tranzacție și programează ștergerea fizică. Fără scheduler, fișierele rămân private pe disc până la cleanup; backup-urile au politica proprie de retenție. Nu goli direct tabelele sau storage-ul.

4. Configurează PHP `upload_max_filesize` minimum cât limita per fișier din admin, `post_max_size` și Nginx `client_max_body_size` peste totalul lotului (max. 5 fișiere; implicit 8 MB fiecare). Exemplu pentru limitele implicite: upload_max_filesize=8M, post_max_size=48M, client_max_body_size=48m. Pentru limita admin de 20 MB, folosește un plafon total peste 100 MB sau micșorează setarea. Setează o memorie PHP potrivită procesării PDF/imaginilor și monitorizează coada/cota discului.
5. Admin → Setări → Evidence & AI: furnizor OpenAI, model care acceptă Responses + imagini/PDF + structured outputs, cheie, limite și activare. Câmpul model nu are o valoare presupusă. Cheia nu este afișată în pagină sau inclusă în payload-ul jobului. Nu activa înainte de verificarea condițiilor furnizorului, informării utilizatorilor și permisiunii de a transmite documentele.
6. Smoke test pe o clădire de test: upload, v2, descărcare privată, introducere manuală, confirmare/conflict/istoric. Solicită AI numai bifând informarea pentru versiunea selectată; verifică workerul și rezultatul propus, apoi fă review uman. Nicio cerere AI reală sau modificare pe VPS nu a fost făcută în verificările locale.

`store:false` evită stocarea răspunsului pentru recuperare ulterioară; nu garantează Zero Data Retention la furnizor. Vezi [date OpenAI](https://developers.openai.com/api/docs/guides/your-data) și documentația [Genesis 005](GENESIS-005.md).


## Furnizori alternativi — 0.14.0

Fără migrații sau dependențe noi. Configurația OpenAI existentă se păstrează. După deploy, din Setări → Evidence Engine & AI poți alege Gemini/OpenRouter/Ollama și introduce modelul/cheia sursei respective. Workerul evidence și schedulerul existent rămân necesare, iar workerii trebuie reporniți pentru noul binding. Pentru Ollama este necesară instalarea serviciului local separat și dezactivarea cloud; aplicația nu face instalări pe VPS. PDF-urile merg prin Gemini/OpenAI, iar OpenRouter/Ollama acceptă în această integrare PNG/JPEG. Configurarea gratuită și limitele sunt în [AI-PROVIDERS.md](AI-PROVIDERS.md). Nu au fost făcute apeluri AI live sau modificări pe VPS în verificarea locală.


## Smart Asset Registry — 0.15.0

Deploy-ul existent aplică migrarea aditivă `2026_11_16_220000_create_smart_asset_registry`, apoi regenerează build-ul și cache-urile. Nu sunt dependențe sau chei .env noi. Păstrează backup pentru DB și storage privat înainte de deploy; nu rula rollback sau migrate:fresh pe date reale.

Fotografiile folosesc discul privat Evidence în subdirectorul assets; storage rămâne partajat între release-uri, fără link public. Limitele sunt 3 × 5 MB/încărcare și 20 originale/activ. Schedulerul/workerul Evidence existent rămân necesare pentru funcțiile lor; registrul și importul din afirmații existente nu necesită un worker nou și nu trimit automat date către AI.

Smoke test pe o proprietate de test: creează un activ în cameră, editează pașaportul, încarcă o fotografie, leagă o afirmație/document, verifică justificarea și înlocuirea explicită la diferențe, importă un candidat, arhivează/restaurează, apoi click pe cameră în Building Studio. Verifică și un viewer fără drept de editare. Performanța de 10.000 active a fost testată pe SQLite local; monitorizează interogările și discul pe configurația VPS reală. Nu s-a făcut deploy sau test pe DB-ul de producție în această livrare.

La purjarea unei clădiri, activele fizice sunt arhivate la proprietate cu identitatea păstrată; sursele eliminate devin indisponibile, iar aprobările dependente sunt retrase. Detalii și fișiere: [SMART-ASSET-REGISTRY.md](SMART-ASSET-REGISTRY.md).


## Maintenance Intelligence — 0.16.0

Deploy-ul standard aplică migrarea aditivă 2026_11_17_000000_create_maintenance_workflows și rebuild/cache. Nu există dependențe Composer/NPM sau chei .env noi. Păstrează backup DB + storage privat și nu rula migrate:fresh sau rollback pe date reale. Storage Evidence rămâne privat și partajat între release-uri.

Adaugă în Forge un worker separat:

```bash
php artisan queue:work database --queue=maintenance --sleep=3 --tries=3 --timeout=60
```

Conexiunea database existentă are retry_after 90, mai mare decât timeout-ul jobului/workerului 60. Păstrează workerul evidence separat și repornește ambii workeri la deploy. Schedulerul Laravel trebuie să ruleze în fiecare minut; comanda orară pune generarea în coada maintenance. Verificare manuală:

```bash
php artisan gnosix:maintenance-generate
php artisan queue:failed
php artisan schedule:list
```

Fără worker nu se generează ordine programate și nu se trimit notificările de mentenanță. Raportarea/ordinele manuale funcționează fără AI și fără worker. SMTP se configurează numai în administrarea existentă. Testează un destinatar autorizat cu preferința planului activată; retry-ul SMTP poate produce notificări repetate, dar cheia plan/dată nu produce ordine duplicate.

Smoke după deploy pe proprietate de test: rol Raportor → sesizare cu foto; coordonator → ordin alocat unui Tehnician; tehnician → lucrări/checklist; coordonator → verificare/închidere; atașament și candidat Evidence rămas proposed; plan scadent → command/worker, apoi repetarea comenzii fără ordin duplicat. Verifică și un utilizator cu grant pe altă proprietate, filtrarea portofoliului și notele private. În Admin → Mentenanță se configurează pragul/fereastra observațiilor. Rolurile vechi își păstrează drepturile; pentru personal cu documente restricționate alege rolurile noi explicit.

Nu s-a făcut deploy sau validare MariaDB/MySQL/SMTP reală în această livrare locală. Detalii și limite: [MAINTENANCE-016.md](MAINTENANCE-016.md).
