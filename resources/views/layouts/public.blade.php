<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $page->localized('seo_description') }}">
    <meta name="theme-color" content="#061b2d">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $page->localized('seo_title') }}">
    <meta property="og:description" content="{{ $page->localized('seo_description') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @foreach(['ar', 'en', 'fr'] as $language)
        <link rel="alternate" hreflang="{{ $language }}" href="{{ $page->slug === 'home' ? route('public.home', ['locale' => $language]) : route('public.pages.show', ['locale' => $language, 'public_page' => $page]) }}">
    @endforeach
    <title>{{ $page->localized('seo_title') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-public-1.0.0.css') }}?v={{ filemtime(public_path('assets/css/iuoamc-public-1.0.0.css')) }}">
    <script type="application/ld+json" nonce="{{ $cspNonce }}">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $siteProfile['legal_name'],
        'alternateName' => $siteProfile['brand_name'],
        'url' => route('public.home', ['locale' => app()->getLocale()]),
        'logo' => asset($siteProfile['primary_logo']),
        'email' => $siteProfile['contact_email'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
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
                    <a class="{{ $page->is($item) ? 'active' : '' }}" href="{{ route('public.pages.show', ['locale' => app()->getLocale(), 'public_page' => $item]) }}">{{ $item->localized('navigation_label') }}</a>
                @endforeach
            </nav>
            <div class="header-tools">
                <nav class="public-languages" aria-label="{{ __('public_site.languages') }}">
                    @foreach(['ar' => 'ع', 'en' => 'EN', 'fr' => 'FR'] as $language => $label)
                        <a class="{{ app()->getLocale() === $language ? 'active' : '' }}" hreflang="{{ $language }}" href="{{ $page->slug === 'home' ? route('public.home', ['locale' => $language]) : route('public.pages.show', ['locale' => $language, 'public_page' => $page]) }}">{{ $label }}</a>
                    @endforeach
                </nav>
                @auth
                    <a class="control-link" href="{{ route('dashboard', ['locale' => app()->getLocale()]) }}">{{ __('public_site.control_center') }}</a>
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
    <script src="{{ asset('assets/js/iuoamc-public-1.0.0.js') }}?v={{ filemtime(public_path('assets/js/iuoamc-public-1.0.0.js')) }}" defer></script>
</body>
</html>
