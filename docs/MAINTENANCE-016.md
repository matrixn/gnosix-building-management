# GNOSIX 0.16.0 — Maintenance Intelligence & Work Orders

## Utilizare și decizii de arhitectură

Stackul Laravel 13 / Blade / Vite rămâne neschimbat. Nu există pachete Composer/NPM noi. Smart Asset Registry și Evidence Engine sunt reutilizate.

Din pașaportul activului, dashboard-ul clădirii, inspectorul camerei sau Centrul de mentenanță se poate raporta o sesizare. Localizarea cunoscută este precompletată; titlul și observația sunt suficiente pentru o raportare scurtă, cu categorie/severitate/impact și ciornă sau trimitere. Simptomele nu sunt diagnoze. Contextul arată sesizări active similare și, în fișa sesizării, alte rapoarte recente. Datele necunoscute rămân necunoscute. Ciornele sunt vizibile autorului și coordonatorilor proprietății.

Sesizarea și ordinul sunt entități separate. Un ordin poate asocia maximum 30 sesizări trimise din aceeași proprietate/clădire; o sesizare poate avea mai multe ordine. Ordinele se pot crea și independent, inclusiv pentru sisteme comune. Nu există restricții după tipul proprietății. Un activ poate deservi spații în clădiri diferite ale aceleiași proprietăți, conform relațiilor existente din Asset Registry.

Coordonatorul atribuie un responsabil nominal sau o echipă declarată, prioritate și termen în fusul proprietății. Tehnicianul înregistrează lucrările, checklist-ul, materialele/componentele, cantitățile și costurile cunoscute. Costul unei linii de material este totalul liniei, nu preț unitar. Referința AST/UUID a unui activ înlocuit poate fi asociată explicit și este verificată în proprietatea autorizată. Nu se modifică automat activul inițial.

Flux: reported, triaged, assigned, in_progress, awaiting_parts / external_service / blocked, resolved, verified, closed. Pașii operaționali pot fi schimbați fără o succesiune rigidă, cu motiv și revizie la fiecare tranziție. Alocarea cere responsabil sau echipă; rezolvarea cere lucrări și checklist complet. Doar coordonatorul verifică un ordin rezolvat și închide un ordin verificat, cu autor/timestamps păstrate. Redeschiderea revocă verificarea curentă; vechea verificare rămâne în jurnal. Editarea unei intervenții rezolvate cere o nouă rezolvare. Ordinele verificate/închise/anulate cer redeschidere înainte de editarea execuției. Sesizarea nu se poate închide cât timp are intervenții neterminate; se poate redeschide ulterior.

## Autorizare și confidențialitate

Se reutilizează membership și PropertyPermissions. Owner/admin/manager și editorii existenți coordonează doar proprietățile editabile. Membrii pot raporta în proprietățile vizibile; responsabilul nominal poate executa propriul ordin, fără drept implicit de verificare. Rolurile noi **Raportor** și **Tehnician** se aleg în pagina organizației, fără cont/guard separat. Raportorul raportează și poate atașa dovezi la propriile sesizări; nu execută ordine chiar dacă este selectat accidental ca responsabil. Tehnicianul poate executa ordinele atribuite nominal.

Matricea centrală din config/profiles.php refuză rolurilor noi capabilitățile Evidence și mutațiile Asset Registry; grantul editor nu transformă aceste roluri în manageri. Apartenența nu se acordă prin atribuirea unui ordin. Rolurile istorice păstrează drepturile existente. Pentru personal cu documente restricționate se folosesc explicit rolurile noi, în loc de a interpreta rolul vechi member ca tehnician.

Notele private sunt vizibile exclusiv autorului și coordonatorilor proprietății. Evenimentul din jurnal nu include conținutul notei. Sumele agregate sunt afișate doar pentru proprietățile editabile; responsabilul nominal vede costurile intervenției sale. Toate cererile și lookup-urile reevaluează organizația, granturile și capabilitățile. CSRF/throttling și tranzacțiile existente sunt păstrate. Nu există endpoint de ștergere a istoricului operațional.

## Evidence și audit existente

Atașamentele sunt **versiuni Evidence existente**, prin Engine::uploads: PDF/PNG/JPEG validate, limitele și cota clădirii din administrare, hash și stocare privată. Un upload de mentenanță folosește autoritatea operațională fără a acorda acces la celelalte versiuni ale clădirii. Sunt necesare o clădire și dreptul de a atașa la înregistrarea respectivă; sesizările doar la nivel de proprietate funcționează fără atașament până se selectează clădirea. Nu se creează un al doilea depozit de fișiere.

