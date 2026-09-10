<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <meta name="color-scheme" content="light">
    <title>@yield('title', __('certificate_intake.public_title')) — IUOAMC</title>
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-certificate-intake-1.0.0.css') }}">
</head>
<body class="ci-public">
<a class="ci-skip" href="#ci-main">{{ __('certificate_intake.skip_to_form') }}</a>
<header class="ci-masthead">
    <div class="ci-masthead-inner">
        <div class="ci-brand"><img src="{{ asset('assets/brand/master-v1/iuoamc-original.png') }}" alt="International Union of Arab Master Chefs — IUOAMC" width="282" height="114"></div>
        @yield('languages')
    </div>
</header>
<main id="ci-main" class="ci-public-main" tabindex="-1">@yield('content')</main>
<footer class="ci-public-footer"><strong>IUOAMC</strong><span>{{ __('certificate_intake.footer') }}</span></footer>
</body>
</html>
