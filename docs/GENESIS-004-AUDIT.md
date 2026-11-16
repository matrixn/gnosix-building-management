# Genesis 004 — audit înainte de implementare

Baseline `862cf6d`, arbore curat. Laravel 13.42 / PHP 8.4.14, Blade/Vite 7, fără Vue; User/web și PlatformAdmin separate, SMTP centralizat. Task 003 are properties, buildings (property_id/name), floors (building_id/name/position), spaces (property + building/floor opționale), granturi viewer/editor, ciorne Workspace Studio și configurare centrală. Rutele, politicile, componentele, tema navy/violet/lime și localizarea RO/EN se reutilizează. 115 teste / 958 aserțiuni reprezintă baseline-ul anterior.

Nu se recreează structura existentă. O migrare aditivă atribuie identități stabile și păstrează valorile necunoscute NULL. Unitățile de cazare și paturile individuale se modelează separat de spații. Ciornele declarației sunt separate de ciornele Task 003. Editorul nou și mutațiile vechi vor participa la audit și concurență; eliminarea din structură va arhiva înregistrări, fără ștergere.

Evaluare vizuală: SVG.js (https://svgjs.dev/), Konva (https://konvajs.org/docs/performance/All_Performance_Tips.html), SVG tabindex (https://developer.mozilla.org/en-US/docs/Web/SVG/Reference/Attribute/tabindex). Se alege SVG nativ axonometric + plan/listă semantică. Nu sunt necesare WebGL, dependențe noi sau încărcarea geometriei BIM. Reprezentarea este schematică și nu inventează dimensiuni sau fapte constructive.

Deploy-ul Forge existent compilează frontend-ul și rulează migrarea înainte de activare. Se vor actualiza README, documentația, verificarea instalării și pachetul de release. Nu se modifică VPS-ul sau .env-ul real în timpul verificărilor locale.
