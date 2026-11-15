# Genesis 002 — organizații, profilare și capabilități

Auditul Task 001, efectuat înainte de modificări, este în [GENESIS-002-AUDIT.md](GENESIS-002-AUDIT.md). Baseline: 79 teste / 575 aserțiuni. Nu sunt adăugate dependențe Composer/NPM și nu se înlocuiește autentificarea existentă. Registrul de livrare este checklistul din README; AGENTS.md impune actualizarea lui la fiecare task.

## Modelul de date

O singură migrare aditivă creează:

| Tabel | Responsabilitate |
| --- | --- |
| organizations | Identitatea stabilă a organizației și numele spațiului; fără clasificarea clădirilor. |
| organization_memberships | Legătura user–organizație, rol owner/manager/member, unicitate și indexuri în ambele direcții. |
| organization_profiles | Profilul comercial, forma de operare și versiunea schemei; un profil curent per organizație. |
| organization_capabilities | Opțiuni explicite de acces per organizație, cu autorul ultimei modificări. |
| user_preferences | Organizația activă a utilizatorului. Limba rămâne în User.locale din Task 001, fără duplicare. |
| onboarding_sessions | O ciornă activă/ultima finalizare per utilizator: stare validată, pas, versiune, rezultat și completed_at. |

Profilul personal din Task 001 (`owner/manager/technical`) rămâne separat și informativ. Nicio clădire nu este cuplată la profilul comercial. Task 002 nu adaugă tabele CRUD de clădiri sau dovezi. Recomandările `hotel/apartment/...` sunt metadate de onboarding, nu validări care limitează proprietatea.

## Registru și disponibilitate

`config/profiles.php` este contractul versionat al softwareului, citit exclusiv prin `ProfileRegistry`: identificatori, disponibilitate, scheme/forme, recomandări, capabilități și navigare. Nu citește variabile de mediu și nu este o listă de setări business în `.env`. Opțiunile editabile ale organizației sunt salvate din interfață în DB.

- **hospitality:** activ pentru profilare, cu independent/company/portfolio.
- **construction:** reprezentat în arhitectură și UI, momentan indisponibil.
- **facility:** reprezentat în arhitectură și UI, momentan indisponibil.

Capabilitățile Hospitality pentru proprietăți, camere, mentenanță și upload/review au contractul de acces disponibil. `evidence.conflicts` și capabilitățile verticale viitoare nu pot fi activate. Fiecare descriptor precizează separat `enabled` (contract de acces) și `operational` (modul implementat). În acest release modulele de clădiri/dovezi sunt încă `operational=false`; paginile lor explică stadiul, fără formulare fictive de CRUD sau upload. Acest lucru este marcat separat în README.

`CapabilityService` este singurul evaluator al capabilităților, folosit pentru navigare, vizibilitate și endpointul protejat al modulului. Verifică apartenența curentă, profilul activ, includerea capabilității în profil, disponibilitatea globală și opțiunea din DB. Un override nu poate activa o funcționalitate nelansată, necunoscută sau din alt profil. Nu există cache global de autorizație care să păstreze accesul după revocare.

## Autorizație și context

| Rol | Permisiuni curente |
| --- | --- |
| owner | Vede organizația, schimbă profilul și capabilitățile, adaugă/gestionează membri confirmați. |
| manager | Vede organizația, schimbă profilul și capabilitățile; nu administrează apartenențe. |
| member | Citește organizația și contextele modulelor permise; nu schimbă setări sau membri. |

Rolurile necunoscute sunt refuzate. `OrganizationPolicy` verifică DB, nu o valoare trimisă de client. ID-urile de apartenență din rute sunt căutate în organizația autorizată; nu se poate modifica un membru din altă organizație. Proprietarul nu poate fi eliminat, retrogradat sau creat prin payloadul de adăugare membri. Transferul proprietății și invitațiile către conturi inexistente nu sunt implementate.

`TenantContext` rezolvă organizația activă din preferința utilizatorului și o apartenență validă. Dacă preferința este veche sau falsificată, revine la prima apartenență validă ori la spațiul personal. Contextul este curățat la început și la sfârșitul cererii. Schimbarea preferinței nu acordă acces; rutele verifică separat organizația explicită. Header-ele cu tenant ID și organizația activă nu înlocuiesc policy-ul.

Rutele păstrează guardul web, verificarea emailului, sesiunea versionată, CSRF, no-store/noindex și limitarea cererilor. Adminul platformei nu primește implicit acces la organizațiile clienților.

## Fluxuri și rute

