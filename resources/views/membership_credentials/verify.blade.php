<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale==='ar'?'rtl':'ltr' }}"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>{{ __('memberships.verification_title') }} · IUOAMC</title>
<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-pro-certificates-1.0.0.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-verification-2.0.0.css') }}">
</head><body class="pc-public pc-verification-v2">
<header class="pc-public-header"><a class="pc-brand" href="{{ url('/') }}"><img src="{{ asset('assets/brand/iuoamc-pro-logo.png') }}" width="74" height="74" alt="IUOAMC"><span>IUOAMC<span class="pc-brand-domain">International Union of Arab Master Chefs</span></span></a>
<nav class="pc-languages">@foreach(['ar'=>'العربية','en'=>'EN','fr'=>'FR'] as $code=>$label)<a href="{{ route('memberships.verify',['token'=>$token,'lang'=>$code]) }}" @if($locale===$code) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav></header>
<main class="pc-public-main">
@if($public)
<section class="pc-verified-hero"><div class="pc-verification-seal"><span>✓</span></div><div class="pc-verified-copy"><span class="pc-verified-kicker">{{ __('memberships.official_registry') }}</span><h1>{{ __('memberships.verification_passed') }}</h1><p>{{ __('memberships.verification_summary') }}</p><div class="pc-verification-badges"><span class="pc-verified-badge">PAdES / X.509</span><span class="pc-status-badge">{{ __('memberships.states.'.$public['status']) }}</span></div></div>
<dl class="pc-verification-meta"><div><dt>{{ __('memberships.number') }}</dt><dd><bdi dir="ltr">{{ $public['number'] }}</bdi></dd></div><div><dt>{{ __('memberships.version') }}</dt><dd>{{ $public['version'] }}</dd></div></dl></section>
<article class="pc-credential-card"><header class="pc-credential-header"><div><span>{{ __('memberships.member') }}</span><h2><bdi>{{ $public['full_name'] }}</bdi></h2></div></header>
<div class="pc-credential-identity"><div class="pc-recipient-block"><span>{{ __('memberships.type') }}</span><strong><bdi>{{ $public['membership_type'] }}</bdi></strong></div><div class="pc-program-block"><span>{{ __('memberships.organization') }}</span><strong><bdi>{{ $public['organization'] }}</bdi></strong>@if($public['professional_title'])<small>{{ $public['professional_title'] }}</small>@endif</div></div>
<dl class="pc-public-facts"><div><dt>{{ __('memberships.valid_from') }}</dt><dd><bdi dir="ltr">{{ $public['valid_from'] }}</bdi></dd></div><div><dt>{{ __('memberships.valid_until') }}</dt><dd><bdi dir="ltr">{{ $public['valid_until'] }}</bdi></dd></div><div><dt>{{ __('memberships.issued_at') }}</dt><dd><bdi dir="ltr">{{ $public['issued_at'] }}</bdi></dd></div><div><dt>{{ __('memberships.verified_at') }}</dt><dd><bdi dir="ltr">{{ $public['verified_at'] }}</bdi></dd></div></dl></article>
<section class="pc-security-panel"><header><h2>{{ __('memberships.digital_evidence') }}</h2><p>{{ __('memberships.public_privacy') }}</p></header><div class="pc-security-grid"><article><span>✓</span><div><h3>PAdES</h3><p>{{ $public['pades_profile'] }}</p></div></article><article><span>✓</span><div><h3>SHA-256</h3><p>{{ __('memberships.hashes_verified') }}</p></div></article></div></section>
@else
<section class="pc-verified-hero"><div class="pc-verified-copy"><h1>{{ __('memberships.verification_unavailable') }}</h1><p>{{ __('memberships.verification_unavailable_detail') }}</p></div></section>
@endif
</main></body></html>
