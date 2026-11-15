@extends('layouts.identity')
@section('title', __('workspace.new_organization'))
@section('content')
<a class="identity-link organization-back" href="{{ route('app.organizations.index') }}">← {{ __('workspace.organizations') }}</a>
@include('organizations.wizard')
@endsection
