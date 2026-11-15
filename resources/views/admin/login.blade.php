@extends('admin.layout')
@section('title', 'Autentificare administrator')
@section('content')
<div class="login-card">
    <a class="wordmark" href="{{ route('home') }}"><span>G×</span> GNOSIX</a>
    <span class="scope">ADMINISTRAREA PLATFORMEI</span>
    <h1>Un singur loc.<br><em>Controlul platformei.</em></h1>
    <p class="intro">Acces rezervat administratorilor GNOSIX. Conturile clienților sunt gestionate separat.</p>
    <form method="POST" action="{{ route('admin.login.store') }}" class="form-stack">
        @csrf
        <label>Email administrator<input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" maxlength="255" autofocus></label>
        <label>Parolă<input type="password" name="password" required autocomplete="current-password" maxlength="255"></label>
        <button class="button">Intră în administrare <span>↗</span></button>
    </form>
    <div class="divider"><span>sau</span></div>
    <button class="button secondary full" data-passkey-login data-options="{{ route('admin.passkeys.options') }}" data-submit="{{ route('admin.passkeys.login') }}">Autentificare cu passkey <span>⌘</span></button>
    <p class="passkey-feedback" role="status" aria-live="polite"></p>
    <p class="fine">Passkey-ul se înregistrează după prima autentificare cu parolă, din Setări → Securitate.</p>
    <a class="back-link" href="{{ route('home') }}">← Înapoi la GNOSIX</a>
</div>
@endsection
