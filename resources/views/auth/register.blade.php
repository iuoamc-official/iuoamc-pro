@extends('layouts.auth')
@section('title', __('account.create_account'))
@section('content')
<div class="card-heading"><span class="section-mark"></span><div><h2>{{ __('account.create_account') }}</h2><p>{{ __('account.register_intro') }}</p></div></div>
@if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('register.store',['locale'=>app()->getLocale()]) }}" class="form-stack">@csrf
    <label class="field"><span>{{ __('account.name') }}</span><input name="name" maxlength="255" autocomplete="name" required autofocus value="{{ old('name') }}"></label>
    <label class="field"><span>{{ __('account.email') }}</span><input type="email" name="email" maxlength="254" autocomplete="email" required value="{{ old('email') }}"></label>
    <label class="field"><span>{{ __('account.password') }}</span><input type="password" name="password" autocomplete="new-password" required minlength="12"><small>{{ __('account.password_help') }}</small></label>
    <label class="field"><span>{{ __('account.password_confirmation') }}</span><input type="password" name="password_confirmation" autocomplete="new-password" required minlength="12"></label>
    <label class="check-field"><input type="checkbox" name="terms" value="1" required><span>{{ __('account.registration_consent') }}</span></label>
    <button class="primary-button" type="submit">{{ __('account.create_account') }}</button>
    <a class="secondary-action" href="{{ route('login',['locale'=>app()->getLocale()]) }}">{{ __('account.have_account') }}</a>
</form>
@endsection
