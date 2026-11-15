# Genesis 001 — identitate, autentificare și experiență

Auditul inițial și justificarea arhitecturii sunt în [GENESIS-001-AUDIT.md](GENESIS-001-AUDIT.md). Experiența folosește Laravel/Blade/Vite existente; nu sunt adăugate dependențe Composer sau NPM.

## Acces și funcționare

Intrarea clientului este `/app/login`, disponibilă și prin **Contul meu** din footerul landingului. Înregistrarea este la `/app/register`. Rutele sunt aceleași pentru RO/EN; selectorul `?lang=ro|en` stabilește preferința sesiunii. Limba salvată în cont este folosită la autentificări viitoare și pentru notificări. Landingul păstrează rutele SEO `/` și `/en`, cu româna implicită.

1. La înregistrare se validează numele, emailul, parola confirmată și citirea informării. Emailul este normalizat; parola este hash-uită. Contul primește o sesiune nouă și un email de confirmare prin SMTP-ul platformei.
2. Conturile neverificate pot doar vedea ecranul de confirmare, retrimite emailul și ieși. Verificarea necesită identitatea potrivită, hashul adresei și semnătura temporară. Linkurile expiră după 60 minute. Un link valid deschis fără sesiune poate fi reluat după login; alte adrese de redirect nu sunt acceptate.
3. Prima autentificare este păstrată separat de ultima. După verificare, utilizatorii fără onboarding complet intră în introducerea Genesis și aleg perspectiva Hospitality: proprietar/hotelier, administrator/manager sau echipă tehnică. Aceasta nu este un rol de autorizare.
4. Utilizatorii care au terminat onboardingul revin la `/app`. Shell-ul protejat oferă navigare, profil și securitate; modulele de clădiri nu sunt simulate ca funcționale.
5. `/app/account` permite actualizarea numelui și limbii. Schimbarea emailului cere parola curentă, anulează verificarea și celelalte sesiuni, apoi solicită confirmarea noii adrese.
6. Schimbarea parolei cere parola curentă. Dispozitivul curent primește o sesiune nouă; celelalte sunt invalidate. Comanda de deconectare a celorlalte dispozitive cere tot parola curentă.
7. Recuperarea folosește brokerul Laravel `users` și tabelul existent `password_reset_tokens`. Răspunsul cererii este neutru; tokenurile sunt hash-uite, limitate în timp, de unică folosință. Resetarea invalidează sesiunile și solicită login nou, fără autentificare automată.

Parolele noi au minimum 12 caractere. Validarea limitează și lungimea la 72 octeți pentru a preveni trunchierea cu bcrypt. Nu este introdus un serviciu extern de verificare a parolelor și nici un cookie persistent „remember me”.

## Limite de acces și securitate

- Se reutilizează `User` / provider `users` / guard `web`, separat de PlatformAdmin / guard `platform_admin` / cookie `/admin`. Nu se modifică parolele, passkey-urile sau drepturile administratorilor existenți.
- `CustomerSession` verifică `auth_version` la fiecare cerere autentificată. Schimbarea parolei, resetarea, schimbarea emailului sau revocarea sesiunilor cresc versiunea. Sesiunile vechi sunt respinse la următoarea cerere, pentru stocare file/database/Redis, fără ștergerea sesiunilor adminului.
- `UserPolicy` limitează modificarea la identitatea proprie. ID-ul, verificarea, versiunea de sesiune și tipul profilului nu sunt preluate din payloaduri arbitrare.
- CSRF rămâne activ pentru toate modificările. Loginul regenerează sesiunea, logoutul o invalidează și reface tokenul CSRF. Parolele, confirmările și tokenurile nu sunt flash-uite în sesiunea de erori.
- Login: 5 încercări/minut/adresă și 15/minut/IP. Înregistrare: 3/minut și 10/oră/IP. Resetare: 3/minut/adresă și 5/minut/IP, plus throttle-ul brokerului. Retrimitere verificare: 1/minut/utilizator. Modificări sensibile: 10/minut/utilizator.
- Paginile de identitate au `no-store, private`, `noindex,nofollow`, referrer `no-referrer`; nu sunt în sitemap și sunt excluse în robots. Linkurile emailurilor folosesc `APP_URL`, nu Host-ul cererii sau adresa canonică SEO editabilă în admin.
- Producția verifică debug oprit, HTTPS, sesiuni server-side, cookie secure/HTTP-only/SameSite și criptarea sesiunilor. Folosește TLS Forge și configurația existentă de producție.
- Notificările folosesc `PlatformMailManager`, deci SMTP-ul configurat în administrare. La eroare nu se afișează stacktrace, credențiale sau configurație SMTP. Înregistrarea păstrează contul neverificat și permite retrimiterea. Recuperarea afișează același răspuns pentru adrese inexistente și erori de livrare; logul conține doar categoria și clasa excepției.

## Experiență și componente

Layoutul `layouts/identity`, componentele `identity/logo`, `field` și `button`, plus stilurile și modulul JS dedicate sunt reutilizabile. Textele și validările sunt în `lang/ro/identity.php`, `lang/en/identity.php` și cataloagele `validation.php`.

Introducerea are logo reveal, grilă arhitecturală și progres animat, dar nu blochează utilizatorul cu un cronometru. Butonul Continuă mută focusul la titlul selecției profilului. Săgețile, indicatorii și formele păstrează paleta bleumarin/violet/lime. Câmpurile au etichete, autocomplete și erori asociate; parolele pot fi afișate temporar; formularele au stare de trimitere și recuperare din back/forward cache. Explicația profilurilor este un disclosure nativ accesibil din tastatură.

