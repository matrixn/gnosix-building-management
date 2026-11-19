# Trust Passport și Smart Building Handover · 0.21.0

## Fluxul din aplicație

1. Din **Pașaportul clădirii** sau din pagina unei clădiri deschide pașaportul. Aici vezi inventarul, acoperirea pe categorii, observațiile și legăturile către modulele unde poți corecta datele. Recalculează după corecții.
2. Creează o ciornă în **Studioul de predare**. Identitatea de bază este inclusă. Bifează secțiunile aprobate, alege explicit versiunile originale, verifică lista, notează responsabilitățile rămase și înregistrează temeiul contractual sau mandatul real. Selectează durata accesului de după finalizare, între 0 și 365 de zile.
3. Emite invitațiile înainte să se activeze prima. Copiază fiecare link și transmite-l în privat la adresa nominalizată. Fiecare fereastră de activare și examinare poate dura între 1 și 14 zile.
4. Destinatarul folosește un cont web GNOSIX verificat cu adresa invitată. Dacă trebuie să se conecteze sau să își verifice adresa, redeschide linkul original. În **Predări primite** găsește apoi pachetele valabile. Poate descărca originalele alese, pune întrebări, solicita corecturi prin returnare sau accepta.
5. După acceptarea tuturor recenzenților activi, sursa verifică din nou drepturile și originalele, apoi confirmă finalizarea. Un termen mai mare decât 0 creează numai acces de citire la pachetul aprobat. Acest acces poate fi revocat. Permisiunile organizației sursă și dreptul de proprietate rămân cele existente.

Stări: ciornă → pregătit → invitat → în examinare → acceptat → finalizat. Returnarea închide accesul și păstrează motivul pentru sursă. Pentru corecții după emitere, folosește o predare nouă. Anularea înainte de finalizare revocă invitațiile. Dacă un recenzent expiră, sursa îl poate retrage explicit și continua cu recenzenții rămași sau poate crea o nouă predare. Mutările care schimbă starea folosesc revizia curentă și tranzacții. Răspunsurile sursei la întrebări sunt vizibile numai recenzentului care le-a inițiat.

## Inventar și limite

- Clădirea are același UUID și aceeași referință de pașaport, inclusiv după finalizarea unei predări. Pașaportul normal se calculează din registrele existente. Predarea păstrează un instantaneu limitat la secțiunile alese.
- Fiecare indicator afișează numărătorul, numitorul și formula. Documentația numără categoriile din inventarul activ dintre cinci urmărite. Disponibilitatea originalelor este o verificare separată pentru versiunile afișate. Identificarea și garanția folosesc datele declarate, iar mentenanța și coerența folosesc starea curentă a ordinelor și a dovezilor. Numitorul zero este afișat ca lipsă de date. Nu se calculează scor total și nu se folosesc ponderi.
- Observațiile semnalează documentație tehnică absentă, original indisponibil, conflicte, serie în dispută, garanție necunoscută sau fără sursă curentă examinată, ordine critice, rapoarte în așteptare și sisteme fără relații înregistrate. Acolo unde lipsește o înregistrare, linkul duce la registrul în care se poate completa. În portal, observațiile indică secțiunea partajată.
- Datele declarate, susținute documentar, verificate uman și disputate au proveniențe distincte. Sistemul existent de Evidence păstrează câmpul pentru evaluarea profesională. GNOSIX nu atestă inspecția independentă, siguranța structurală sau conformitatea legală în acest flux.
- Inventarele din pașaport, PDF și JSON afișează maximum 40 de rânduri pe secțiune, cu totalul separat. Pentru inventarul complet se folosesc registrele sursă. Pickerul de versiuni este paginat. Salvează selecția înainte să treci la următoarea pagină. Sunt acceptate cel mult 40 de versiuni pe pachet și 20 de recenzenți.
- Legăturile originale pot fi retrase ulterior dacă sursa arhivează documentul sau fișierul devine indisponibil. O copie descărcată anterior poate fi păstrată de destinatar. Exporturile nu includ originale, geometrie, coordonate, adrese, costuri, fotografii, numere de serie sau temei contractual. Verifică numele, titlurile și textul liber pe care alegi să le partajezi.
- Modelul de date permite destinatari individuali și verificarea apartenenței la o organizație desemnată. Capabilitățile sunt activate în profilul Hospitality, singurul profil operațional actual. Grantul aparține recenzentului verificat, nu întregii organizații. Accesul de scriere, transferul de titlu, semnăturile externe și registrele oficiale nu fac parte din acest release.

## Arhitectură și verificări de acces

Auditul 0.18–0.20 a identificat sursele canonice: Building Studio pentru identitate și revizii, Evidence pentru originale, versiuni și conflicte, Asset Registry pentru echipamente și garanții, Maintenance pentru ordine și rapoarte, Building DNA pentru dependențe. Portalul Field a furnizat modelul de autentificare de client verificată în afara organizației. Time Machine și Operations rămân surse navigabile de analiză, fără copii ale datelor de lucru.

| Componentă | Rol |
|---|---|
| app/Trust/Passport.php | Proiecție, capabilități, limite, formule și observații |
| app/Trust/Handover.php | Mașina de stări, revizie optimistă, granturi, întrebări și audit |
| app/Trust/Exports.php | PDF, JSON și ZIP dintr-un singur instantaneu |
| routes/trust.php, routes/handover.php | Workspace autentificat și portal separat de onboarding |
| resources/views/trust, resources/css/trust.css, resources/js/trust.js, lang/{ro,en}/trust.php | Interfață Blade și localizare |
| 2026_11_19_100000_create_building_handovers.php | Patru tabele noi cu referințe la clădire, organizație și utilizator |
| tests/Feature/TrustPassportTest.php | Flux, permisiuni, expirare, revocare, concurență optimistă și export |

Token de 256 biți în fragmentul linkului, hash în baza de date, utilizare unică, verificare de email și, opțional, membership existent. Portalul folosește CSRF, sesiuni, rate limiting și răspunsuri private no-store. Originalele sunt asociate clădirii sursă, verificate după SHA-256 și servite ca atașamente. La trimiterea unei acceptări, operația ia lock pe proprietate și clădire înainte de a reverifica accesul și revizia. Un răspuns dintr-o pagină veche primește conflict HTTP 409. Anularea și revocarea păstrează actorul și motivul. Arhivarea clădirii suspendă accesul. Restaurarea reactivează doar accesul încă valabil. Purjarea confirmată din Coș curăță și tabelele de predare aferente. Respectă politica de retenție și permisiunile pentru copiile exportate.

## Export și verificare offline

Arhiva conține passport.pdf, passport.json și manifest.json. JSON folosește gnosix.trust-passport.v1. Manifestul gnosix.trust-manifest.v1 declară schema, fișierele, dimensiunile și SHA-256. Verificarea nu necesită acces la GNOSIX.

```bash
python scripts/verify-passport.py GNOSIX-TP-<uuid>.zip
```

O verificare reușită confirmă concordanța fișierelor cu manifestul furnizat. Manifestul poate fi refăcut de cineva care schimbă și fișierele. Confirmarea provenienței juridice necesită un mecanism de semnare distinct.

## Forge și revenire

Rulează deploy-ul zero-downtime standard. Migrarea este aditivă. Dompdf este deja instalat. Verifică extensia PHP ZIP în CLI și PHP-FPM, spațiul temporar disponibil și cache-ul de rute. Nu sunt chei, pachete Composer/NPM sau cozi noi. Păstrează baza de date și storage-ul Evidence în backup. Pentru smoke și rollback, vezi [Publicare pe Forge](DEPLOY-FORGE.md).
