<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>@yield('title') — IUOAMC</title>
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-account-1.0.0.css') }}?v={{ filemtime(public_path('assets/css/iuoamc-account-1.0.0.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/membership-policy.css') }}?v={{ filemtime(public_path('assets/css/membership-policy.css')) }}">
</head>
<body class="account-body">
    <header class="account-header">
        <div class="account-container account-header-row">
            <a class="account-brand" href="{{ route('public.home',['locale'=>app()->getLocale()]) }}">
                <img src="{{ asset('assets/images/iuoamc-logo-gold-transparent.png') }}" alt="IUOAMC">
            </a>
            <nav class="account-nav" aria-label="{{ __('account.navigation') }}">
                <a href="{{ route('account.dashboard',['locale'=>app()->getLocale()]) }}">{{ __('account.my_account') }}</a>
                <a href="{{ route('account.dashboard',['locale'=>app()->getLocale()]) }}#memberships">{{ __('account.memberships') }}</a>
                <a href="{{ route('account.dashboard',['locale'=>app()->getLocale()]) }}#certificates">{{ __('account.certificates') }}</a>
                <a href="{{ route('account.dashboard',['locale'=>app()->getLocale()]) }}#documents">{{ __('account.documents') }}</a>
                <a href="{{ route('account.membership.create',['locale'=>app()->getLocale()]) }}">{{ __('account.apply_membership') }}</a>
                @if(auth()->user()->canAccessControl())
                    <a class="admin-link" href="{{ route('dashboard',['locale'=>app()->getLocale()]) }}">{{ __('account.control_center') }}</a>
                @endif
            </nav>
            <details class="account-user-menu">
                <summary><span>{{ mb_substr(auth()->user()->name,0,1) }}</span><bdi>{{ auth()->user()->name }}</bdi></summary>
                <div>
                    <a href="{{ route('account.dashboard',['locale'=>app()->getLocale()]) }}#profile">{{ __('account.my_profile') }}</a>
                    <a href="{{ route('account.dashboard',['locale'=>app()->getLocale()]) }}#memberships">{{ __('account.memberships') }}</a>
                    <a href="{{ route('account.dashboard',['locale'=>app()->getLocale()]) }}#certificates">{{ __('account.certificates') }}</a>
                    <a href="{{ route('account.dashboard',['locale'=>app()->getLocale()]) }}#documents">{{ __('account.documents_invoices_receipts') }}</a>
                    @if(auth()->user()->canAccessControl())
                        <a href="{{ route('dashboard',['locale'=>app()->getLocale()]) }}">{{ __('account.control_center') }}</a>
                    @endif
                    <form method="post" action="{{ route('logout',['locale'=>app()->getLocale()]) }}">@csrf<button type="submit">{{ __('account.logout') }}</button></form>
                </div>
            </details>
        </div>
    </header>
    <main class="account-container account-main">
        @if(session('success'))<div class="account-alert success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="account-alert error" role="alert">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</body>
</html>
