@extends('layouts.auth')
@section('title', __('account.forgot_password'))
@section('content')
<div class="card-heading"><span class="section-mark"></span><div><h2>{{ __('account.forgot_password') }}</h2><p>{{ __('account.forgot_intro') }}</p></div></div>
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('password.email',['locale'=>app()->getLocale()]) }}" class="form-stack">@csrf<label class="field"><span>{{ __('account.email') }}</span><input type="email" name="email" required autocomplete="email" value="{{ old('email') }}"></label><button class="primary-button" type="submit">{{ __('account.send_reset') }}</button><a class="secondary-action" href="{{ route('login',['locale'=>app()->getLocale()]) }}">{{ __('account.back_login') }}</a></form>
@endsection