Fără JavaScript, autentificarea și formularele funcționează normal și selecția de profil este direct accesibilă. `prefers-reduced-motion` oprește animațiile. Pagina de confidențialitate explică acum și datele contului.

## Migrare și deploy Forge

O singură migrare aditivă completează `users` cu `locale`, `profile_type`, `first_signed_in_at`, `last_signed_in_at`, `onboarding_completed_at`, `auth_version`. Nu recreează tabelele users/sessions/reset și nu modifică hashurile sau verificările existente. Utilizatorii existenți se pot autentifica; parcurg onboardingul la prima intrare în noul flux. Sesiunile vechi care nu au versiune cer reautentificare.

Scriptul Forge existent execută deja pașii necesari:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan config:cache
php artisan migrate --force
php artisan route:cache
php artisan view:cache
php artisan gnosix:check --production
```

Nu este necesară o nouă comandă de inițializare sau rerularea `gnosix:admin` pentru administratorii existenți. Configurează și testează SMTP din administrare înainte de a invita utilizatori. `APP_URL` trebuie să fie domeniul HTTPS final; linkurile create în mediul local nu se folosesc în producție. Nu se adaugă variabile GNOSIX în `.env`.

Nu folosi rollback automat al migrației: ar elimina noile informații de identitate. Păstrează baza de date și APP_KEY în backup. Pentru verificarea în producție folosește un cont propriu de test: înregistrare, inbox/Spam, confirmare, onboarding, logout/login și resetare. Livrarea locală nu dovedește livrarea în inbox pe VPS.

## Verificare executată

- Baseline înainte de modificări: 57 teste / 378 aserțiuni.
- Suita completă după implementare: 79 teste / 575 aserțiuni, inclusiv 22 teste de identitate / 197 aserțiuni. Acoperă validarea, CSRF, limitarea încercărilor, separarea guardurilor, verificarea semnată/expirată/pentru alt cont, resetarea de unică folosință, cookie/sesiune, autorizarea profilului, invalidarea sesiunilor și randarea notificărilor prin contractul MailChannel.
- `npm run build`, verificarea sintaxei PHP/JS, `composer validate --no-check-publish`, `route:cache` și `view:cache` reușite.
- `gnosix:check --production` reușit cu configurație cache-uită de producție și bază SQLite izolată local; nu este verificare a VPS-ului.
- Browser: înregistrare, retrimitere și confirmare din email SMTP local, onboarding, profil, schimbare limbă, schimbare parolă, revocare sesiuni, logout, resetare din email local și revenire directă în shell. Verificări la 390 px, aproximativ 773 px și 1440 px, fără overflow. Login fără JavaScript, selecție profil vizibilă fără JS, reducere motion verificată și zero erori în consolă.

## Limite asumate

Identitatea este globală; organizațiile, membership-urile, politicile de tenant, invitațiile și resursele clădirilor vor aparține următoarelor etape. Selectarea unui profil nu acordă acces la date de tenant. Nu există încă listă nominală a dispozitivelor; este disponibilă revocarea tuturor celorlalte sesiuni, compatibilă cu storage-ul existent. Passkeys pentru clienți, social login și 2FA nu sunt activate; adminul păstrează passkeys existente. Nu a fost făcut deploy pe VPS sau trimis email extern.

## Fișiere modificate

```text
README.md
app/Console/Commands/CheckLanding.php
app/Http/Controllers/Identity/AccountController.php
app/Http/Controllers/Identity/AuthenticationController.php
app/Http/Controllers/Identity/PasswordController.php
app/Http/Controllers/Identity/VerificationController.php
app/Http/Controllers/Identity/WorkspaceController.php
app/Http/Middleware/CustomerLocale.php
app/Http/Middleware/CustomerOnboarded.php
app/Http/Middleware/CustomerSession.php
app/Http/Middleware/ResponseHeaders.php
app/Models/User.php
app/Notifications/CustomerResetPassword.php
app/Notifications/CustomerVerifyEmail.php
app/Policies/UserPolicy.php
app/Providers/AppServiceProvider.php
app/Support/CustomerIdentity.php
bootstrap/app.php
config/session.php
database/migrations/2026_11_15_180000_add_customer_identity_fields_to_users.php
docs/DEPLOY-FORGE.md
docs/GENESIS-001-AUDIT.md
docs/GENESIS-001.md
lang/en.json
lang/en/identity.php
lang/en/validation.php
lang/ro/identity.php
lang/ro/validation.php
resources/css/identity.css
resources/js/identity.js
resources/views/components/identity/button.blade.php
resources/views/components/identity/field.blade.php
resources/views/components/identity/logo.blade.php
resources/views/errors/403.blade.php
resources/views/errors/419.blade.php
resources/views/errors/429.blade.php
resources/views/errors/500.blade.php
resources/views/identity/account.blade.php
resources/views/identity/auth-frame.blade.php
resources/views/identity/dashboard.blade.php
resources/views/identity/error.blade.php
resources/views/identity/forgot.blade.php
resources/views/identity/login.blade.php
resources/views/identity/onboarding.blade.php
resources/views/identity/register.blade.php
resources/views/identity/reset.blade.php
resources/views/identity/verify.blade.php
resources/views/landing/footer.blade.php
resources/views/layouts/identity.blade.php
resources/views/mail/identity.blade.php
resources/views/privacy.blade.php
routes/identity.php
routes/web.php
scripts/package-release.py
tests/Feature/CustomerIdentityTest.php
vite.config.js
```
