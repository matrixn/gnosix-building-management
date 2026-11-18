@extends('admin.layout')
@section('title', 'Utilizatori')
@section('content')
<header class="page-heading customer-heading">
    <div><h1>Utilizatori</h1><p>Conturile clienților, în ordinea înregistrării.</p></div>
    <a class="button" href="{{ route('admin.customers.create') }}">Adaugă utilizator</a>
</header>

<form class="campaign-filter" action="{{ route('admin.customers.index') }}">
    <label>Caută după nume sau email <input type="search" name="q" maxlength="120" value="{{ $search }}"></label>
    <label>Starea contului <select name="state">
        @foreach(['all'=>'Toate','active'=>'Email verificat','pending'=>'Email neconfirmat','archived'=>'Arhivat'] as $value => $label)
            <option value="{{ $value }}" @selected($state === $value)>{{ $label }}</option>
        @endforeach
    </select></label>
    <button class="button" type="submit">Aplică</button>
</form>

<div class="customer-list">
    @forelse($customers as $customer)
        <article class="panel customer-row">
            <div><h2>{{ $customer->name }}</h2><p>{{ $customer->email }}</p></div>
            <div>@include('admin.customers.status')<p class="fine">Înscris {{ $customer->created_at?->timezone('Europe/Bucharest')->format('d.m.Y H:i') ?? '—' }} · Organizații {{ $customer->memberships_count }}</p></div>
            <a class="button ghost" href="{{ route('admin.customers.show', $customer) }}" aria-label="Vezi detalii pentru {{ $customer->name }}">Vezi detalii</a>
        </article>
    @empty
        <p class="empty-state">Nu există utilizatori pentru filtrele selectate.</p>
    @endforelse
</div>
@include('admin.customers.pagination', ['items' => $customers])
@endsection
