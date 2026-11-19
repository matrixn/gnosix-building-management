# GNOSIX 0.20.0 · Hospitality Intelligence Center

În meniul organizației, deschide **Centrul operațional**. Intră într-un portofoliu, o proprietate sau o clădire din Explorer. În clădire poți continua la nivel, spațiu și activ sau poți deschide direct Building Studio. Explorerul listează numai pagina curentă. Folosește **Vezi sursele** sau cardurile pentru a deschide înregistrările care compun un indicator.

## Auditul modulelor reutilizate

| Modul | Integrare |
| --- | --- |
| Maintenance 0.16 | Aceleași sesizări, ordine, planuri, statusuri, audit și prag de repetare. Atribuirea ordinelor și crearea planurilor folosesc formularele existente. |
| Smart QR / Field 0.17 | Accesul tehnicienilor deschide fluxul existent, sub permisiunile proprietății. |
| Building DNA 0.18 | Simularea unui activ folosește motorul de impact existent și oferă link către trasee. Contestările vin din relațiile DNA. |
| Time Machine 0.19 | Compară istoricul clădirii folosind datele selectate. |
| Smart Asset Registry | Inventarul, pașapoartele, sistemele comune și starea declarată rămân sursele de adevăr. |
| Evidence Engine | Conflictele și versiunile se pot investiga în studio. Data de garanție verificată se bazează pe afirmația confirmată, revizia aplicată și absența unui conflict deschis. |
| Organizații și portofolii | Scope folosește PropertyPermissions, CapabilityService și relațiile existente. |

## Indicatori și investigație

Situația curentă folosește stările încă active. Ordinele neterminate exclud rezolvat, verificat, închis și anulat. Nealocat înseamnă fără persoană și echipă. Întârziat înseamnă termen declarat în trecut. „Planuri scadente” înseamnă planuri active cu următoarea intervenție în 30 de zile. Un spațiu cu sesizare apare numai dacă este indicat explicit în sesizare. Un activ cere atenție când este marcat scos din funcțiune sau este asociat unei sesizări deschise.

Activitatea compară perioada selectată cu un interval anterior de aceeași lungime. Sesizările sunt numărate după data raportării, iar finalizările după data rezolvării pentru ordine încă rezolvate, verificate sau închise. Repetițiile grupează intervențiile rezolvate pe activ și categorie folosind pragul Maintenance. O observație de redeschidere repetată cere minimum două tranziții în jurnal. Sistemele comune sunt evidențiate când au cel puțin două ordine neterminate. Regulile arată ce înregistrări au produs observația și trimit la fluxurile originale. Fiecare observație este deterministă.

Simularea se pornește la cerere de pe un activ, cu buget de patru niveluri de traversare și maximum 80 de noduri. Afișează până la 12 spații și deschide traseele în DNA. Impactul potențial și sesizările confirmate ca existente sunt indicatori diferiți. Niciunul nu actualizează PMS sau disponibilitatea camerelor. Explorerul reutilizează vizualizarea declarată din Building Studio.

## Rapoarte și preferințe

Se pot genera șase rapoarte PDF în coada Maintenance. Sumarul săptămânal și raportul lunar folosesc ultimele 7, respectiv 30 de zile, sau intervalul selectat din opțiunea formularului. Celelalte rapoarte acoperă recurența, costurile pe activ, termenele preventive și calitatea datelor. PDF-ul are scop, perioadă, momentul generării, reguli, limite și până la opt referințe per indicator. Costurile sunt împărțite pe monede, componentele necunoscute rămân necunoscute și sunt numărate ordinele cu date incomplete. Raportul pe active afișează cel mult 30 combinații activ–monedă și recomandă restrângerea scopului când pragul este depășit.

În Preferințe, alege indicatorii sau Explorerul ca panou principal. Managerii cu acces de editare pot primi sumarul operațional săptămânal prin SMTP. Este dezactivat implicit și poate fi trimis o singură dată pe săptămână, per utilizator și organizație. Dacă încercarea de livrare eșuează, sumarul nu se retrimite automat în aceeași săptămână.

## Securitate și limite

Rutele cer client autentificat, email verificat, membership și capabilități. Fiecare selecție și agregare păstrează scopul autorizat. Lipsește accesul Evidence, lipsesc și indicatorii documentari. Costurile sunt accesibile din centrul operațional numai pentru proprietățile editabile. Mutările și modificările sunt delegate către modulele care aplică validările de domeniu.

PDF-urile sunt private, legate de autor și expiră după 24 de ore. Workerul reevaluează accesul înainte de generare. Descărcarea reevaluează din nou organizația, capabilitățile și amprenta proprietăților și clădirilor din scop. Reacordarea sau schimbarea scopului poate cere un raport nou. Schedulerul curăță fișierele expirate. Nu se efectuează apeluri AI, conversii valutare sau evaluări de disponibilitate și conformitate legală.

## Proveniența înscrierii

În **Administrare → Utilizatori → Vezi detalii**, „Proveniența înregistrării” afișează țara estimată, domeniul referrer și UTM-urile validate. Se folosește vizita care primește atribuirea. Un link care deschide direct formularul de înscriere poate furniza separat UTM și referrer. Se rețin numai codul țării, domeniul și etichetele, în contul creat. Semnalele DNT/GPC sunt respectate. Conturile vechi și cele create din admin păstrează proveniența indisponibilă. Proveniența nu este serializată către clienți.

Instrucțiunile de publicare și worker sunt în [Deploy Forge](DEPLOY-FORGE.md).
