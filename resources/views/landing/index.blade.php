@extends('layouts.site')
@section('content')
@include('landing.header')
<main id="main-content">
    @include('landing.sections.hero')
    @include('landing.sections.ticker')
    @include('landing.sections.story')
    @include('landing.sections.features')
    @include('landing.sections.segments')
    @include('landing.sections.pilot')
    @include('landing.sections.faq')
</main>
@include('landing.footer')
@include('landing.pilot-dialog')
@endsection