- `/app/onboarding`: introducerea Genesis și noua selecție de profil pentru utilizatorul nou. Alegerea creează organizația la final, fără întrebări fiscale sau despre numărul de camere.
- `/app/organizations`: lista exclusivă a organizațiilor proprii, intrare pentru creare și schimbare.
- `/app/organizations/create`: creare suplimentară sau reluarea ciornei curente.
- `POST /app/organization-onboarding/draft`: salvează doar profil, formă și nume; răspuns JSON pentru autosave sau redirect pentru formular nativ.
- `POST /app/organization-onboarding`: confirmă atomic organizația, proprietarul, profilul, preferința și onboardingul.
- `/app/organizations/{organization}`: setări și membri, cu permisiuni diferențiate.
- `POST .../{organization}/switch`: schimbare autorizată a organizației active.
- `PUT .../{organization}/capabilities`: activare/dezactivare a contractelor disponibile.
- `POST/PATCH/DELETE .../{organization}/members[/{membership}]`: administrarea apartenențelor.
- `GET .../{organization}/modules/{capability}`: context protejat; Accept JSON întoarce doar metadata autorizată. Capabilitățile nepermise primesc 403 și prin cereri directe.

Utilizatorii existenți din Task 001 nu primesc automat organizații. Spațiul personal oferă CTA pentru configurare. Ruta POST veche de onboarding personal rămâne compatibilă, dar interfața nouă folosește profilarea organizației. Utilizatorii adăugați ca membri pot intra direct în organizație fără a fi obligați să creeze încă una.

## Persistență și schimbarea profilului

Salvarea este serializată în browser, cu feedback corect pentru date încă nesalvate. Serverul blochează rândul utilizatorului/ciornei în tranzacție și compară versiunea; o fereastră veche nu suprascrie ciorna nouă. Câmpurile necunoscute, user_id, roluri sau ID-uri injectate nu sunt persistate în stare. Submitul final așteaptă salvările și folosește ultima versiune. Finalizarea repetată este idempotentă.

La inițierea explicită a unei alte organizații, ultima ciornă finalizată este resetată pentru noul flux. Organizația creată anterior și toate relațiile sale rămân intacte. Acest tabel nu este un audit log complet și nu oferă mai multe ciorne simultane per utilizator.

Schimbarea profilului/formei actualizează numai numele și organization_profiles. Nu șterge organizația, membrii, opțiunile de capabilități sau date de domeniu. Opțiunile inactive ale altui profil sunt păstrate și pot redeveni relevante la revenire. Activarea viitoare a altui profil se face printr-un release al registrului și modulelor, fără schimbarea distructivă a structurii existente.

## Interfață și localizare

Cele trei carduri au ilustrații SVG locale distincte, exemple, stări disponibile/în curând și explicații native details/summary accesibile din tastatură. Selecția Hospitality dezvăluie forma de operare și apoi numele. Formele disponibile sunt derivate din registru, fără verificări de industrie în controllere sau componente.

Totul este localizat prin `lang/ro/workspace.php` și `lang/en/workspace.php`, reutilizând limba și componentele Task 001. Salvarea manuală funcționează fără JavaScript; instrucțiunile indică atunci explicit lipsa autosave. Motion redus oprește animația SVG, iar preferințele temporare ale browserului de test au fost resetate.

## Validare și deploy

- Suita completă: **98 teste / 737 aserțiuni**, incluzând 19 teste noi de organizații / 162 aserțiuni și toate regresiile Task 001, landing, SMTP și passkeys.
- Testele includ acces străin/IDOR, roluri, revocare, capabilități inactive/necunoscute, profile viitoare respinse pe server, preferință falsificată, CSRF, ciorne concurente și finalizare repetată.
- Un test de upgrade simulează activarea viitoare a Construction și păstrează un hotel și un document în tabele-fixture, apoi revine la Hospitality cu opțiunile vechi păstrate. Aceste fixtures nu sunt module de producție.
- Browser: creare, autosave/refresh, două organizații, switching, adăugare manager, vizibilitate diferită între utilizatori, schimbare de formă și dezactivare de capabilitate; RO/EN, 390 px și 1440 px, fără overflow, fără erori JS. Salvare nativă fără JS și explicație accesată din tastatură; motion redus verificat.
- Build Vite, cache rute/view-uri și verificarea de producție sunt rulate local. Nu se instalează dependențe noi și nu se trimit emailuri externe.

Deploy-ul Forge existent rulează deja `npm ci`, `npm run build`, `migrate --force`, cache-urile și `gnosix:check --production`. Comanda verifică acum și cele șase tabele noi. Nu este necesară o comandă de provisioning nouă, variabile GNOSIX în mediu sau recrearea conturilor. Nu folosi rollback automat: ar șterge tabelele organizațiilor. Backup-ul DB și APP_KEY rămâne obligatoriu pentru operare.

Verificarea VPS-ului și scenariul real de operare se fac după deploy. Transferul proprietarului, invitațiile email, CRUD-ul clădirilor/documentelor și fluxurile Construction/Facility rămân nebifate în README.
