# Surse AI și modele gratuite — 0.14.0

## Alegerea sursei

Setări → Evidence Engine & AI → Configurează furnizorul → Deschide configurația. Acest pas este read-only: nu schimbă sursa activă și nu contactează API-ul. Introdu modelul, cheia dacă este necesară și opțiunile, apoi salvează. Activarea extracției are checkbox separat. Cheia goală păstrează cheia acelui furnizor; Șterge cheia afectează doar sursa selectată. Configurațiile sunt criptate în PlatformSetting, fără variabile GNOSIX noi. Configurația OpenAI veche este adoptată la citire/salvare fără migrare de schemă sau pierdere de cheie.

| Sursă | Formate în această integrare | Gratuitate și condiții |
| --- | --- | --- |
| OpenAI Responses | PDF, PNG, JPEG | Tarif și limite ale contului existent |
| Google Gemini generateContent | PDF, PNG, JPEG | Free tier numai pentru modele/conturi eligibile, cu cote; cheia Gemini este necesară |
| OpenRouter Chat Completions | PNG, JPEG | openrouter/free sau un model :free, cu cheie OpenRouter; disponibilitate și limite variabile |
| Ollama local /api/chat | PNG, JPEG | Fără tarif API către un furnizor; necesită server, memorie/CPU/GPU și respectarea licenței modelului |

Nu orice model acceptă imagini și JSON Schema. Alege un model cu ambele capabilități. Identificatorii sunt editabili și nu există o listă statică prezentată ca sigur disponibilă. Nu este necesară instalarea unui pachet PHP/NPM nou.

### Gemini

Obține cheia din contul Google AI Studio și verifică modelul, țara, cota, planul de facturare și termenii de procesare înainte de activare. Introdu identificatorul exact, fără prefixul models/. API-ul folosit este generateContent cu inlineData și responseJsonSchema; originalul PDF/imagine este trimis integral, numai după acordul per versiune. Nu se creează fișiere persistente prin Files API. GNOSIX nu poate garanta lipsa costului unui cont Gemini cu billing activ; verifică bugetele/quotele în contul Google. Tier-urile gratuite pot avea politici diferite de utilizare a datelor.

### OpenRouter

Alege `openrouter/free` sau un identificator de model vision terminat în `:free`. „Doar modele gratuite” este bifat implicit și verificat și pe server. Nu se trimit tools, pluginuri, modele de fallback sau cereri către o alternativă plătită la eroare. Se cer structured outputs și require_parameters, iar allow_fallbacks este false. Routerul `openrouter/free` poate alege un model gratuit compatibil; un identificator concret :free oferă un control mai precis asupra modelului. Prețurile/disponibilitatea și politicile furnizorilor downstream se verifică în cont. Opțiunea poate fi dezactivată explicit de platform admin pentru utilizarea unui model plătit.

PDF-urile nu sunt trimise către pluginuri de parsare (care pot avea costuri/terți suplimentari). Pentru PDF folosește Gemini/OpenAI sau exportă paginile relevante ca PNG/JPEG și încarcă-le. Uploadul privat și review-ul manual rămân disponibile indiferent de sursă.

### Ollama local pe Forge

Instalează Ollama din sursa oficială pe același server cu workerul și descarcă manual un model vision compatibil cu structured outputs, verificând licența și resursele necesare. Aplicația nu instalează servicii, nu descarcă modele, nu expune porturi și nu configurează un GPU. Folosește `ollama list` pentru numele exact. Nu există cheie API în formular pentru Ollama.

Conexiunea este fixă `http://127.0.0.1:11434`; nu se acceptă endpointuri arbitrare, proxy din mediu sau redirecturi. Ollama trebuie să asculte doar local. Dezactivează cloud în configurația infrastructurii Ollama `~/.ollama/server.json` pentru contul care rulează serviciul:

```json
{"disable_ollama_cloud": true}
```