Descărcarea explicită a atașamentului cere semnătură relativă de 10 minute, același utilizator, acces curent la sesizare/ordin, versiune disponibilă și SHA-256 corect. Răspunsul este attachment/no-store/nosniff/sandbox. Asocierea unei versiuni tehnice existente cere și acces Evidence; aceasta o face disponibilă echipei operaționale care vede înregistrarea, deci trebuie selectate doar documentele adecvate acestei echipe. Upload-ul și versiunea rămân în Evidence chiar dacă asocierea ulterioară eșuează; un coordonator o poate asocia din nou. Nu se sacrifică originalul valid pentru a simula succesul întregului formular.

Pașaportul, garanția declarată, aprobările valide, faptele lipsă/modificate, intervențiile anterioare și conflictele sunt accesibile contextual. Informațiile/sursele documentare generale rămân condiționate de capabilitățile Evidence. Managerul poate propune explicit serie nouă, dată de instalare sau dată de intervenție dintr-un document atașat, cu citat/pagină. Engine::propose păstrează status proposed, proveniența, conflictele și review-ul existente. Se asociază afirmația cu activul; nu se confirmă și nu se aplică automat. Veridicitatea citatului este responsabilitatea review-ului uman, fără OCR/AI automat. Nu au fost făcute apeluri AI.

Jurnalul append-only **asset_audits** se extinde cu property_id/entity_type/entity_uuid. Evenimentele cu activ apar și în istoricul său; sesizările fără activ au același jurnal, fără o infrastructură de audit paralelă. Snapshoturile păstrează lucrări, checklist, piese și costuri; comentariile sunt append-only. Documentele au auditul Evidence existent.

La ștergerea logică a clădirii, înregistrările localizate în ea devin indisponibile; restaurarea păstrează UUID-urile. Purjarea explicită din coș păstrează înregistrările operaționale la proprietate, suspendă planurile și blochează ordinele neterminate, cu evenimente în același jurnal/tranzacție. Documentele urmează retenția Evidence; legăturile istorice păstrează source_uuid și marchează sursa indisponibilă după eliminare. Nu se generează ordine noi dintr-un plan suspendat.

## Planuri, programare și notificări

Planurile acceptă zilnic, săptămânal, lunar, trimestrial, anual, interval personalizat și placeholder usage fără scadențe inventate. Data ancoră și indexul apariției păstrează zilele de sfârșit de lună (31 ianuarie → 28 februarie → 31 martie). Termenele ordinelor și next_at sunt normalizate UTC din data/fusul proprietății; agregarea upcoming este pe instanți, inclusiv portofolii cu fusuri diferite. Schimbarea recurenței afectează doar aparițiile viitoare; ordinele deja generate și unicitatea plan/dată rămân.

Schedulerul pune GenerateMaintenance în coada **maintenance** pe conexiunea database, orar. Fiecare plan este blocat după proprietate; cheia unică plan_id/scheduled_for previne dublurile la concurență/retry. Maximum 20 apariții restante per plan/rulare și loturi de 100 planuri limitează procesarea. Planurile cu responsabili de configurare eliminați/fără acces, clădiri indisponibile sau active arhivate nu generează ordine. Un manager nou poate revizui și salva planul pentru a prelua responsabilitatea de configurare (managed_by); created_by și jurnalul păstrează creatorul original. Nu se presupun drepturi ale unui cont șters.

Preferința notify_assignee este per plan, implicit oprită. Notificarea email se pune după commit și reevaluează drepturile destinatarului. Folosește infrastructura SMTP centralizată; nu există adresă/furnizor nou în .env. Notificările SMTP sunt at-least-once la retry/crash de transport, nu o garanție exactly-once de livrare. Idempotenta ordinelor este garantată separat în DB.

Nu se presupun intervale legale obligatorii. Datele după utilizare/IoT nu sunt generate până la existența măsurătorilor și regulilor validate.

## Centrul de comandă și setări

Listele și panoul folosesc aceeași paginare server-side de 24. Panoul arată pagina curentă, nu întreg inventarul în browser; dropdown-uri/căutări au 20 rezultate. Sursele 10, comentariile 15, istoricul 20; sumarul recent este limitat. Filtrele includ organizație/portofoliu/proprietate/clădire/spațiu/activ, categorie, prioritate, stare, atribuiri și întârzieri. Nu există drag-and-drop obligatoriu; schimbarea stării este un formular accesibil cu motiv. CSS respectă prefers-reduced-motion.

