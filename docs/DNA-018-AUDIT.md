# GNOSIX 0.18 — audit inițial

Baseline main fd03359, arbore curat; Laravel 13 / Blade / Vite, PHP 8.4 local, fără Vue sau graph DB. Au fost inspectate README, Asset Registry, Maintenance 016, Field 017, Genesis 005, Forge, schema, politicile, serviciile și testele existente.

Surse de adevăr reutilizate: proprietate → clădiri → niveluri → spații, assets building/floor/space, assets.system_id și asset_served_spaces. Adaptorul SQL expune relațiile fără a le copia. Apartenența la sistem nu implică dependență funcțională. `serves` se scrie tot în pivotul existent; metadata DNA nu înlocuiește pivotul. Building/floor/space/asset au UUID stabil; Property nu are UUID, deci primește unul prin migrare aditivă/backfill fără modificarea ID-urilor existente.

Structuri noi justificate: tipuri custom de relație cu semantică imuabilă, relații funcționale suplimentare și metadata pentru serves, revizii append-only, referințe la aceleași versiuni/afirmații Evidence și decizii asupra observațiilor deterministe. Autoritate User/web + PropertyPermissions/CapabilityService; invitațiile Field nu acordă acces DNA. Originalele Evidence rămân private și nu sunt transmise către AI.

Cytoscape.js a fost ales pentru import ES module, selecție, pan/zoom și layout inclus fără framework nou. Sigma folosește WebGL/Graphology, potrivit grafurilor masive, dar nu există nevoie de 10.000 noduri simultane. Canvasul nu este alternativa accesibilă: implementarea oferă separat navigare/listă/formulare native. Bundle-ul și limitele de explorare vor fi măsurate înaintea livrării.
Surse primare: https://js.cytoscape.org/ și https://www.sigmajs.org/concepts/rendering/ .
