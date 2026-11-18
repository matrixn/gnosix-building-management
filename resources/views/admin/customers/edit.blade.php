@extends('admin.layout')
@section('title', $customer->exists ? 'Editare utilizator' : 'Utilizator nou')
@section('content')
<header class="page-heading customer-heading">
    <div>
        <a class="back-link customer-back" href="{{ $customer->exists ? route('admin.customers.show', $customer) : route('admin.customers.index') }}">← Înapoi</a>
        <h1>{{ $customer->exists ? 'Editează utilizatorul' : 'Adaugă utilizator' }}</h1>
        <p>Cont de client GNOSIX.</p>
    </div>
</header>
<form class="panel form-grid customer-form" method="post" action="{{ $customer->exists ? route('admin.customers.update', $customer) : route('admin.customers.store') }}">
    @csrf
    @if($customer->exists) @method('PATCH') @endif
    <label>Nume complet <input name="name" value="{{ old('name', $customer->name) }}" required maxlength="120" autocomplete="off"></label>
    <label>Adresă email <input type="email" name="email" value="{{ old('email', $customer->email) }}" required maxlength="255" autocomplete="off"></label>
    <label>Limba contului <select name="locale"><option value="ro" @selected(old('locale', $customer->locale) === 'ro')>Română</option><option value="en" @selected(old('locale', $customer->locale) === 'en')>Engleză</option></select></label>
    @if($customer->exists)
        <p class="fine">Dacă schimbi adresa, utilizatorul va trebui să o verifice din nou. Poți lăsa parola goală pentru a o păstra.</p>
    @else
        <p class="fine">Utilizatorul își verifică emailul după prima autentificare.</p>
    @endif
    <label>{{ $customer->exists ? 'Parolă nouă (opțional)' : 'Parolă' }} <input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" @required(!$customer->exists)></label>
    <label>Confirmă parola <input type="password" name="password_confirmation" minlength="12" maxlength="72" autocomplete="new-password" @required(!$customer->exists)></label>
    <div class="span-two"><button class="button" type="submit">{{ $customer->exists ? 'Salvează modificările' : 'Creează contul' }}</button></div>
</form>
@endsection
