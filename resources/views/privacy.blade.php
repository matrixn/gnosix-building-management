@extends('layouts.site')
@section('title', __('Confidențialitate — GNOSIX Building Management'))
@section('content')
@include('landing.header')
<main id="main-content" class="wrap legal-page">
    <span class="eyebrow">{{ __('DATELE TALE, ÎN CONTEXTUL POTRIVIT') }}</span>
    <h1>{{ __('Confidențialitate.') }}</h1>
    <p>{{ __('Informare pentru contact, conturi și organizații GNOSIX Building Management. Ultima actualizare: 16 noiembrie 2026.') }}</p>
    <h2>{{ __('Cine primește cererea') }}</h2>
    <p>{{ __('Cererile ajung la Alex-Radu Răduță, inițiatorul proiectului GNOSIX Building Management. Proiectul este în dezvoltare; formularul invită la o discuție, fără a încheia un abonament sau un contract.') }}</p>
    <h2>{{ __('Ce informații folosim') }}</h2>
    <p>{{ __('Numele complet, emailul, telefonul, subiectul și mesajul sunt folosite pentru a răspunde solicitării tale și pentru a evalua o posibilă colaborare. Nu include date sensibile sau informații personale despre locatari, oaspeți ori angajați.') }}</p>
    <h2>{{ __('Scop și temei') }}</h2>
    <p>{{ __('Prelucrăm cererea pentru demersurile solicitate de tine înaintea unei posibile colaborări. Confirmarea informării nu reprezintă acord pentru marketing. Nu te înscriem automat la newsletter și nu vindem datele.') }}</p>
    <h2>{{ __('Livrare și păstrare') }}</h2>
    <p>{{ __('Cererea este transmisă prin email către responsabilul proiectului. Furnizorii de găzduire și email pot prelucra datele pentru livrare și funcționarea serviciului. Aplicația nu creează o bază de date separată cu solicitări. Păstrăm corespondența cât este necesar pentru discuția inițiată și pentru eventualele obligații legale aplicabile.') }}</p>
    <h2>{{ __('Cookie-uri și date tehnice') }}</h2>
    <p>{{ __('Folosim un cookie de sesiune și mecanisme tehnice pentru protecția formularului. Adresa IP poate fi folosită temporar pentru limitarea abuzului; serverul poate păstra jurnale tehnice de acces. Pagina nu include instrumente de analiză, publicitate sau fonturi externe.') }}</p>
    <h2>{{ __('Contul și identitatea ta GNOSIX') }}</h2>
    <p>{{ __('Pentru cont păstrăm numele, emailul, limba preferată, profilul ales și momentele de confirmare a emailului, autentificare și finalizare a introducerii. Parola este stocată ca hash, nu în clar. Aceste informații sunt folosite pentru acces, personalizarea experienței și securitatea contului.') }}</p>
    <p>{{ __('Trimitem emailuri necesare pentru confirmarea adresei și resetarea parolei. Cookie-ul de sesiune menține autentificarea; în funcție de stocarea configurată, sesiunile pot include adresa IP și identificarea browserului. Datele contului sunt păstrate în baza de date a aplicației; poți solicita corectarea sau ștergerea lor prin contact. Contul nu te înscrie la marketing.') }}</p>
    <h2>{{ __('Organizații și ciorne de configurare') }}</h2>
    <p>{{ __('Păstrăm numele organizației, profilul, forma de operare, apartenențele și rolurile, capabilitățile configurate și organizația activă. Ciorna de configurare este salvată pentru a putea continua ulterior. Membrii unei organizații pot vedea numele și emailurile celorlalți membri ai acelui spațiu; accesul nu se extinde automat la alte organizații.') }}</p>
    <p>{{ __('Pentru spațiul organizației păstrăm preferințele de țară, fus orar, limbă și unități, datele de contact și juridice opționale, logo-ul privat, grupurile și structura proprietăților, clădirilor, etajelor și spațiilor. Ciornele Workspace Studio sunt separate pentru fiecare utilizator și organizație. Accesul la proprietăți poate fi limitat la membri desemnați.') }}</p>
    <h2>{{ __('Solicitări privind datele tale') }}</h2>
    <p>{{ __('Poți cere accesul, corectarea sau ștergerea datelor, precum și informații suplimentare despre prelucrare, prin răspuns la corespondența cu proiectul sau prin formularul de contact, precizând scopul solicitării. Drepturile se aplică în condițiile legislației relevante. Poți adresa o plângere autorității competente pentru protecția datelor.') }}</p>
    <a href="{{ \App\Support\PublicSite::home() }}#contact" class="button">{{ __('Înapoi la contact') }} <span>↗</span></a>
</main>
@include('landing.footer')

@endsection
