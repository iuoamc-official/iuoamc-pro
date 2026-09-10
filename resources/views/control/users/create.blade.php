@extends('layouts.control')
@section('title', __('institutional.new_user'))
@section('content')
    <section class="page-heading compact-heading"><div><span class="eyebrow">ACCESS / NEW USER</span><h1>{{ __('institutional.new_user') }}</h1></div></section>
    @include('control.users._form')
@endsection
