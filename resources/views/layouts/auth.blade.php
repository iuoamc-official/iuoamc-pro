<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title') — {{ __('ui.app_name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-control.css') }}?v={{ filemtime(public_path('assets/css/iuoamc-control.css')) }}">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-brand-panel">
            <div class="brand-lockup official-brand-lockup auth-logo-lockup">
                <img
                    src="{{ asset('assets/images/iuoamc-logo-gold-transparent.png') }}"
                    class="brand-logo auth-brand-logo" style="display:block;width:100%;max-width:430px;height:auto;max-height:174px;object-fit:contain"
                    width="1414"
                    height="573"
                    alt="IUOAMC — International Union of Arab Master Chefs"
                    decoding="async"
                >
            </div>
            <div class="auth-brand-copy">
                <span class="eyebrow">GLOBAL INSTITUTIONAL SYSTEM</span>
                <h1>{{ __('ui.control_center') }}</h1>
                <p>{{ __('ui.secure_system') }}</p>
            </div>
            <div class="security-note">
                <span class="status-dot"></span>
                {{ __('ui.operational') }}
            </div>
        </section>

        <section class="auth-form-panel">
            <nav class="language-nav" aria-label="Languages">
                @foreach (['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $code => $label)
                    @php
                        $languageUrl = request()->routeIs('password.change')
                            ? route('password.change', ['locale' => $code])
                            : route('login', ['locale' => $code]);
                    @endphp
                    <a href="{{ $languageUrl }}" class="{{ app()->getLocale() === $code ? 'active' : '' }}">{{ $label }}</a>
                @endforeach
            </nav>
            <div class="auth-card">
                @yield('content')
            </div>
        </section>
    </main>
</body>
</html>