Repornește serviciul și verifică logul „Ollama cloud disabled: true”. Aceasta este configurația serviciului extern Ollama, nu o setare de produs în .env Laravel. GNOSIX verifică /api/show înainte de /api/chat: model_info trebuie să existe, capabilities trebuie să includă vision, iar remote_host/remote_model să lipsească. Tagurile cloud și aliasurile remote sunt refuzate înainte de transmiterea imaginii. Limita totală rămâne compatibilă cu timeout job 120 secunde (metadata 10, chat 90); modelele prea lente eșuează și se pot relua. Un VPS mic fără GPU poate să nu fie potrivit.

## Fiabilitate și confidențialitate

Nu există fallback automat între surse; documentul nu este transmis în secret altui operator. Modificarea sursei/modelului după autorizarea jobului oprește jobul vechi înainte de apel. Fiecare cerere de extracție păstrează providerul/modelul solicitat și consimțământul. Schimbarea cheii aceluiași furnizor nu schimbă destinația. În OpenRouter modelul solicitat poate fi routerul gratuit, nu identitatea exactă a modelului downstream.

Erorile de cotă/429, model incompatibil, refuz, output trunchiat/invalid sau indisponibilitate nu devin succes și nu produc afirmații parțiale. Rezultatele tuturor furnizorilor trec prin aceeași validare tipizată, conflicte și review uman. Rezultatele nu sunt certificate; AI nu suprascrie identitatea declarată. Datele și cheile nu sunt incluse în mesajele publice de eroare. Nicio cheie/API live nu a fost folosită la implementare.

## Implementare și verificare

`Providers` definește sursele/formatele; `ProviderExtractor` selectează adapterul; `ExtractionContract` centralizează schema/instrucțiunile și decodarea. `ProviderInput` verifică hash, configurare, format și răspuns HTTP. `GeminiExtractor`, `OpenRouterExtractor`, `OllamaExtractor` completează adapterul OpenAI existent. Settings, admin controller/view, EvidenceController/Engine/job și traducerile aplică aceeași configurație și compatibilitate. Nu sunt migrații/dependențe noi.

Teste: EvidenceProvidersTest verifică payloaduri reale prin Http::fake, chei distincte/criptare, adoptarea configurației vechi, modele gratuite, refuz PDF, refuz alias remote, invalidarea consimțământului vechi, erori de cotă fără fallback și validarea admin inclusiv input cu tip incorect. Testele existente verifică workerul database real, idempotence, tenant/review/retention. Browser local cu bază izolată: selecție fără activare, refuz model plătit în modul gratuit, salvare openrouter/free, formular Ollama fără cheie, mobil 390 px fără overflow și consolă fără erori/warnings. Nu reprezintă un test al serviciilor externe live.

Rezultat final: **190 teste PHP / 1731 aserțiuni trecute**; cele nouă teste noi pentru furnizori au 61 aserțiuni. Build production, Pint și cache-urile rutelor/view-urilor au trecut.

Comenzi: `php vendor/bin/phpunit`, `npm run build`, `php vendor/bin/pint --test` pe fișierele modificate, `php artisan route:cache`, `php artisan view:cache`. Pentru Windows se păstrează OPENSSL_CONF documentat în GENESIS-005. Deploy-ul Forge existent compilează și repornește workerii. Configurează separat serviciul Ollama numai dacă alegi sursa locală.

## Surse oficiale verificate

- [Gemini billing/free tier](https://ai.google.dev/gemini-api/docs/billing?hl=en), [pricing](https://ai.google.dev/gemini-api/docs/pricing), [generateContent](https://ai.google.dev/api/generate-content), [PDF](https://ai.google.dev/gemini-api/docs/document-processing).
- [OpenRouter free models](https://openrouter.ai/collections/free-models), [structured outputs](https://openrouter.ai/docs/guides/features/structured-outputs), [image inputs](https://openrouter.ai/docs/guides/overview/multimodal/image-understanding).
- [Ollama chat](https://docs.ollama.com/api/chat), [structured outputs](https://docs.ollama.com/capabilities/structured-outputs), [dezactivare cloud](https://docs.ollama.com/faq#how-do-i-disable-ollamas-cloud-features), [OpenAPI oficial](https://github.com/ollama/ollama/blob/main/docs/openapi.yaml).
