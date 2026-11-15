@extends('admin.layout')
@section('content')
<div class="page-heading"><span class="eyebrow">CONFIGURAȚIA GLOBALĂ GNOSIX</span><h1>Setările platformei.</h1><p>Infrastructura din spatele fiecărei experiențe.</p></div>
<nav class="settings-tabs" aria-label="Secțiuni setări">
    @foreach (['general' => 'General', 'smtp' => 'Email & SMTP', 'security' => 'Securitate & passkeys'] as $key => $label)
    <a href="{{ route('admin.settings', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
@if ($tab === 'general')
<section class="panel"><div class="panel-heading"><span class="panel-icon">◇</span><div><h2>Un spațiu pentru platformă.</h2><p>Identitatea și configurația instanței GNOSIX.</p></div><span class="badge">PLATFORM ADMIN</span></div>
<dl class="definition-list"><div><dt>Aplicație</dt><dd>{{ config('app.name') }}</dd></div><div><dt>Adresă</dt><dd>{{ config('app.url') }}</dd></div><div><dt>Mediu</dt><dd>{{ app()->environment() }}</dd></div><div><dt>Cont</dt><dd>Administrator al platformei GNOSIX</dd></div><div><dt>Email sistem</dt><dd>SMTP centralizat · {{ $smtpSaved ? 'Configurat în admin' : 'Configurare inițială din mediu' }}</dd></div></dl></section>
<section class="context-panel"><h3>Administrarea clienților este un spațiu diferit.</h3><p>Setările de aici sunt globale. Conturile viitorilor administratori de clădiri vor aparține aplicației pentru clienți, cu propriile permisiuni și date.</p></section>
@elseif ($tab === 'smtp')
<section class="panel"><div class="panel-heading"><span class="panel-icon">↗</span><div><h2>Email & SMTP</h2><p>O singură configurație pentru toate emailurile sistemului.</p></div><span class="badge">{{ $smtpSaved ? 'DIN ADMIN' : 'DIN MEDIU' }}</span></div>
<p class="helper">Setările salvate aici au prioritate față de valorile SMTP din mediu. Parola rămâne criptată și nu este afișată. Modificarea necesită o autentificare din ultimele 10 minute.</p>
<form action="{{ route('admin.smtp.update') }}" method="POST" class="form-grid">
    @csrf @method('PUT')
    <label class="span-two">Server SMTP<input name="host" required maxlength="253" value="{{ old('host', $smtp['host']) }}" placeholder="smtp.furnizor.ro" autocomplete="off"></label>
    <label>Port<input name="port" type="number" required min="1" max="65535" value="{{ old('port', $smtp['port']) }}"></label>
    <label>Criptare<select name="scheme"><option value="smtps" @selected(old('scheme', $smtp['scheme']) === 'smtps')>TLS implicit (de obicei 465)</option><option value="smtp" @selected(old('scheme', $smtp['scheme']) === 'smtp')>STARTTLS (de obicei 587)</option></select></label>
    <label>Utilizator SMTP<input name="username" maxlength="255" value="{{ old('username', $smtp['username']) }}" autocomplete="off"></label>
    <label>Parolă SMTP<input type="password" name="smtp_password" maxlength="1024" autocomplete="new-password" placeholder="{{ $hasPassword ? 'Salvată — lasă gol pentru a o păstra' : 'Introdu parola SMTP' }}"></label>
    @if ($hasPassword)<label class="check-label span-two"><input type="checkbox" name="clear_password" value="1"><span>Șterge parola salvată (pentru un relay fără autentificare; golește și utilizatorul).</span></label>@endif
    <label>Email expeditor<input type="email" name="from_address" required maxlength="255" value="{{ old('from_address', $smtp['from_address']) }}" placeholder="pilot@gnosix.zion3d.ro"></label>
    <label>Nume expeditor<input name="from_name" required maxlength="100" value="{{ old('from_name', $smtp['from_name']) }}"></label>
    <div class="form-actions span-two"><p class="fine">Salvarea nu trimite un email. În producție conexiunea trebuie să fie criptată.</p><button class="button">Salvează configurația <span>↗</span></button></div>
</form></section>
<section class="panel test-panel"><div><h3>Verifică livrarea.</h3><p>Trimite un mesaj folosind setările salvate, exclusiv către {{ auth('platform_admin')->user()->email }}. Verifică și Spam.</p></div><form method="POST" action="{{ route('admin.smtp.test') }}">@csrf<button class="button secondary">Trimite email de test ↗</button></form></section>
@else
<section class="panel"><div class="panel-heading"><span class="panel-icon">⌘</span><div><h2>Passkeys</h2><p>Autentificare cu dispozitivul tău, fără introducerea parolei.</p></div><span class="badge">WEBAUTHN</span></div>
<p class="helper">Folosește Windows Hello, Touch ID, un manager de passkeys sau o cheie de securitate compatibilă. Înregistrează o cheie de rezervă pe un al doilea dispozitiv.</p>
<form class="passkey-registration form-row" data-passkey-register data-options="{{ route('admin.passkeys.register-options') }}" data-submit="{{ route('admin.passkeys.store') }}">
    <label>Nume pentru passkey<input name="passkey_name" required maxlength="100" placeholder="Laptop personal / cheie de rezervă"></label><button class="button">Adaugă passkey <span>+</span></button>
</form><p class="passkey-feedback" role="status" aria-live="polite"></p>
<div class="passkey-list">@forelse ($passkeys as $key)<div class="passkey-item"><div><strong>{{ $key->name }}</strong><p>Adăugat {{ $key->created_at->format('d.m.Y') }} · {{ $key->last_used_at ? 'Ultima folosire '.$key->last_used_at->format('d.m.Y H:i') : 'Nefolosit încă' }}</p></div><form method="POST" action="{{ route('admin.passkeys.destroy', $key->id) }}">@csrf @method('DELETE')<button class="button ghost small">Revocă</button></form></div>@empty<p class="empty-state">Nu ai înregistrat încă un passkey. Primul se adaugă de aici.</p>@endforelse</div>
</section>
<section class="panel"><h3>Confirmă identitatea pentru modificări.</h3><p class="helper">După 10 minute, parola trebuie reconfirmată înainte de salvarea SMTP sau de administrarea passkey-urilor. O nouă autentificare cu passkey confirmă și ea identitatea.</p><form method="POST" action="{{ route('admin.confirm') }}" class="form-row">@csrf<label>Parola administratorului<input name="password" type="password" required autocomplete="current-password" maxlength="255"></label><button class="button secondary">Reconfirmă parola ↗</button></form></section>
<section class="context-panel"><h3>Configurare passkey pe Forge</h3><ol><li>Activează HTTPS pentru domeniul final.</li><li>Setează <code>APP_URL</code> și <code>PASSKEY_ORIGIN</code> la <code>https://gnosix.zion3d.ro</code>, iar <code>PASSKEY_RP_ID</code> la <code>gnosix.zion3d.ro</code>.</li><li>Refă cache-ul configurației, apoi autentifică-te cu parola și adaugă un passkey aici.</li><li>Deloghează-te și verifică butonul „Autentificare cu passkey”.</li></ol><p>Cheile sunt legate de domeniu. Păstrează parola ca opțiune de recuperare; amprenta și PIN-ul nu ajung pe server.</p></section>
@endif
@endsection