Metricile provin din DB sub granturile utilizatorului: sesizări deschise/critice, nealocate, ordine întârziate, planuri în 30 zile, spații afectate, costuri cunoscute pe monedă, intervenții recente și conflicte autorizate. Necunoscut nu este zero. Totalurile de cost nu includ sume nedeclarate și nu convertesc monede.

Implicit, trei ordine distincte rezolvate/verificate/închise pentru același activ și categorie în 90 zile produc o observație cu referințe. **Administrare platformă → Mentenanță** configurează fereastra 7–3650 zile și pragul 2–20, în platform_settings criptat, sub guard separat și autentificare recentă. Aceste valori nu vin din .env. Observația este deterministă; nu este diagnostic AI sau cauză confirmată. Repetițiile de sesizări se afișează separat și nu sunt unite automat.

## Modificări și baza de date

Migrare aditivă: 2026_11_17_000000_create_maintenance_workflows. Creează maintenance_issues, maintenance_plans, work_orders, maintenance_issue_work, maintenance_comments, maintenance_attachments și extinde asset_audits. UUID/ref și ancorele organizație/proprietate sunt imuabile. FK-uri explicite leagă proprietatea, clădirea, spațiul, activul, utilizatorii, planul și versiunile Evidence; nu se dezactivează FK și nu se resetează date existente. Liniile de materiale sunt declarații versionate în snapshotul ordinului/jurnalului, cu referințe opționale verificate în aceeași proprietate.

Fișiere principale:
- app/Maintenance/{Workflow,Documents,Retention,Settings}.php — domeniu, tranzacții, recurențe, documente și reguli.
- app/Models/Maintenance*.php, WorkOrder.php; Policies/MaintenancePolicy.php — date și autoritate.
- app/Jobs/{GenerateMaintenance,NotifyMaintenance}.php, Notifications/MaintenanceAssigned.php, routes/console.php — scheduler/coadă/SMTP.
- Identity/MaintenanceController.php, routes/maintenance.php; resources/views/maintenance/*, css/maintenance.css, js/maintenance.js, lang/{ro,en}/maintenance.php — experiența operațională.
- Engine::uploads acceptă autoritate de upload operațional verificată; Registry::purgeBuilding include retenția mentenanței. Identitățile, extragerea, review-ul și furnizorii AI nu sunt înlocuiți.
- OrganizationMembership/PropertyPermissions/CapabilityService și pagina membrilor adaugă roluri restricționate; SettingsController/tab admin configurează observațiile.
- Layout/asset-room/Building dashboard/Asset Passport adaugă navigația și raportarea contextuală.
- tests/Feature/MaintenanceTest.php — scenarii și regresii; README și DEPLOY-FORGE — checklist și lansare.

## Verificări și limite

**Rezultat final local:** 230 teste PHP / 2.075 aserțiuni, toate trecute. Cele 24 teste noi Maintenance au 161 aserțiuni. Cele 10 verificări ale generatorului Building Studio, build-ul Vite, Pint/lint PHP și cache-urile de rute/Blade au trecut.

Comenzi: php vendor/bin/phpunit, node --test tests/building-generator.mjs, npm run build, Pint pe fișierele PHP modificate, lint PHP, route:cache și view:cache în fișiere izolate. Bazele de test sunt izolate; nu se folosește migrate:fresh pe date reale.

Browser local, date sintetice izolate: raportare din pașaport, sugestie de simptom, precompletare cameră/activ și sesizări similare; ordin din sesizare, alocare, checklist, material/costuri, rezolvare/verificare/închidere cu autor și istoric; atașament privat; suspendare/reluare/editare plan și interval personalizat; listă/panou, RO/EN, mobil 390×844 fără overflow orizontal și navigare Tab cu focus vizibil. Ultimul tab pe build final nu a avut erori/warnings în consolă. Capturi locale build/maintenance-desktop.jpg și maintenance-mobile.jpg. Fișierele și baza QA au fost eliminate după verificare.

Nu s-a făcut deploy, test MariaDB/MySQL sau expediere SMTP reală pe VPS. Mediul local folosește SQLite; validarea DB/locking și workerilor pe VPS rămâne checklist de lansare. Nu există portal public pentru furnizori externi, echipe cu membership separat (team este o responsabilitate declarată), calendar drag-and-drop, SLA contractual, IoT real, scanare antivirus dedicată sau certificare profesională automată. Documentele fără clădire și sistemele care traversează proprietăți diferite necesită extensii de domeniu, fără fabricarea unei clădiri suport.

Următorul release recomandat: QR pentru active/spații și raportare contextuală rapidă, urmat de SLA/escaladări și calendarul echipelor.
