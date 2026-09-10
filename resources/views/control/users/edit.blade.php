@extends('layouts.control')
@section('title', __('institutional.edit_user'))
@section('content')
    <section class="page-heading compact-heading"><div><span class="eyebrow">ACCESS / USER</span><h1>{{ __('institutional.edit_user') }}</h1><p><bdi dir="auto">{{ $user->name }}</bdi></p></div></section>
    @include('control.users._form')
@endsection
