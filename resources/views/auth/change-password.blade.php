@extends('layouts.auth')

@section('title', __('ui.change_password'))

@section('content')
    <div class="card-heading">
        <span class="section-mark"></span>
        <div>
            <h2>{{ __('ui.change_password') }}</h2>
            <p>{{ __('ui.change_password_intro') }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-error" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('password.update', ['locale' => app()->getLocale()]) }}" class="form-stack">
        @csrf
        @method('PUT')

        <label class="field">
            <span>{{ __('ui.current_password') }}</span>
            <input type="password" name="current_password" autocomplete="current-password" required autofocus>
        </label>

        <label class="field">
            <span>{{ __('ui.new_password') }}</span>
            <input type="password" name="password" autocomplete="new-password" required>
        </label>

        <label class="field">
            <span>{{ __('ui.confirm_password') }}</span>
            <input type="password" name="password_confirmation" autocomplete="new-password" required>
        </label>

        <button class="primary-button" type="submit">{{ __('ui.update_password') }}</button>
    </form>
@endsection
