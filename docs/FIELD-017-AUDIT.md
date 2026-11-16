# GNOSIX 0.17 — audit inițial

Baseline main 1b32a5b, arbore curat. README, Asset Registry, Maintenance 016, Genesis 005, Forge și sursele modelelor/migrațiilor/politicilor/rutelor/testelor au fost inspectate. Laravel 13, Blade/Vite, PHP local 8.4; identități User/web și platform_admin separate. Baza 0.16 are 230 teste / 2075 aserțiuni.

QR nu trebuie să fie bearer authorization. Locatorul va fi public/opac, cu UUID intern imuabil separat; toate rezolvările verifică permisiunile curente. Invitațiile externe reutilizează User/web verificat fără membership sau abonament; hash unic, expirare, revocare, scop și consimțământ explicit la răscumpărare. Tokenul este transmis în fragment și POST, nu în cale/query/loguri. Niciun document general Evidence nu devine accesibil implicit.

Se reutilizează WorkOrder/Workflow, versiunile private Evidence și jurnalul append-only asset_audits. Rapoartele și review-urile lor sunt append-only, cu PDF-uri sursă; acceptarea nu aplică automat afirmațiile. Pachetele sunt independente de framework: Endroid QR 6.0.x (PHP >=8.2) și Dompdf >=3.1.6, cu resurse remote/PHP/JS dezactivate. Lock-ul final folosește Endroid 6.0.9 și Dompdf 3.1.6; composer audit nu raportează advisories sau pachete abandonate la livrare.
