@extends('layouts.auth')
@section('title', __('account.reset_password'))
@section('content')
<div class="card-heading"><span class="section-mark"></span><div><h2>{{ __('account.reset_password') }}</h2><p>{{ __('account.password_help') }}</p></div></div>
@if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('password.store',['locale'=>app()->getLocale()]) }}" class="form-stack">@csrf<input type="hidden" name="token" value="{{ $token }}"><label class="field"><span>{{ __('account.email') }}</span><input type="email" name="email" required value="{{ old('email',$email) }}"></label><label class="field"><span>{{ __('account.password') }}</span><input type="password" name="password" required minlength="12" autocomplete="new-password"></label><label class="field"><span>{{ __('account.password_confirmation') }}</span><input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"></label><button class="primary-button" type="submit">{{ __('account.reset_password') }}</button></form>
@endsection
