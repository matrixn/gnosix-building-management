# Trafic și campanii

În administrarea platformei, deschide **Trafic și campanii**. Lista arată intrările pe paginile publice, atât cu UTM, cât și fără UTM, și totalurile filtrate. Poți căuta după sursă, canal, campanie sau conținut, sorta după activitate și copia adresa reconstruită cu domeniul public configurat.

Exemplu: `/?utm_source=outreach&utm_medium=email&utm_campaign=uk_founding_partners_2026&utm_content=property`. Ambele landing page-uri (`/` și `/en`) acceptă `utm_campaign` și opțional `utm_source`, `utm_medium`, `utm_content`, `utm_term` și `utm_id`. Identifică segmentul cu o valoare precum `property`. Folosește etichete de campanie, fără date despre destinatari. Parametrii suplimentari sunt excluși din raport.

O vizită se înregistrează la o intrare pe `/` sau `/en`. O reîncărcare a aceluiași link în fereastra atribuirii nu adaugă o vizită. Navigarea internă fără UTM păstrează intrarea din sesiune. Un domeniu extern diferit sau o intrare externă pe altă pagină se poate înregistra separat. O vizită directă sau cu proveniență ascunsă de browser apare drept **Directă / necunoscută**.

Fiecare link oferă distribuția vizitelor după domeniul transmis în antetul Referer. Se păstrează gazda normalizată, fără cale, query, fragment sau adresă IP. Domeniul propriu este tratat ca navigare internă. Contoarele UTM existente rămân disponibile. Vizitele UTM înregistrate înainte de introducerea referrerului apar separat în distribuție, cu proveniență indisponibilă. Căutarea după domeniu găsește linkurile asociate, iar totalurile rămân ale linkurilor întregi.

Conversia se atribuie ultimului link UTM activ din aceeași sesiune sau, în lipsa lui, intrării fără UTM. Fereastra este de două ore. Rata împarte vizitele cu cel puțin o conversie la vizitele înregistrate. Se contorizează o singură dată per vizită fiecare categorie: formular trimis cu succes și cont client creat. Confirmarea emailului este un pas separat.

## Raport pe țări

Tabelul **Vizite și conversii pe țări** urmărește filtrele existente și arată separat traficul cu UTM și fără UTM. Deschide **Țări pentru acest link** pentru distribuția linkului. Conversiile păstrează țara estimată la intrarea pe site, chiar dacă IP-ul se schimbă ulterior.

Geolocalizarea folosește același index local IPv4/IPv6 ca selectorul de limbă. Se reține doar codul țării. Vizitele noi pentru care țara nu poate fi identificată apar la **Țară necunoscută**. Vizitele anterioare release-ului păstrează totalurile existente și nu primesc o țară retroactiv. Ștergerea sesiunilor de atribuire expirate păstrează totalurile pe țări.

Scanerele de email care încarcă pagina pot apărea în estimare. Schimbarea dispozitivului și acțiunile după expirare pot lăsa conversii neatribuite. Cererile HEAD, prefetch și semnalele DNT/GPC sunt excluse. Raportul folosește sesiunea Laravel existentă, cu totaluri, etichete, domenii și identificatori de vizită temporari separați de datele de contact și de cont.

Pe Forge, scriptul standard aplică migrarea și compilează frontend-ul. Schedulerul existent trebuie să ruleze pentru curățarea identificatorilor temporari după expirare. Comanda `php artisan gnosix:campaign-cleanup` poate verifica manual curățarea, cu păstrarea totalurilor. După activare, deschide un link UTM de test, creează un cont în aceeași sesiune și verifică rezultatul în administrare.
