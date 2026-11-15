@php($contactReady = \App\Support\ContactAvailability::ready())
<section class="wrap contact-section" id="contact" aria-labelledby="contact-title">
    <div class="contact-intro">
        <span class="eyebrow">{{ __('CONTACT / GNOSIX') }}</span>
        <h2 id="contact-title">{{ __('Să vorbim despre') }}<br><em>{{ __('clădirea ta.') }}</em></h2>
        <p>{{ __('Spune-ne ce vrei să îmbunătățești. Fie că administrezi o proprietate, pregătești o predare sau explorezi un parteneriat, conversația începe aici.') }}</p>
        <ol class="contact-steps">
            <li><span>01</span><div><h3>{{ __('Contextul tău') }}</h3><p>{{ __('Înțelegem proprietatea și provocarea.') }}</p></div></li>
            <li><span>02</span><div><h3>{{ __('O discuție utilă') }}</h3><p>{{ __('Clarificăm ce ar trebui să funcționeze mai bine.') }}</p></div></li>
            <li><span>03</span><div><h3>{{ __('Următorul pas') }}</h3><p>{{ __('Explorăm împreună o posibilă colaborare.') }}</p></div></li>
        </ol>
        <span class="status"><i></i>{{ __('În dezvoltare · Deschis pentru colaborări') }}</span>
    </div>
    <div class="contact-card">
        @if (session('contact_sent'))
            <div class="form-notice success" role="status" tabindex="-1" data-contact-feedback><strong>{{ __('Mulțumim pentru mesaj.') }}</strong><p>{{ __('Mesajul a fost transmis. Vom reveni folosind datele de contact indicate.') }}</p></div>
        @endif
        @if ($errors->any())
            <div id="contact-errors" class="form-notice" role="alert" tabindex="-1" data-contact-feedback><strong>{{ __('Verifică informațiile de mai jos.') }}</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @unless ($contactReady)<p class="contact-unavailable" role="status">{{ __('Formularul nu este disponibil momentan. Te rugăm să revii.') }}</p>@endunless
        <form id="contact-form" aria-labelledby="contact-title" method="POST" action="{{ \App\Support\PublicSite::contact() }}" data-sending="{{ __('Se trimite…') }}" data-required="{{ __('Completează acest câmp.') }}" data-email="{{ __('Introdu o adresă de email validă.') }}" data-phone="{{ __('Introdu un număr de telefon valid. Pentru numere din afara României, include prefixul internațional (de exemplu +44).') }}" data-message="{{ __('Mesajul trebuie să aibă cel puțin 10 caractere.') }}">
            @csrf
            <fieldset class="contact-fields" @disabled(! $contactReady)>
                <div class="contact-field">
                    <label for="contact-name">{{ __('Nume complet') }}</label>
                    <input id="contact-name" name="name" required autocomplete="name" minlength="2" maxlength="120" value="{{ old('name') }}" placeholder="{{ __('Numele și prenumele tău') }}" @error('name') aria-invalid="true" aria-describedby="contact-name-error" @enderror>
                    @error('name')<span class="field-error" id="contact-name-error">{{ $message }}</span>@enderror
                </div>
                <div class="contact-field">
                    <label for="contact-email">{{ __('Adresă email') }}</label>
                    <input id="contact-email" name="email" type="email" required autocomplete="email" maxlength="160" value="{{ old('email') }}" placeholder="{{ __('nume@companie.ro') }}" @error('email') aria-invalid="true" aria-describedby="contact-email-error" @enderror>
                    @error('email')<span class="field-error" id="contact-email-error">{{ $message }}</span>@enderror
                </div>
                <div class="contact-field contact-wide">
                    <label for="contact-subject">{{ __('Subiect') }}</label>
                    <select id="contact-subject" name="subject" required @error('subject') aria-invalid="true" aria-describedby="contact-subject-error" @enderror>
                        @foreach (\App\Support\ContactSubjects::options() as $value => $label)<option value="{{ $value }}" @selected(old('subject', 'pilot') === $value)>{{ $label }}</option>@endforeach
                    </select>
                    @error('subject')<span class="field-error" id="contact-subject-error">{{ $message }}</span>@enderror
                </div>
                <div class="contact-field contact-wide">
                    <label for="contact-phone">{{ __('Număr de telefon') }}</label>
                    <input id="contact-phone" name="phone" type="tel" inputmode="tel" required autocomplete="tel" maxlength="40" pattern="[+0-9\(\) .\-]{7,40}" value="{{ old('phone') }}" placeholder="{{ __('07xx xxx xxx sau +40…') }}" aria-describedby="contact-phone-hint{{ $errors->has('phone') ? ' contact-phone-error' : '' }}" @error('phone') aria-invalid="true" @enderror>
                    <span class="fine" id="contact-phone-hint">{{ __('Pentru numere din afara României, include prefixul de țară (de exemplu +44).') }}</span>
                    @error('phone')<span class="field-error" id="contact-phone-error">{{ $message }}</span>@enderror
                </div>
                <div class="contact-field contact-wide">
                    <label for="contact-message">{{ __('Mesaj') }}</label>
                    <textarea id="contact-message" name="message" required minlength="10" maxlength="3000" rows="5" placeholder="{{ __('Cu ce te putem ajuta?') }}" @error('message') aria-invalid="true" aria-describedby="contact-message-error" @enderror>{{ old('message') }}</textarea>
                    @error('message')<span class="field-error" id="contact-message-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-trap" aria-hidden="true"><label for="contact-website">Website</label><input id="contact-website" name="website" tabindex="-1" autocomplete="off"></div>
                <div class="contact-field contact-wide">
                    <label class="consent"><input type="checkbox" name="consent" value="1" required @checked(old('consent')) @error('consent') aria-invalid="true" aria-describedby="contact-consent-error" @enderror><span>{{ __('Am citit') }} <a href="{{ \App\Support\PublicSite::privacy() }}" target="_blank" rel="noopener">{{ __('informarea privind datele personale') }}</a> {{ __('și înțeleg că datele sunt folosite pentru a răspunde acestei cereri.') }}</span></label>
                    @error('consent')<span class="field-error" id="contact-consent-error">{{ $message }}</span>@enderror
                </div>
                <button class="button contact-wide" type="submit">{{ __('Trimite mesajul') }} <span aria-hidden="true">↗</span></button>
                <p class="fine contact-wide">{{ __('Fără abonare automată la newsletter. Nu include date sensibile, parole sau documente confidențiale.') }}</p>
            </fieldset>
        </form>
    </div>
</section>
