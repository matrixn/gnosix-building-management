# Genesis 005 — audit înainte de implementare

Baseline: main d2af588, Laravel 13 / PHP 8.4, Blade/Vite, Node 24. Identitatea client folosește User/web, separat de PlatformAdmin; PropertyPolicy și CapabilityService aplică tenantul/granturile. Nu se introduce un al doilea sistem de autentificare sau Vue.

Building Studio păstrează UUID-uri, ciorne, revizii și afirmații manuale append-only. Spațiile și unitățile de cazare sunt independente. Ștergerea logică restaurează din snapshot; purjarea explicită din coș elimină datele într-o tranzacție. Evidence trebuie integrat în ambele fluxuri și în protecția accesului după ștergere.

Nu există încă stocare de documente, extrageri sau review. Capabilitățile evidence sunt placeholders; Hospitality rămâne singurul profil activ. Setările de produs și SMTP sunt în PlatformSetting criptat; configurarea AI va folosi aceeași administrare, nu GNOSIX_* în mediu. Tabela Laravel jobs există; extracția va folosi o conexiune database dedicată, cu timeout/retry coerente.

163 teste / 1561 aserțiuni în baseline; SQLite pentru suita automată, build Vite și verificări în browser. PDF.js este verificat în upstream Mozilla și registrul npm: 6.5.438 cere Node >=22.13 sau >=24, compatibil cu Node 24 local. Preview-ul va încărca parserul separat, fără execuție de JavaScript din PDF. Adapterul AI folosește Responses, documentat oficial, fără tool calls sau acces arbitrar la URL-uri din documente. Nicio cheie sau transmitere externă nu este necesară pentru fluxul manual.
