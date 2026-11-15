@extends('layouts.site')
@section('title', 'Confidențialitate — GNOSIX Building Management')
@section('content')
@include('landing.header')
<main id="main-content" class="wrap legal-page">
    <span class="eyebrow">DATELE TALE, ÎN CONTEXTUL POTRIVIT</span>
    <h1>Confidențialitate.</h1>
    <p>Informare pentru formularul programului pilot GNOSIX Building Management. Ultima actualizare: 15 noiembrie 2026.</p>
    <h2>Cine primește cererea</h2>
    <p>Cererile ajung la Alex-Radu Răduță, inițiatorul proiectului GNOSIX Building Management. Proiectul este în dezvoltare; formularul invită la o discuție, fără a încheia un abonament sau un contract.</p>
    <h2>Ce informații folosim</h2>
    <p>Numele, emailul, tipul proprietății și descrierea trimisă sunt folosite pentru a răspunde cererii și pentru a evalua o posibilă colaborare pilot. Nu include date sensibile sau informații personale despre locatari, oaspeți ori angajați.</p>
    <h2>Scop și temei</h2>
    <p>Prelucrăm cererea pentru demersurile solicitate de tine înaintea unei posibile colaborări. Confirmarea informării nu reprezintă acord pentru marketing. Nu te înscriem automat la newsletter și nu vindem datele.</p>
    <h2>Livrare și păstrare</h2>
    <p>Cererea este transmisă prin email către responsabilul proiectului. Furnizorii de găzduire și email pot prelucra datele pentru livrare și funcționarea serviciului. Aplicația nu creează o bază de date separată cu solicitări. Păstrăm corespondența cât este necesar pentru discuția inițiată și pentru eventualele obligații legale aplicabile.</p>
    <h2>Cookie-uri și date tehnice</h2>
    <p>Folosim un cookie de sesiune și mecanisme tehnice pentru protecția formularului. Adresa IP poate fi folosită temporar pentru limitarea abuzului; serverul poate păstra jurnale tehnice de acces. Pagina nu include instrumente de analiză, publicitate sau fonturi externe.</p>
    <h2>Solicitări privind datele tale</h2>
    <p>Poți cere accesul, corectarea sau ștergerea datelor, precum și informații suplimentare despre prelucrare, prin răspuns la corespondența cu proiectul sau prin formularul programului pilot, precizând scopul solicitării. Drepturile se aplică în condițiile legislației relevante. Poți adresa o plângere autorității competente pentru protecția datelor.</p>
    <a href="{{ route('home') }}#pilot" class="button">Înapoi la programul pilot <span>↗</span></a>
</main>
@include('landing.footer')
@include('landing.pilot-dialog')
@endsection
