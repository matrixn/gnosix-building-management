# Limba inițială a paginii publice

Ruta `/` folosește țara estimată pentru IP-ul cererii. Un IP străin identificat redirecționează temporar la `/en` și transferă parametrii campaniei. Pentru România, IP-uri locale și adrese cu țară necunoscută, afișează româna. Linkurile `/ro` și `/en` deschid explicit limba aleasă și sunt listate ca adrese alternative în sitemap. Selectorul RO/EN păstrează alegerea în sesiunea existentă.

Clasificarea folosește adresa determinată de Laravel. Pe Forge, conexiunile directe folosesc adresa primită de Nginx. Dacă instalezi un reverse proxy, configurează lista de proxy-uri de încredere pentru ca Laravel să primească adresa vizitatorului. Pagina de intrare și redirecționarea de limbă sunt private și nu trebuie puse în cache partajat. Modulul folosește IP-ul în memorie pentru clasificare. Raportul de trafic reține doar codul țării estimat la intrare. Clasificarea rulează local.

Indexurile livrate sunt derivate din datele [DB-IP Country Lite](https://db-ip.com/db/lite.php). Metadatele sursei și licența CC BY 4.0 sunt în [resources/geo/SOURCE.md](../resources/geo/SOURCE.md). Pentru a actualiza datele, descarcă versiunea lunară CSV gzip de la DB-IP și rulează `python scripts/build-landing-geo.py <fișier.csv.gz> <AAAA-LL>`. Include ambele indexuri și metadatele sursei în release. Dacă indexul lipsește sau nu poate fi citit, pagina continuă în română. Datele IP reprezintă estimări, inclusiv pentru VPN-uri.

Forge folosește deploy-ul obișnuit. Indexurile sunt livrate în `resources/geo` și nu necesită o migrare sau o cheie externă.
