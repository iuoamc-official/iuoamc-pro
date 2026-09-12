<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $page->localized('seo_description') }}">
    <meta name="theme-color" content="#061b2d">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $page->localized('seo_title') }}">
    <meta property="og:description" content="{{ $page->localized('seo_description') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @php
        $localizedUrl = static fn (string $language): string => $page->slug === 'home'
            ? route('public.home', ['locale' => $language])
            : ($page->template === 'entity'
                ? route('public.entities.show', ['locale' => $language, 'entity' => $entity['slug']])
                : route('public.pages.show', ['locale' => $language, 'public_page' => $page]));
    @endphp
    @foreach(['ar', 'en', 'fr'] as $language)
        <link rel="alternate" hreflang="{{ $language }}" href="{{ $localizedUrl($language) }}">
    @endforeach
    <title>{{ $page->localized('seo_title') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-public-1.0.0.css') }}?v={{ filemtime(public_path('assets/css/iuoamc-public-1.0.0.css')) }}">
    @php
    $structuredData = [
        chr(64).'context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $siteProfile['legal_name'],
        'alternateName' => $siteProfile['brand_name'],
        'url' => route('public.home', ['locale' => app()->getLocale()]),
        'logo' => asset($siteProfile['primary_logo']),
        'email' => $siteProfile['contact_email'],
    ];
    if ($page->template === 'leadership') {
        $structuredData = [
            chr(64).'context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => 'Ahmad Maadarani',
            'honorificPrefix' => 'Engineer & Master Chef',
            'jobTitle' => 'President General, Authorised Signatory, Founder and Principal Codifier of International Arbitration in Culinary Arts and Gastronomy',
            'knowsAbout' => ['International Arbitration in Culinary Arts and Gastronomy', 'Professional tasting', 'Sensory analysis', 'Culinary standards'],
            'url' => url()->current(),
            'image' => asset('assets/brand/leadership/ahmad-maadarani-president-general-v1.webp'),
            'worksFor' => [
                '@type' => 'Organization',
                'name' => $siteProfile['legal_name'],
                'url' => route('public.home', ['locale' => app()->getLocale()]),
            ],
        ];
    }
    @endphp
    <script type="application/ld+json" nonce="{{ request()->attributes->get('csp_nonce') }}">{!! json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</head>
<body>
    <a class="skip-link" href="#main">{{ __('public_site.skip') }}</a>
    @if($siteProfile['announcement'][app()->getLocale()] ?? '')
        <div class="public-announcement" role="status">{{ $siteProfile['announcement'][app()->getLocale()] }}</div>
    @endif
    <header class="public-header" data-public-header>
        <div class="public-container header-row">
            <a class="public-brand" href="{{ route('public.home', ['locale' => app()->getLocale()]) }}" aria-label="IUOAMC">
                <img src="{{ asset($siteProfile['primary_logo']) }}" alt="{{ $siteProfile['legal_name'] }}" width="1414" height="573">
            </a>
            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="public-navigation" data-nav-toggle><span></span><span></span><span></span><b class="sr-only">{{ __('public_site.menu') }}</b></button>
            <nav id="public-navigation" class="public-navigation" aria-label="{{ __('public_site.navigation') }}" data-navigation>
                <a class="{{ $page->slug === 'home' ? 'active' : '' }}" href="{{ route('public.home', ['locale' => app()->getLocale()]) }}">{{ __('public_site.home') }}</a>
                @foreach($navigation as $item)
                    <a class="{{ $page->is($item) || ($page->template === 'entity' && $item->slug === 'entities') ? 'active' : '' }}" href="{{ route('public.pages.show', ['locale' => app()->getLocale(), 'public_page' => $item]) }}">{{ $item->localized('navigation_label') }}</a>
                @endforeach
                @guest
                    <div class="mobile-auth-actions" aria-label="{{ __('account.secure_portal') }}">
                        <a href="{{ route('login',['locale'=>app()->getLocale()]) }}">{{ __('ui.sign_in') }}</a>
                        <a class="primary" href="{{ route('register',['locale'=>app()->getLocale()]) }}">{{ __('account.create_account') }}</a>
                    </div>
                @endguest
            </nav>
            <div class="header-tools">
                <nav class="public-languages" aria-label="{{ __('public_site.languages') }}">
                    @foreach(['ar' => 'ع', 'en' => 'EN', 'fr' => 'FR'] as $language => $label)
                        <a class="{{ app()->getLocale() === $language ? 'active' : '' }}" hreflang="{{ $language }}" href="{{ $localizedUrl($language) }}">{{ $label }}</a>
                    @endforeach
                </nav>
                @auth
                    <details class="public-account-menu">
                        <summary><span>{{ mb_substr(auth()->user()->name,0,1) }}</span><bdi>{{ auth()->user()->name }}</bdi></summary>
                        <div>
                            <a href="{{ route('account.dashboard',['locale'=>app()->getLocale()]) }}#profile">{{ __('account.my_profile') }}</a>
                            <a href="{{ route('account.dashboard',['locale'=>app()->getLocale()]) }}#memberships">{{ __('account.memberships') }}</a>
                            <a href="{{ route('account.dashboard',['locale'=>app()->getLocale()]) }}#certificates">{{ __('account.certificates') }}</a>
                            <a href="{{ route('account.dashboard',['locale'=>app()->getLocale()]) }}#documents">{{ __('account.documents_invoices_receipts') }}</a>
                            <a href="{{ route('account.membership.create',['locale'=>app()->getLocale()]) }}">{{ __('account.apply_membership') }}</a>
                            @if(auth()->user()->canAccessControl())
                                <a href="{{ route('dashboard',['locale'=>app()->getLocale()]) }}">{{ __('public_site.control_center') }}</a>
                            @endif
                            <form method="post" action="{{ route('logout',['locale'=>app()->getLocale()]) }}">@csrf<button type="submit">{{ __('account.logout') }}</button></form>
                        </div>
                    </details>
                @else
                    <div class="guest-auth-actions" aria-label="{{ __('account.secure_portal') }}">
                        <a class="control-link" href="{{ route('login',['locale'=>app()->getLocale()]) }}">{{ __('ui.sign_in') }}</a>
                        <a class="register-link" href="{{ route('register',['locale'=>app()->getLocale()]) }}">{{ __('account.create_account') }}</a>
                    </div>
                @endauth
            </div>
        </div>
    </header>

    <main id="main">@yield('content')</main>

    <footer class="public-footer">
        <div class="public-container footer-grid">
            <div class="footer-brand"><img src="{{ asset($siteProfile['primary_logo']) }}" alt="IUOAMC"><p>{{ __('public_site.footer_statement') }}</p></div>
            <div><span class="footer-label">{{ __('public_site.registry') }}</span><strong>{{ $siteProfile['registration_label'] }}</strong><bdi>{{ $siteProfile['registration_number'] }}</bdi></div>
            <div><span class="footer-label">{{ __('public_site.contact') }}</span><a href="mailto:{{ $siteProfile['contact_email'] }}">{{ $siteProfile['contact_email'] }}</a><a href="{{ route('login', ['locale' => app()->getLocale()]) }}">{{ __('public_site.secure_access') }}</a></div>
        </div>
        <div class="public-container footer-floor"><span>© {{ now()->year }} IUOAMC</span><span>{{ __('public_site.integrity_line') }}</span></div>
    </footer>
    @include('public._ai_concierge')
</body>
</html>
