<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale==='ar'?'rtl':'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('certificates.public.title') }} · IUOAMC</title>
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-pro-certificates-1.0.0.css') }}">
</head>
<body class="pc-public">
    <header class="pc-public-header"><a class="pc-brand" href="{{ url('/') }}" aria-label="{{ __('certificates.public.home') }}"><img src="{{ asset('assets/brand/iuoamc-pro-logo.png') }}" alt="IUOAMC" width="74" height="74"><span>IUOAMC<span class="pc-brand-domain">iuoamc.pro</span></span></a>
        <nav class="pc-languages" aria-label="{{ __('certificates.public.language') }}">@foreach(['ar'=>'العربية','en'=>'EN','fr'=>'FR'] as $code=>$label)<a href="{{ route('pro-certificates.verify',['token'=>$token,'lang'=>$code]) }}" lang="{{ $code }}" hreflang="{{ $code }}" @if($locale===$code)aria-current="page"@endif>{{ $label }}</a>@endforeach</nav>
    </header>
    <main class="pc-public-main">
        <div class="pc-public-intro"><span class="pc-eyebrow">{{ __('certificates.public.eyebrow') }}</span><h1>{{ __('certificates.public.title') }}</h1><p>{{ __('certificates.public.intro') }}</p></div>
        @if($public)
        <article class="pc-verification-card">
            <div class="pc-verification-heading"><div class="pc-authentic-mark" aria-hidden="true">✓</div><div><span class="pc-eyebrow">{{ __('certificates.public.authenticity') }}</span><h2>{{ __('certificates.public.authenticity_passed') }}</h2></div></div>
            <p class="pc-verification-explanation">{{ __('certificates.public.authenticity_notice') }}</p>
            <section class="pc-public-state pc-public-state-{{ $public['status'] }}"><div><span>{{ __('certificates.public.state') }}</span><strong>{{ __('certificates.states.'.$public['status']) }}</strong></div><p>{{ __('certificates.public.'.$public['status'].'_notice') }}</p></section>
            <div class="pc-public-certificate"><span class="pc-eyebrow">{{ $public['type_name'] ?? __('certificates.types.'.$public['type']) }}</span><h2><bdi>{{ $public['title'] }}</bdi></h2><p class="pc-public-recipient"><span>{{ __('certificates.public.public_name') }}</span><strong><bdi>{{ $public['public_name'] }}</bdi></strong></p><p class="pc-public-program"><bdi>{{ $public['program'] }}</bdi></p></div>
            <dl class="pc-public-facts">
                <div class="pc-public-full"><dt>{{ __('certificates.number') }}</dt><dd><bdi class="pc-number" dir="ltr">{{ $public['number'] }}</bdi></dd></div>
                @if(!empty($public['specialization']))<div class="pc-public-full"><dt>{{ __('certificate_catalog.specialization') }}</dt><dd><bdi>{{ $public['specialization'] }}</bdi></dd></div>@endif
                @if(!empty($public['type_code']))<div><dt>{{ __('certificate_catalog.type_code') }}</dt><dd><bdi dir="ltr">{{ $public['type_code'] }}</bdi></dd></div>@endif
                <div><dt>{{ __('certificates.achievement_date') }}</dt><dd><bdi dir="ltr">{{ $public['achievement_date'] }}</bdi></dd></div>
                <div><dt>{{ __('certificates.issued_at') }}</dt><dd><bdi dir="ltr">{{ $public['issued_at'] }}</bdi></dd></div>
                <div><dt>{{ __('certificates.expires_on') }}</dt><dd><bdi dir="ltr">{{ $public['expires_on'] ?: __('certificates.no_expiry') }}</bdi></dd></div>
                <div><dt>{{ __('certificates.public.issuer') }}</dt><dd><bdi>{{ $public['issuer'] }}</bdi></dd></div>
            </dl>
            <div class="pc-public-privacy"><p>{{ __('certificates.public.privacy') }}</p><details><summary>{{ __('certificates.public.proof') }}</summary><p>{{ __('certificates.public.proof_notice') }}</p><a class="pc-text-link" href="{{ route('pro-certificates.proof',['token'=>$token,'lang'=>$locale]) }}" rel="nofollow">{{ __('certificates.public.proof') }} <span dir="ltr">(JSON)</span></a></details></div>
        </article>
        @else
        <article class="pc-verification-card pc-unavailable"><span class="pc-unavailable-mark" aria-hidden="true">—</span><h2>{{ __('certificates.public.unavailable') }}</h2><p>{{ __('certificates.public.unavailable_detail') }}</p></article>
        @endif
    </main>
    <footer class="pc-public-footer"><span>IUOAMC · iuoamc.pro</span><p>{{ __('certificates.public.footer') }}</p></footer>
</body>
</html>
