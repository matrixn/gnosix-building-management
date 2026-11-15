# Genesis 002 — audit înainte de modificări

Baseline: `56773c2`, Laravel 13.42 / PHP 8.4 / Blade + Vite; fără Vue. User, autentificare/verificare/resetare, locale RO/EN, policy pentru identitatea proprie, sesiuni versionate și shell-ul Genesis sunt reutilizabile. Adminul este separat prin model, guard și cookie; SMTP este centralizat. Nu există tabele de organizații, membri, clădiri sau dovezi, deci nu sunt recreate resurse existente.

Task 001 stochează `User.profile_type` ca perspectivă personală (`owner`, `manager`, `technical`), nu ca industrie sau rol autorizat. Acest câmp rămâne compatibil; Task 002 introduce profilul organizației separat. Utilizatorii existenți nu primesc automat organizații fictive. Fluxul nou de onboarding creează organizația la confirmare, iar shell-ul vechi oferă o intrare pentru configurare.

Task 001 folosește fișiere sau baza de date pentru sesiuni, rate limiting, CSRF, no-store și verificare obligatorie. Aceste controale rămân aplicate tuturor rutelor organizațiilor. Policy-ul organizației și serviciul de capabilități verifică apartenența chiar dacă ID-ul organizației este cunoscut. Contextul activ este o preferință validată, nu autorizație.

Nu este necesar un framework de tenancy sau un nou sistem de autentificare. Se adaugă exact cele șase tabele cerute printr-o migrare aditivă. Registrul versionat definește contracte de software; configurările organizațiilor se salvează în DB din interfață, fără variabile GNOSIX în mediu. Proprietățile recomandate sunt indicii de onboarding, fără constrângeri de proprietate sau chei străine către industrie.

README era o listă descriptivă, fără checkbox-uri. Este convertit într-un checklist de livrare, cu separarea explicită a demonstrațiilor, infrastructurii de capabilități și modulelor operaționale viitoare. Regula este păstrată în AGENTS.md pentru taskurile următoare.
