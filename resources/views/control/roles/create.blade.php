@extends('layouts.control')
@section('title', __('institutional.new_role'))
@section('content')
    <section class="page-heading compact-heading"><div><span class="eyebrow">RBAC / NEW ROLE</span><h1>{{ __('institutional.new_role') }}</h1></div></section>
    @include('control.roles._form')
@endsection
