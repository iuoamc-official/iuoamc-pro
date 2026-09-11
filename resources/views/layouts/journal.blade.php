<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#071b2d">
    <meta name="description" content="@yield('description', $journal->localized('description'))">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('title', $journal->localized('name'))">
    <meta property="og:description" content="@yield('description', $journal->localized('description'))">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @foreach($localizedUrls as $language => $localizedUrl)
        <link rel="alternate" hreflang="{{ $language }}" href="{{ url($localizedUrl) }}">
    @endforeach
    <title>@yield('title', $journal->localized('name')) — IUOAMC</title>
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-public-1.0.0.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-journal-1.0.0.css') }}">
    @stack('metadata')
</head>
<body class="journal-public">
    <a class="skip-link" href="#journal-main">{{ __('public_site.skip') }}</a>
    <header class="journal-header">
        <div class="public-container journal-header-row">
            <a class="journal-brand" href="{{ route('journal.public.index', ['locale' => app()->getLocale()]) }}">
                <img src="{{ asset($siteProfile['primary_logo']) }}" alt="IUOAMC" width="1414" height="573">
                <span><b>MCIJ</b><small>{{ $journal->localized('name') }}</small></span>
            </a>
            <nav class="journal-nav" aria-label="{{ __('journal.navigation') }}">
                <a href="{{ route('journal.public.index', ['locale' => app()->getLocale()]) }}">{{ __('journal.home') }}</a>
                <a href="{{ route('journal.public.index', ['locale' => app()->getLocale(), 'type' => 'peer_reviewed_research']) }}">{{ __('journal.types.peer_reviewed_research') }}</a>
                <a href="{{ route('journal.public.index', ['locale' => app()->getLocale(), 'type' => 'professional_article']) }}">{{ __('journal.types.professional_article') }}</a>
                <a href="{{ route('journal.public.issues.index', ['locale' => app()->getLocale()]) }}">{{ __('journal.issues') }}</a>
                <a href="{{ route('journal.public.policies', ['locale' => app()->getLocale()]) }}">{{ __('journal.policies') }}</a>
            </nav>
            <nav class="journal-languages" aria-label="{{ __('public_site.languages') }}">
                @foreach(['ar' => 'ع', 'en' => 'EN', 'fr' => 'FR'] as $language => $label)
                    <a class="{{ app()->getLocale() === $language ? 'active' : '' }}" hreflang="{{ $language }}" href="{{ url($localizedUrls[$language]) }}">{{ $label }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main id="journal-main">@yield('content')</main>

    <footer class="journal-footer">
        <div class="public-container journal-footer-grid">
            <div><strong>{{ $journal->localized('name') }}</strong><p>{{ $journal->localized('description') }}</p></div>
            <div><span>{{ __('journal.publisher') }}</span><b>{{ $journal->publisher_name }}</b></div>
            <div><a href="{{ route('public.home', ['locale' => app()->getLocale()]) }}">IUOAMC.PRO</a><a href="{{ route('journal.public.policies', ['locale' => app()->getLocale()]) }}">{{ __('journal.editorial_integrity') }}</a></div>
        </div>
    </footer>
</body>
</html>
