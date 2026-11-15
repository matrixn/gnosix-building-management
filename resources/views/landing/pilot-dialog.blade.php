<dialog id="pilot-dialog" aria-labelledby="dialog-title" data-open="{{ $errors->any() || session('pilot_sent') ? 'true' : 'false' }}">
    <button class="dialog-close" aria-label="Închide formularul" type="button">×</button>
    <span class="eyebrow">CONSTRUIM ÎMPREUNĂ</span>
    <h2 id="dialog-title">Clădirea ta.<br>Următorul nostru pas.</h2>
    @if (session('pilot_sent'))
        <div class="form-notice success" role="status"><strong>Mulțumim pentru mesaj.</strong><p>Cererea a fost transmisă. Vom reveni la adresa indicată pentru a discuta despre proprietatea ta.</p></div>
    @else
        @if ($errors->any())
            <div class="form-notice" role="alert"><strong>Verifică cererea înainte de trimitere.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @if (\App\Support\PilotAvailability::ready())
            <p>Spune-ne ce proprietate administrezi și ce ai vrea să funcționeze mai bine. Discuția inițială nu implică un angajament comercial.</p>
            <form id="pilot-form" method="POST" action="{{ route('pilot.store') }}">
                @csrf
                <label>Numele tău<input required name="name" autocomplete="name" maxlength="100" value="{{ old('name') }}" placeholder="Cum te numești?" @error('name') aria-invalid="true" @enderror></label>
                <label>Email de contact<input required name="email" type="email" autocomplete="email" maxlength="160" value="{{ old('email') }}" placeholder="nume@companie.ro" @error('email') aria-invalid="true" @enderror></label>
                <label>Tipul proprietății<select name="property" required>@foreach (['hotel' => 'Hotel / pensiune', 'residential' => 'Clădire rezidențială', 'office' => 'Clădire de birouri', 'portfolio' => 'Portofoliu de clădiri'] as $value => $label)<option value="{{ $value }}" @selected(old('property', 'hotel') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label>Ce ai vrea să rezolvăm?<textarea name="message" required minlength="10" maxlength="3000" rows="4" placeholder="Documente greu de găsit, revizii uitate…" @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea></label>
                <div class="form-trap" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
                <label class="consent"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))><span>Am citit <a href="{{ route('privacy') }}" target="_blank" rel="noopener">informarea privind datele personale</a> și înțeleg că datele sunt folosite pentru a răspunde acestei cereri.</span></label>
                <button class="button" type="submit">Trimite cererea <span aria-hidden="true">↗</span></button>
                <p class="fine">Fără abonare automată la newsletter. Nu include date sensibile, parole sau documente confidențiale.</p>
            </form>
        @else
            <p>Pregătim deschiderea formularului pentru partenerii pilot. Te invităm să revii în curând pentru a ne povesti despre proprietatea ta.</p>
        @endif
    @endif
</dialog>
