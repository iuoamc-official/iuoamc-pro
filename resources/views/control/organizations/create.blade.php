@extends('layouts.control')
@section('title', __('institutional.new_organization'))
@section('content')
    <section class="page-heading compact-heading">
        <div><span class="eyebrow">GOVERNANCE / NEW ENTITY</span><h1>{{ __('institutional.new_organization') }}</h1></div>
    </section>
    @include('control.organizations._form')
@endsection
