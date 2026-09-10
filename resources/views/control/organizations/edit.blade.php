@extends('layouts.control')
@section('title', __('institutional.edit_organization'))
@section('content')
    <section class="page-heading compact-heading">
        <div><span class="eyebrow">GOVERNANCE / {{ $organization->code }}</span><h1>{{ __('institutional.edit_organization') }}</h1><p>{{ $organization->display_name }}</p></div>
    </section>
    @include('control.organizations._form')
@endsection
