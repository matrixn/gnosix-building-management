# GNOSIX 0.19.0 — Time Machine

Mașina timpului reconstruiește ce era documentat despre o clădire la o dată aleasă. Din meniul organizației, selectează **Mașina timpului** și clădirea sau pornește din Building Studio, Asset Passport, Maintenance, Building DNA ori Evidence Review. Alege **Compară cu** pentru două stări și folosește exportul PDF pentru comparația documentată.

## Cele trei momente

- **Data clădirii** reprezintă sfârșitul zilei în fusul proprietății. O dată efectivă declarată rămâne distinctă de momentul înregistrării.
- **Limita informațiilor cunoscute** se exprimă în UTC. Modul documentat folosește această limită pentru dovezi și verificări. Modul „Cunoscut la fiecare dată” o limitează suplimentar la data fiecărei stări.
- **Înregistrarea sursei și revizuirea** au momente separate. O instalare din 2022 declarată sau dovedită în 2026 poate apărea în prima vedere și lipsește din ce știa platforma în 2022. O afirmație cu dată efectivă necunoscută rămâne o afirmație separată.

Structura, poziția echipamentului, conexiunile și stările operaționale fără interval efectiv explicit sunt observații reconstruite până la minimul dintre data clădirii și limita de cunoaștere. O înregistrare retrospectivă a instalării apare separat și nu presupune că locația, starea sau garanția de la înregistrare existau și la data instalării. Înlocuirea se vede prin echipamentul arhivat sau mutat și echipamentul nou. Legătura de succesiune între ele necesită dovezi explicite.

## Auditul surselor existente

| Modul | Sursă folosită |
| --- | --- |
| Building Studio | Ultima declarație completă înainte de limită, apoi cea mai recentă revizie directă per clădire, nivel sau spațiu. Coșul și restaurarea vin din reviziile declarației. |
| Asset Passport | Ultimul audit de stare per activ, calculat înaintea filtrării amplasării. Păstrează tipul de sistem, poziția documentată, spațiile deservite din registru, starea și garanția declarată. |
| Building DNA | Reviziile relațiilor tipizate și asocierile de deservire înregistrate în auditul registrului. Un capăt nedocumentat în clădire rămâne necunoscut. Sursele accesibile se deschid în Evidence. |
| Maintenance | Auditul ordinelor și sesizărilor, intervențiile consemnate și reviziile trimise ale rapoartelor de service. Data de consemnare a unei intervenții nu este o dată fizică presupusă. |
| Evidence | Versiuni originale, afirmații imutabile, pagini de referință, evenimente de arhivare și ultima decizie de review cunoscută. Afirmațiile concurente sunt afișate împreună și nu suprascriu datele declarate. |
| QR / Field | Identitatea și rotația QR rămân în registrul de teren. Scanarea sau existența unei etichete nu stabilește prezența fizică și codurile, locatoarele și invitațiile nu intră în proiecție. |

Proiecția este de citire și reutilizează istoricul existent. Migrarea adaugă doar indexuri. Construiește ambele părți ale comparației în aceeași tranzacție de citire, cu snapshot SQLite sau izolare repeatable read la nivel de tranzacție pe MySQL/MariaDB. Nu există cache sau o copie a registrului. Înregistrările cu același timestamp au ordine stabilă în istoricul sursă; API-ul actual are precizie de o secundă.

## Acces și raport

Time Machine folosește capabilitatea `time.view` și accesul actual la proprietate. Fiecare secțiune verifică și dreptul modulului sursă. Un grant de teren sau un URL QR nu deschide proiecția. PDF-ul și pagina trimit răspunsuri private fără cache. Accesul la original este verificat din nou de Evidence. Trimiterea unui raport de service, fișierul și decizia de review sunt fapte distincte. Un raport purjat sau o clădire purjată nu sunt refăcute din audit.

Comparația indică ce a devenit vizibil, ce a ieșit din vedere și ce s-a schimbat. Implicit pot fi comparate toate secțiunile. PDF-ul include cele două date și limitele de cunoaștere, data generării, proveniența fiecărei înregistrări afișate și mențiuni despre lipsuri sau verificări. Comparațiile lungi se exportă în părți de maximum 80 de diferențe. Raportul rămâne informativ.

## Limite

- Pentru date fără istoric suficient rezultatul este incomplet. Datele scrise direct în bază și asocierile anterioare auditurilor pot lipsi. Nu există geometrie istorică măsurată sau legătură de succesiune dedusă între active.
- Fiecare adaptor materializează maximum 2.500 de rânduri per categorie de interogare. Fereastra SQL selectează ultimele revizii și poate examina mai multe evenimente. Declarația completă face parte din istoricul de origine. La atingerea unei limite, UI avertizează, iar comparația suprimă diferențele bazate exclusiv pe absență. Rezultatele din UI se afișează câte 24.
- Graful istoric pornește de la clădirea selectată. Un capăt din afara proiecției nu este rezolvat din inventarul curent. Revizuirea unei relații apare așa cum a fost consemnată la limită. Vizibilitatea originalelor depinde de permisiunile și retenția curente.
- Verificarea locală a folosit SQLite. Pentru instalarea proprie, folosește o versiune de MySQL/MariaDB care suportă funcții fereastră și operații JSON, așa cum sunt utilizate de proiecție.

## Validare și publicare

Teste de acceptanță în `tests/Feature/TimeMachineTest.php`: lipsă date, instalare ulterioară sau declarată tardiv, mutare și înlocuire, redenumire și reconfigurare, conexiuni retrase, intervenție, contradicții, review la un cut-off, coș și restaurare, permisiuni, PDF și 3.200 de revizii. Testul concurent folosește două conexiuni SQLite WAL și o revizuire Evidence reală între citirile aceleiași proiecții.

Rezultat: 293 teste PHP și 2.494 aserțiuni, inclusiv șapte teste Time Machine și 64 de aserțiuni. Au trecut șapte teste DNA JavaScript, zece scenarii ale generatorului, build-ul și compilarea cache-urilor de configurare, rute și Blade.

Comenzi de verificare: `php vendor/bin/phpunit`, `npm run build`, `node --test tests/dna-graph.mjs tests/building-generator.mjs`. Verificarea în browser a acoperit comparația RO/EN, lățimea 390 px, controalele cu tastatura și descărcarea PDF. Build și arhivă prin `npm run build` și `python scripts/package-release.py`. Instrucțiunile de migrare și verificare Forge sunt în `docs/DEPLOY-FORGE.md`.
