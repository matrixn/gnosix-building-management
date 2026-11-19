@extends('admin.layout')
@section('title', $customer->name)
@section('content')
@php
    $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->timezone('Europe/Bucharest')->format('d.m.Y H:i') : '—';
    $labels = ['properties'=>'Proprietăți','buildings'=>'Clădiri','created_buildings'=>'Clădiri create de utilizator','spaces'=>'Spații','assets'=>'Active','work_orders'=>'Ordine de lucru'];
    $actions = ['created'=>'Creat','updated'=>'Actualizat','archived'=>'Arhivat','restored'=>'Restaurat'];
@endphp
<header class="page-heading customer-heading">
    <div><a class="back-link customer-back" href="{{ route('admin.customers.index') }}">← Utilizatori</a><h1>{{ $customer->name }}</h1><p>{{ $customer->email }}</p></div>
    <div class="customer-actions">@include('admin.customers.status') <a class="button ghost" href="{{ route('admin.customers.edit', $customer) }}">Editează contul</a></div>
</header>

<section class="panel" aria-labelledby="customer-account">
    <h2 id="customer-account">Cont și configurare</h2>
    <dl class="definition-list customer-definition">
        <div><dt>Înregistrat</dt><dd>{{ $date($customer->created_at) }}</dd></div>
        <div><dt>Email verificat</dt><dd>{{ $date($customer->email_verified_at) }}</dd></div>
        <div><dt>Prima autentificare</dt><dd>{{ $date($customer->first_signed_in_at) }}</dd></div>
        <div><dt>Ultima autentificare</dt><dd>{{ $date($customer->last_signed_in_at) }}</dd></div>
        <div><dt>Configurare finalizată</dt><dd>{{ $date($customer->onboarding_completed_at) }}</dd></div>
        <div><dt>Profil personal</dt><dd>{{ $customer->profile_type ? (trans()->has('identity.profile_'.$customer->profile_type) ? __('identity.profile_'.$customer->profile_type) : $customer->profile_type) : 'Neselectat' }}</dd></div>
        <div><dt>Limba contului</dt><dd>{{ $customer->preferredLocale() === 'en' ? 'Engleză' : 'Română' }}</dd></div>
        <div><dt>Organizația activă</dt><dd>{{ $preference?->organization?->name ?? 'Neselectată' }}</dd></div>
        <div><dt>Configurare ghidată</dt><dd>{{ $preference ? ($preference->guided_setup ? 'Da' : 'Nu') : 'Neselectată' }}</dd></div>
    </dl>
</section>

<section class="panel" aria-labelledby="customer-source">
    <h2 id="customer-source">Proveniența înregistrării</h2>
    @if($source = $customer->registration_source)
        @php
            $country = $source['country'];
            $region = $country === 'ZZ' ? 'Necunoscută' : (class_exists(\Locale::class) ? \Locale::getDisplayRegion('und_'.$country, 'ro') ?: $country : $country);
        @endphp
        <dl class="definition-list customer-definition">
            <div><dt>Țară estimată</dt><dd>{{ $region }}</dd></div>
            <div><dt>Referrer</dt><dd>{{ $source['referrer'] ?: 'Direct / necunoscut' }}</dd></div>
            <div><dt>Campanie UTM</dt><dd>{{ $source['utm']['utm_campaign'] ?? 'Fără UTM' }}</dd></div>
            @foreach(\Illuminate\Support\Arr::except($source['utm'], ['utm_campaign']) as $key => $value)
                <div><dt>{{ $key }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
    @else
        <p>Proveniența nu a fost înregistrată pentru acest cont.</p>
    @endif
</section>

<section aria-labelledby="customer-scope">
    <h2 id="customer-scope">Sumar operațional</h2>
    <p class="fine">Datele din organizații sunt numărate după proprietățile din accesul configurat pentru cont. Clădirile create personal sunt identificate din istoricul declarațiilor.</p>
    <dl class="campaign-metrics">
        @foreach($labels as $key => $label)
            <div><dt>{{ $label }}</dt><dd>{{ number_format($metrics[$key]->sum(), 0, ',', '.') }}</dd></div>
        @endforeach
    </dl>
    @forelse($memberships as $membership)
        <details class="panel customer-membership">
            <summary>{{ $membership->organization->name }}</summary>
            <p class="fine">{{ __('workspace.role_'.$membership->role) === 'workspace.role_'.$membership->role ? $membership->role : __('workspace.role_'.$membership->role) }} · {{ $membership->access_scope === 'all' || $membership->role === 'owner' ? 'Toate proprietățile' : 'Proprietăți atribuite' }}</p>
            @if($membership->organization->profile)
                <p class="fine">{{ $membership->organization->profile->profile_key }} · {{ $membership->organization->profile->organization_kind }}</p>
            @endif
            <dl class="campaign-metrics">
                @foreach($labels as $key => $label)
                    <div><dt>{{ $label }}</dt><dd>{{ number_format($metrics[$key]->get($membership->organization_id, 0), 0, ',', '.') }}</dd></div>
                @endforeach
            </dl>
        </details>
    @empty
        <p class="empty-state">Utilizatorul nu are încă o organizație.</p>
    @endforelse
    @include('admin.customers.pagination', ['items' => $memberships])
</section>

<section class="panel customer-lifecycle" aria-labelledby="customer-lifecycle">
    <h2 id="customer-lifecycle">Acces la cont</h2>
    @if($customer->isArchived())
        <p>Cont arhivat la {{ $date($customer->archived_at) }}. Poți redeschide accesul.</p>
        <form method="post" action="{{ route('admin.customers.restore', $customer) }}">@csrf <button class="button" type="submit">Restaurează contul</button></form>
    @else
        <details>
            <summary>Arhivează contul</summary>
            <p>Arhivarea oprește accesul. Organizațiile, clădirile și istoricul rămân disponibile.</p>
            <form method="post" action="{{ route('admin.customers.archive', $customer) }}">
                @csrf @method('DELETE')
                <label class="check-label"><input type="checkbox" name="confirm_archive" value="1" required> Confirm arhivarea acestui cont</label>
                <button class="button secondary" type="submit">Arhivează contul</button>
            </form>
        </details>
    @endif
    @if($history->isNotEmpty())
        <details class="campaign-sources"><summary>Istoric administrativ</summary><ul>
            @foreach($history as $event)
                <li>{{ $actions[$event->action] ?? $event->action }} · {{ $date($event->created_at) }} @if($event->administrator) · {{ $event->administrator }} @endif</li>
            @endforeach
        </ul></details>
    @endif
</section>
@endsection
