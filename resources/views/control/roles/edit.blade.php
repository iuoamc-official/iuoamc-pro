@extends('layouts.control')
@section('title', __('institutional.edit_role'))
@section('content')
    <section class="page-heading compact-heading"><div><span class="eyebrow">RBAC / {{ $role->slug }}</span><h1>{{ __('institutional.edit_role') }}</h1><p>{{ $role->name }}</p></div></section>
    @include('control.roles._form')
@endsection
