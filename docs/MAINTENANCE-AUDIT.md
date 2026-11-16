# GNOSIX 0.16 — audit inițial

Baseline main 80f96d3, arbore curat. README, Smart Asset Registry, Genesis 005, deploy Forge, modelele, migrațiile, politicile, rutele, componentele Blade/JS și testele existente au fost inspectate. Laravel 13 / PHP 8.4, Blade / Vite / PDF.js, fără Vue/Inertia. Nu există încă domeniu operațional de mentenanță; hospitality.maintenance este un placeholder.

Se reutilizează User/web verificat, organizații/membership/granturi PropertyPermissions, capabilități, Asset și UUID spațiale. Membrii pot raporta în proprietățile vizibile; editorii coordonează; responsabilul nominal poate executa, iar verificarea este rezervată managerului cu grant de editare. Documentele tehnice generale rămân sub drepturile Evidence existente. Atașamentele explicite ale mentenanței reutilizează versiunile private Evidence, fără acces implicit la celelalte documente.

Jurnalul append-only asset_audits se extinde pentru evenimente operaționale fără activ, evitând un jurnal paralel. Planurile generează ordine tranzacțional cu cheie unică plan/data. Nu se introduc valori presupuse pentru costuri, garanții, diagnoze sau termene legale. Validarea folosește baze izolate, nu resetarea datelor reale.
