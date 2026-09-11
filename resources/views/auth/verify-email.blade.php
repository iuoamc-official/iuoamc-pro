@extends('layouts.auth')
@section('title', __('account.verify_email'))
@section('content')
<div class="card-heading"><span class="section-mark"></span><div><h2>{{ __('account.verify_email') }}</h2><p>{{ __('account.verify_email_intro',['email'=>auth()->user()->email]) }}</p></div></div>
@if(session('status')==='verification-link-sent')<div class="alert alert-success" role="status">{{ __('account.verification_resent') }}</div>@endif
<form method="post" action="{{ route('verification.send',['locale'=>app()->getLocale()]) }}" class="form-stack">@csrf<button class="primary-button" type="submit">{{ __('account.resend_verification') }}</button></form>
<form method="post" action="{{ route('logout',['locale'=>app()->getLocale()]) }}" class="form-stack">@csrf<button class="secondary-action" type="submit">{{ __('account.logout') }}</button></form>
@endsection
