# Contact, RO/EN și SEO

## Pagini și limbă

- `/`: landing în română, inclusiv când browserul preferă engleza.
- `/en`: landing în engleză, selectabil prin RO/EN în antet.
- `/confidentialitate` și `/en/privacy`: informări în limbile respective, actualizate pentru telefon și subiect.
- Adminul rămâne separat, în română, fără schimbări ale guard-ului, sesiunii sau passkeys.

Limba este stabilită de URL, fără detectare automată sau cookie de preferință care să redirecționeze crawlerii. Selectorul este format din linkuri reale. Textele sunt în `lang/ro.json` și `lang/en.json`; secțiunile și textele dinamice sunt randate/traduse pe server. Scenele, meniul, taburile și feedbackul formularului folosesc limba paginii.

## Formularul din landing

Formularul este vizibil direct în secțiunea `#contact`. Butoanele din landing trimit către el; nu mai există un dialog separat. Funcționează și fără JavaScript.

Toate câmpurile de contact sunt obligatorii: nume complet (2–120 caractere), email (maximum 160), telefon (maximum 40 înainte de normalizare), subiect și mesaj (10–3000). Confirmarea citirii informării privind datele personale este de asemenea obligatorie.

Subiectele sunt definite central în `App\Support\ContactSubjects`, cu valori stabile indiferent de limbă:

1. Participare la programul pilot — selectat implicit.
2. Administrare tehnică pentru hoteluri și pensiuni.
3. Pașaport digital și predarea clădirilor.
4. Administrarea unui portofoliu de clădiri.
5. Parteneriate și colaborări.
6. Alte întrebări despre GNOSIX.

Nu există opțiune goală. Backendul refuză un subiect absent, gol sau necunoscut.

Emailul este verificat sintactic cu regulile RFC/strict, iar telefonul cu metadatele `libphonenumber`. Numerele naționale sunt interpretate ca numere românești, în ambele limbi; limba engleză nu presupune că vizitatorul este în Regatul Unit. Numerele din alte țări trebuie să includă prefixul internațional (`+44`, `+49` etc.). Sunt acceptate spații, paranteze și cratime; literele, extensiile și numerele invalide sunt respinse. Telefonul valid este normalizat în format E.164 înainte de email.

Validarea nu confirmă existența căsuței de email, livrabilitatea sau faptul că vizitatorul deține numărul. Nu se efectuează verificare prin SMS sau confirmare email.

Pe lângă validarea server-side există validare nativă în browser, protecție CSRF, honeypot și limită comună de 5 cereri/minut și 20/zi per IP pentru ambele limbi. Erorile și confirmările apar în formular, în limba paginii, cu etichete și mesaje accesibile. La eroare datele sunt păstrate pentru reîncercare; la succes sunt golite și nu se retrimit prin simpla reîncărcare a paginii.

## Configurare email după deploy

SMTP se configurează în **Email & SMTP**. Toate mesajele folosesc managerul SMTP global. Activarea formularului și destinatarul se configurează în **GNOSIX · Site & contact**, fără variabile de produs în `.env` și fără `config:cache`. Destinatarul inițial este `raduta.alex@gmail.com` și poate fi schimbat numai de administrator.

Emailul primit include numele, emailul, telefonul normalizat, subiectul, mesajul și limba paginii; `Reply-To` este emailul validat al solicitantului. Mesajul intern către administrator are etichete în română. Nu se trimite răspuns automat vizitatorului și nu se salvează o bază de date cu cereri.

La instalare nouă contactul este oprit. Dacă SMTP sau formularul sunt oprite, câmpurile rămân vizibile, dezactivate, cu un mesaj explicativ; backendul refuză trimiterea. Valorile vechi din mediu sunt importate o singură dată la migrare; ulterior nu mai controlează aplicația. Vezi [SYSTEM-SETTINGS.md](SYSTEM-SETTINGS.md).

POST-urile merg la `/contact`, respectiv `/en/contact`. `/program-pilot` rămâne alias compatibil pentru rutare, dar folosește noua schemă obligatorie de câmpuri; formularele vechi trebuie reîncărcate după deploy.

## SEO pregătit pentru lansare

- HTML `lang` și header `Content-Language` corespunzătoare.
- Title și description localizate; fiecare pagină are canonical către propria versiune, fără parametri de tracking.
- `hreflang="ro"`, `hreflang="en"` și `x-default` cu legături reciproce; româna este fallback.
- Sitemap XML cu cele patru pagini și alternativele fiecăreia, fără rute POST sau admin.
- Open Graph și Twitter Card cu titlu/descriere localizate și imagini distincte RO/EN, 1200×630.
- JSON-LD `Organization`, `WebSite`, `WebPage`, fără prețuri, testimoniale sau evaluări inventate.
- Conținut server-rendered, un H1 per pagină, linkuri navigabile și resurse locale compilate prin Vite.
- Adminul rămâne `noindex` și este exclus din robots când indexarea publică este activă.

Pe staging păstrează indexarea oprită din **GNOSIX · Site & contact**. Pe domeniul final, după configurarea HTTPS/SMTP și verificarea conținutului, activează contactul și indexarea din același tab și verifică adresa canonică. Nu este necesară refacerea cache-ului Laravel pentru aceste schimbări.

Rulează `php artisan gnosix:check --launch`. Verifică `/robots.txt`, `/sitemap.xml`, `/`, `/en` și primirea reală a unui mesaj în Gmail. Poți trimite sitemap-ul în Google Search Console după verificarea proprietății. Codul SEO-ready nu garantează indexarea sau o anumită poziție în căutări.

## Validare efectuată

56 de teste / 354 de aserțiuni au trecut, inclusiv câmpuri obligatorii, selecția implicită din șase subiecte, emailuri/telefoane invalide, normalizare, feedback localizat, erori SMTP, canonical/hreflang, sitemap, JSON-LD și regresiile admin/passkey. Au fost verificate în browser selectorul RO/EN, contactul în ambele limbi, respingerea unui telefon invalid, livrarea către un SMTP sink local și lipsa overflow-ului la 390 px. Niciun mesaj de test nu a fost trimis pe internet.

Referințe: [Google Search Central — versiuni localizate](https://developers.google.com/search/docs/specialty/international/localized-versions), [libphonenumber pentru PHP](https://giggsey.github.io/libphonenumber-for-php/).
