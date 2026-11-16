# GNOSIX 0.15 — audit înainte de implementare

Baseline main 477c637 (0.14.0), arbore curat. Laravel 13 / PHP 8.4 local, Blade/Vite, PDF.js, fără Vue/Inertia. Composer/package și documentația README, GENESIS-005, AI-PROVIDERS au fost inspectate. Nu este necesară o dependență nouă.

Modelele și migrațiile existente separă Organization, Property, Building/Floor/Space și AccommodationUnit. Building are SoftDeletes; nivelurile/spațiile arhivate au global scope current. UUID-urile spațiale sunt stabile și reviziile declarațiilor sunt append-only. Nu există registru de active. Relațiile noi trebuie să accepte localizare incompletă și proprietăți cu mai multe clădiri, fără restricții după tipul proprietății.

User/web verificat, customer.session/customer.organization, PropertyPolicy/PropertyPermissions și CapabilityService se reutilizează. Owner/admin/manager și granturile viewer/editor pe proprietate rămân autoritatea; platform_admin rămâne separat. Navigația vine din registrul de capabilități. Hospitality este singurul profil activ, fără a lega categoria activului de industria organizației.

Evidence Engine are documente/versiuni/afirmații cu hash și proveniență imuabile, conflicte și review tranzacțional. Asocierea cu un activ trebuie să fie explicită, fără mutarea/suprascrierea subiectului original al afirmației. Aplicarea unei valori va fi separată de declarația manuală, verificată față de revizia sursei și conflictele curente. Importul reutilizează afirmațiile existente și nu face apeluri AI.

Building Studio este un modul JS separat cu inspector de cameră și UUID-uri; activele asociate se vor încărca la cerere, paginat, nu în snapshotul structurii. Listele, selectoarele și sugestiile vor avea filtre/limite pe server. Fișierele foto folosesc discul privat și outbox-ul de cleanup existent, cu rute autorizate și semnate.

Baseline: 190 teste / 1731 aserțiuni. Sunt necesare teste noi pentru tenant/granturi/capabilități, locații și sisteme comune, dovezi/conflicte, seriale duplicate/lipsă, archive/restore, concurență, retenție și 10.000 active. Verificarea UI va folosi bază locală izolată, desktop/mobil; nu se modifică VPS-ul sau conturile utilizatorului.

Constatare în timpul verificării: comparația generică Evidence convertea și stringuri numerice, ceea ce echivala seriile `00123` și `123`. Implementarea 0.15 păstrează comparația textuală pentru value_type string și comparația numerică pentru tipurile numerice; regresia este testată. Arhitectura Evidence și schema sa existentă sunt păstrate.
