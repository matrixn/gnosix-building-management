# Genesis 003 — audit inițial

Baseline `759424a`: Laravel 13.42 / PHP 8.4, Blade/Vite, User și PlatformAdmin separate, verificare email și sesiuni versionate. Task 002 are organizații, apartenențe owner/manager/member, profil comercial independent de tipul clădirilor, capabilități centralizate și ciorne personale pentru crearea organizației. Nu există încă proprietăți, clădiri sau spații operaționale.

Se reutilizează autentificarea, middleware-ul de tenant, politicile, localizarea și componentele Genesis. Workspace Studio primește ciorne separate per utilizator/organizație, pentru a nu suprascrie onboardingul de identitate/organizație. `manager` rămâne compatibil ca administrator; se adaugă rolul `admin` și granturi viewer/editor per proprietate. Grupurile și entitățile juridice sunt opționale. Nu se impune un număr fix de clădiri sau camere.

Nu sunt necesare Vue, un al doilea sistem de permisiuni sau un pachet de media. Schema condițională este un contract PHP/JSON consumat de server și JS progresiv. Laravel oferă validarea, tranzacțiile și stocarea privată pentru logo-uri raster. GD/fileinfo sunt disponibile local; nu sunt adăugate dependențe. Logo-urile nu acceptă SVG și sunt livrate exclusiv printr-o rută autorizată.

Testul-fixture din Task 002 care crea o tabelă fictivă buildings trebuie adaptat la modelul real nou; dovada păstrării datelor la schimbarea profilului rămâne. Funcțiile operaționale implementate vor fi bifate distinct în README; documentele, mentenanța și activele tehnice viitoare nu vor fi prezentate ca livrate.
