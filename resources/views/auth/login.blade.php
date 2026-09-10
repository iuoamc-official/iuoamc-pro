@extends('layouts.auth')

@section('title', __('ui.sign_in'))

@section('content')
    <div class="card-heading">
        <span class="section-mark"></span>
        <div>
            <h2>{{ __('ui.sign_in') }}</h2>
            <p>{{ __('ui.sign_in_intro') }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('login.store', ['locale' => app()->getLocale()]) }}" class="form-stack">
        @csrf
        <label class="field">
            <span>{{ __('ui.email') }}</span>
            <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        </label>

        <label class="field">
            <span>{{ __('ui.password') }}</span>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>

        <label class="check-field">
            <input type="checkbox" name="remember" value="1">
            <span>{{ __('ui.remember_me') }}</span>
        </label>

        <button class="primary-button" type="submit">{{ __('ui.continue') }}</button>
    </form>
@endsection
