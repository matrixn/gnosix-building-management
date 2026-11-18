# Campanii UTM

În administrarea platformei, deschide **Campanii**. Lista arată linkurile înregistrate, variantele UTM și totalurile filtrate. Poți căuta după sursă, canal, campanie sau conținut, sorta după activitate și copia adresa reconstruită cu domeniul public configurat.

Exemplu: `/?utm_source=outreach&utm_medium=email&utm_campaign=uk_founding_partners_2026&utm_content=property`. Ambele landing page-uri (`/` și `/en`) acceptă `utm_campaign` și opțional `utm_source`, `utm_medium`, `utm_content`, `utm_term` și `utm_id`. Identifică segmentul cu o valoare precum `property`; folosește etichete de campanie, fără date despre destinatari. Parametrii suplimentari sunt excluși din raport.

O vizită apare la prima deschidere a linkului etichetat. Reîncărcările aceluiași link sunt deduplicate cât rămâne ultima campanie a sesiunii, maximum două ore. Un alt link etichetat preia atribuirea; navigarea fără UTM păstrează atribuirea până la expirare. Rata împarte vizitele cu cel puțin o conversie la vizitele înregistrate. Se contorizează o singură dată per vizită fiecare categorie: formular de contact trimis cu succes și cont client creat. Confirmarea adresei de email este un pas separat.

Măsurarea pornește odată cu publicarea. Scanerele de email care încarcă pagina pot apărea în estimare. Sesiunile noi, schimbarea dispozitivului și acțiunile după expirare pot lăsa conversii neatribuite. Cererile HEAD, cererile care se declară prefetch și semnalele DNT/GPC sunt excluse. Raportul folosește sesiunea Laravel existentă și păstrează totaluri, etichete și identificatori de vizită temporari, separați de datele de contact și de cont.

Pe Forge, scriptul standard aplică migrarea și compilează frontend-ul. Schedulerul existent trebuie să ruleze pentru curățarea identificatorilor temporari după expirare. Comanda `php artisan gnosix:campaign-cleanup` poate verifica manual curățarea, cu păstrarea totalurilor. După activare, deschide un link UTM de test, creează un cont în aceeași sesiune și verifică rezultatul în administrare.
