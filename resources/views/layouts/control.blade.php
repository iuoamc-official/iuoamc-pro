<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title') — {{ __('ui.app_name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-control.css') }}?v={{ filemtime(public_path('assets/css/iuoamc-control.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-memberships-1.0.0.css') }}">
    {{-- IUOAMC_PLATFORM_HUB_1_0_1 --}}
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-platform-hub-1.0.1.css') }}">
    {{-- IUOAMC_LEGACY_CERTIFICATE_CSS_1_0_0 --}}
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-legacy-certificates-1.0.0.css') }}">
    {{-- IUOAMC_PRO_CERTIFICATE_CSS_1_0_0 --}}
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-pro-certificates-1.0.0.css') }}">
    @stack('styles')
</head>
<body class="control-body">
    <div class="control-shell" data-control-shell>
        <aside class="sidebar" id="iuoamc-sidebar">
            <div class="brand-lockup sidebar-brand official-brand-lockup">
                <img
                    src="{{ asset('assets/images/iuoamc-logo-gold-transparent.png') }}"
                    class="brand-logo sidebar-brand-logo" style="display:block;width:100%;max-width:226px;height:auto;max-height:92px;object-fit:contain"
                    width="1414"
                    height="573"
                    alt="IUOAMC — International Union of Arab Master Chefs"
                    decoding="async"
                >
            </div>

            {{-- IUOAMC_PLATFORM_NAV_1_0_0 --}}
            @include('control.navigation._sidebar')

            <div class="sidebar-footer">
                <span class="status-dot"></span>
                {{ __('ui.operational') }}
            </div>
        </aside>

        <button
            class="sidebar-overlay"
            type="button"
            data-sidebar-overlay
            tabindex="-1"
            aria-label="{{ __('ui.close_sidebar') }}"
        ></button>

        <section class="main-panel">
            <header class="topbar">
                <div class="topbar-primary">
                    <button
                        class="sidebar-toggle"
                        type="button"
                        data-sidebar-toggle
                        data-hide-label="{{ __('ui.hide_sidebar') }}"
                        data-show-label="{{ __('ui.show_sidebar') }}"
                        aria-controls="iuoamc-sidebar"
                        aria-expanded="true"
                        aria-label="{{ __('ui.hide_sidebar') }}"
                        title="{{ __('ui.hide_sidebar') }}"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 5h16M4 12h16M4 19h16"></path>
                        </svg>
                    </button>
                    <div class="topbar-heading">
                        <span class="eyebrow">IUOAMC / CONTROL</span>
                        <strong>{{ __('ui.control_center') }}</strong>
                    </div>
                </div>

                <div class="topbar-actions">
                    <nav class="language-nav compact" aria-label="Languages">
                        @foreach (['ar' => 'AR', 'en' => 'EN', 'fr' => 'FR'] as $code => $label)
                            <a href="{{ app(\App\Services\ControlNavigation::class)->languageUrl(request(), $code) }}" class="{{ app()->getLocale() === $code ? 'active' : '' }}">{{ $label }}</a>
                        @endforeach
                    </nav>

                    <div class="user-chip" title="{{ auth()->user()->email }}">
                        <span class="user-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                        <span class="user-identity">
                            <bdi dir="auto">{{ auth()->user()->name }}</bdi>
                            <small>{{ __('ui.administrator') }}</small>
                        </span>
                    </div>

                    <form method="post" action="{{ route('logout', ['locale' => app()->getLocale()]) }}">
                        @csrf
                        <button class="logout-button" type="submit">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M10 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M14 8l4 4-4 4M18 12H9"></path>
                            </svg>
                            <span>{{ __('ui.logout') }}</span>
                        </button>
                    </form>
                </div>
            </header>

            <main class="page-content">
                @if (session('success'))
                    <div class="alert alert-success" role="status">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-error" role="alert">{{ session('error') }}</div>
                @endif
                @if(request()->routeIs('organizations.*','users.*','roles.*','audit.*'))
                    @include('control.navigation._governance_links', ['hub'=>false])
                @endif
                @yield('content')
            </main>
        </section>
    </div>
    <script src="{{ asset('assets/js/iuoamc-control.js') }}?v={{ filemtime(public_path('assets/js/iuoamc-control.js')) }}" defer></script>
</body>
</html>
