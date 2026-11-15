# GNOSIX Genesis 001 — audit înainte de implementare

Audit efectuat pe checkout-ul `main`, după `87aed32`, înainte de modificările de identitate.

- **Runtime:** PHP local 8.4.14, Laravel 13.42.0 (lockfile), Node 24.22.1, Vite 7. Nu există Vue/Inertia în proiect; interfața este Blade cu module JavaScript mici.
- **Autentificare:** providerul `users` și guardul `web` sunt configurate, însă nu există rute de autentificare pentru clienți. Modelul User este cel standard Laravel, fără verificare obligatorie sau onboarding. Tabelele users, password_reset_tokens și sessions există deja.
- **Admin:** PlatformAdmin, provider, guard, cookie cu path `/admin`, password login, reconfirmare și passkeys Spatie sunt implementate separat. Aceste fluxuri trebuie păstrate. Nu se adaugă un al doilea model de client sau un nou sistem de tokenuri.
- **Autorizare:** nu există încă organizații, membri sau resurse de tenant. Genesis 001 poate proteja identitatea proprie și shell-ul, dar nu trebuie să inventeze permisiuni de tenant din selecția de profil.
- **Design:** landing Atlas RO/EN, bleumarin/violet/lime, Manrope local, Blade/Vite, scenarii interactive, motion redus și SEO. Adminul are stiluri dedicate. Componentele pentru client vor reutiliza identitatea vizuală fără încărcarea animațiilor landingului în aplicație.
- **Localizare:** landingul folosește JSON Laravel și middleware PublicLocale. Clientul necesită localizare reutilizabilă prin catalog `identity.php`, preferință pe utilizator și sesiune; publicul trebuie să rămână RO implicit.
- **Mail:** PlatformMailManager impune SMTP inclusiv notificărilor. Creditele SMTP se configurează în admin și sunt criptate. Emailurile de identitate trebuie să utilizeze același transport.
- **Sesiuni:** storage file în exemplul Forge, database implicit Laravel; cookie HTTP-only, SameSite lax, secure/encrypt în producție. Invalidarea identității trebuie să funcționeze pentru ambele stocări și să nu afecteze adminul.
- **Deploy:** Forge instalează Composer fără dev, compilează Vite, rulează config/migrate/route/view cache și verificarea de producție. Se păstrează procesul; noua migrare adaugă numai câmpuri de identitate, fără recrearea tabelelor existente.
- **Teste:** PHPUnit 12; 57 teste / 378 aserțiuni la baseline pentru landing/contact/SEO/admin/passkeys. Nu există teste de client pentru că nu există încă fluxul.

## Decizii

Se reutilizează guardul `web`, brokerul Laravel `users`, notificările și URL-urile semnate Laravel. Blade rămâne tehnologia de prezentare. CSS și JavaScript progresiv sunt suficiente pentru introducerea cinematică; motion-v presupune Vue, absent în proiect. Un UI kit ar introduce un al doilea limbaj vizual. Spatie Permission suportă Laravel 13, dar rolurile organizaționale și tenanturile nu fac parte din această etapă. Passkeys Spatie existente rămân pentru admin; extinderea lor la client, social login și 2FA necesită fluxuri de recuperare și teste suplimentare și nu sunt activate aici.

Surse de compatibilitate consultate: [Laravel authentication](https://laravel.com/framework/docs/13.x/authentication), [verification](https://laravel.com/framework/docs/verification), [passwords](https://laravel.com/framework/docs/passwords), [Motion Vue](https://motion.dev/docs/vue), [Spatie prerequisites](https://spatie.be/docs/laravel-permission/v8/prerequisites). Implementarea folosește și sursa frameworkului instalat pentru API-urile exacte.
