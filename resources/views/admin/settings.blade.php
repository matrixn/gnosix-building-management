@extends('admin.layout')
@section('content')
<div class="page-heading"><span class="eyebrow">CONFIGURAȚIA GLOBALĂ GNOSIX</span><h1>Setările platformei.</h1><p>Infrastructura din spatele fiecărei experiențe.</p></div>
<nav class="settings-tabs" aria-label="Secțiuni setări">
    @foreach (['general' => 'General', 'gnosix' => 'GNOSIX · Site & contact', 'smtp' => 'Email & SMTP', 'security' => 'Securitate & passkeys', 'evidence'=>'Evidence Engine & AI', 'maintenance'=>'Mentenanță','field'=>'QR & Tehnicieni','dna'=>'Building DNA'] as $key => $label)
    <a href="{{ route('admin.settings', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
@if ($tab === 'general')
<section class="panel"><div class="panel-heading"><span class="panel-icon">◇</span><div><h2>Un spațiu pentru platformă.</h2><p>Identitatea și configurația instanței GNOSIX.</p></div><span class="badge">PLATFORM ADMIN</span></div>
<dl class="definition-list"><div><dt>Aplicație</dt><dd>{{ config('app.name') }}</dd></div><div><dt>Adresă</dt><dd>{{ config('app.url') }}</dd></div><div><dt>Mediu</dt><dd>{{ app()->environment() }}</dd></div><div><dt>Cont</dt><dd>Administrator al platformei GNOSIX</dd></div><div><dt>Email sistem</dt><dd>SMTP centralizat · {{ $smtpSaved ? 'Configurat în admin' : 'Configurare inițială din mediu' }}</dd></div></dl></section>
<section class="context-panel"><h3>Administrarea clienților este un spațiu diferit.</h3><p>Setările de aici sunt globale. Conturile viitorilor administratori de clădiri vor aparține aplicației pentru clienți, cu propriile permisiuni și date.</p></section>
@elseif ($tab === 'dna')
<section class="panel"><h2>Limitele explorării și simulării</h2><p>Simularea este read-only; limitele semnalează acoperirea incompletă, fără concluzii inventate.</p><form method="POST" action="{{ route('admin.dna.update') }}" class="settings-form">@csrf @method('PUT')<label>Adâncime maximă<input name="max_depth" type="number" min="1" max="12" required value="{{ old('max_depth',$dnaSettings['max_depth']) }}"></label><label>Noduri per explorare/simulare<input name="max_nodes" type="number" min="40" max="300" required value="{{ old('max_nodes',$dnaSettings['max_nodes']) }}"></label><label>Buget de timp (ms)<input name="timeout_ms" type="number" min="100" max="3000" required value="{{ old('timeout_ms',$dnaSettings['timeout_ms']) }}"></label><button class="button">Salvează limitele</button></form></section>
@elseif ($tab === 'field')
<section class="panel"><h2>Acces securizat în teren</h2><p>Invitațiile au scop limitat, email verificat și pot fi revocate imediat. Locatorul QR nu acordă acces.</p><form method="POST" class="settings-form" action="{{ route('admin.field.update') }}">@csrf @method('PUT')<label>Durata maximă a unei invitații (zile)<input type="number" name="invite_max_days" min="1" max="365" required value="{{ old('invite_max_days',$fieldSettings['invite_max_days']) }}"></label><button class="button">Salvează setările</button></form></section>
@elseif ($tab === 'maintenance')
<section class="panel"><h2>Observații operaționale repetitive</h2><p>Reguli deterministe pentru intervenții rezolvate pe același activ și în aceeași categorie. Nu reprezintă o diagnoză sau o concluzie AI.</p><form class="settings-form" method="POST" action="{{ route('admin.maintenance.update') }}">@csrf @method('PUT')<label>Fereastră de observație (zile)<input type="number" name="repeat_days" required min="7" max="3650" value="{{ old('repeat_days',$maintenance['repeat_days']) }}"></label><label>Număr minim de intervenții<input type="number" name="repeat_threshold" required min="2" max="20" value="{{ old('repeat_threshold',$maintenance['repeat_threshold']) }}"></label><button class="button">Salvează setările</button></form></section>
@elseif ($tab === 'evidence')
<section class="panel"><h2>Evidence Engine & AI</h2><p>Furnizor activ: <strong>{{ $aiProviders[$activeAiProvider]['label'] }}</strong>. Configurarea de mai jos devine activă la salvare. Documentele sunt procesate numai după acordul explicit al utilizatorului.</p>
<form method="GET" action="{{ route('admin.settings') }}" class="settings-form"><input type="hidden" name="tab" value="evidence"><label>Configurează furnizorul<select name="ai_provider">@foreach($aiProviders as $key=>$provider)<option value="{{ $key }}" @selected($evidence['provider']===$key)>{{ $provider['label'] }}</option>@endforeach</select></label><button class="button secondary">Deschide configurația</button></form>
<p class="helper">Deschiderea configurației nu activează furnizorul și nu face cereri externe. Cheile și modelele se păstrează separat pentru fiecare sursă.</p>
<form method="POST" action="{{ route('admin.evidence.update') }}" class="settings-form">@csrf @method('PUT')
<input type="hidden" name="provider" value="{{ $evidence['provider'] }}"><input type="hidden" name="enabled" value="0"><label class="check-label span-two"><input type="checkbox" name="enabled" value="1" @checked($evidence['enabled'])><span>Activează extracția cu {{ $aiProviders[$evidence['provider']]['label'] }}</span></label>
<label class="span-two">Model cu vedere și JSON Schema<input name="model" maxlength="100" value="{{ old('model',$evidence['model']) }}" placeholder="{{ $evidence['provider']==='openrouter'?'openrouter/free':'Identificatorul exact din cont sau ollama list' }}"></label>
@if($aiProviders[$evidence['provider']]['key'])
<label>Cheie API pentru {{ $aiProviders[$evidence['provider']]['label'] }}<input type="password" name="api_key" autocomplete="new-password" maxlength="512" placeholder="{{ $hasEvidenceKey ? 'Salvată criptat — lasă gol pentru păstrare' : 'Nicio cheie configurată' }}"></label>
<label class="check-label"><input type="checkbox" name="clear_key" value="1"><span>Șterge cheia acestui furnizor</span></label>
@else
<p class="span-two helper">Ollama nu necesită cheie API. Rulează pe același server cu workerul, exclusiv la http://127.0.0.1:11434. Instalează în prealabil un model vision local; aplicația nu descarcă modele. Dezactivează cloud în configurația Ollama cu disable_ollama_cloud=true; modelul trebuie să fie local și să declare suport vision.</p>
@endif
@if($evidence['provider']==='openrouter')
<input type="hidden" name="free_only" value="0"><label class="check-label span-two"><input type="checkbox" name="free_only" value="1" @checked(old('free_only',$evidence['free_only']))><span>Doar modele gratuite — acceptă openrouter/free sau un model :free; fără fallback automat la modele plătite</span></label>
@endif
<label>Limită per fișier (MB)<input type="number" name="max_file_mb" min="1" max="20" required value="{{ $evidence['max_file_mb'] }}"></label><label>Cotă per clădire (MB)<input type="number" name="building_quota_mb" min="20" max="2048" required value="{{ $evidence['building_quota_mb'] }}"></label>
<p class="span-two helper">OpenAI și Gemini: PDF, PNG, JPEG. OpenRouter și Ollama: PNG/JPEG; pentru PDF folosește Gemini/OpenAI sau încarcă paginile ca imagini. Modelul ales trebuie să suporte imagini și răspuns JSON Schema.</p>
<p class="span-two helper">Gemini are cote gratuite doar pentru anumite modele/conturi; OpenRouter are modele :free cu limite și disponibilitate variabilă. Verifică tarifele și politica de date în contul furnizorului. GNOSIX nu poate impune gratuitatea unui cont Gemini. Tier-urile gratuite pot folosi datele pentru îmbunătățirea serviciilor. Ollama consumă resursele serverului și depinde de licența modelului. Nu există comutare automată între surse.</p>
<p class="span-two helper">Worker Forge: php artisan queue:work evidence --queue=evidence --tries=3 --timeout=120. Cheile rămân criptate. Configurarea nu trimite documente; acordul per versiune este necesar inclusiv pentru procesarea locală.</p><button class="button">Salvează configurația selectată</button></form></section>
@elseif ($tab === 'gnosix')
<section class="panel"><div class="panel-heading"><span class="panel-icon">◇</span><div><h2>GNOSIX · Site & contact</h2><p>Setările publice ale site-ului, controlate din administrare.</p></div><span class="badge">CONFIGURAȚIE GLOBALĂ</span></div>
<p class="helper">Modificările sunt salvate în baza de date și se aplică imediat, inclusiv cu configurația Laravel cache-uită. Nu trebuie editat fișierul de mediu sau efectuat un deploy.</p>
<form method="POST" action="{{ route('admin.gnosix.update') }}" class="form-grid">
    @csrf @method('PUT')
    <label class="span-two">Adresa publică canonică<input type="url" name="canonical_url" required maxlength="255" value="{{ old('canonical_url', $site['canonical_url']) }}" placeholder="https://gnosix.zion3d.ro" aria-describedby="canonical-hint"><span id="canonical-hint" class="fine">Adresa de bază folosită pentru SEO, sitemap și distribuire, fără /en sau alte căi. Nu schimbă domeniul configurat pe server.</span></label>
    <label class="span-two">Email pentru primirea mesajelor de contact<input type="email" name="contact_recipient" required maxlength="160" value="{{ old('contact_recipient', $site['contact_recipient']) }}" autocomplete="off"><span class="fine">Destinatarul formularului în ambele limbi. Expeditorul și conexiunea se configurează în Email & SMTP.</span></label>
    <input type="hidden" name="contact_enabled" value="0">
    <label class="settings-option span-two"><input type="checkbox" name="contact_enabled" value="1" @checked(old('contact_enabled', $site['contact_enabled']))><span><strong>Activează formularul de contact</strong><small>Permite trimiterea mesajelor când SMTP este configurat. Dezactivarea oprește trimiterea în română și engleză.</small></span></label>
    <input type="hidden" name="indexable" value="0">
    <label class="settings-option span-two"><input type="checkbox" name="indexable" value="1" @checked(old('indexable', $site['indexable']))><span><strong>Permite indexarea în motoarele de căutare</strong><small>Controlează robots.txt și metadatele SEO pentru paginile publice. Păstrează dezactivat pe mediile de test. Adminul rămâne exclus de la indexare.</small></span></label>
    <div class="form-actions span-two"><p class="fine">Salvarea necesită o autentificare din ultimele 10 minute.</p><button class="button">Salvează setările GNOSIX <span>↗</span></button></div>
</form></section>
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
