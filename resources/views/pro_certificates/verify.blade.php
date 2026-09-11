<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('certificates.public.title') }} · IUOAMC</title>
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-pro-certificates-1.0.0.css') }}?v={{ filemtime(public_path('assets/css/iuoamc-pro-certificates-1.0.0.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-verification-2.0.0.css') }}?v={{ filemtime(public_path('assets/css/iuoamc-verification-2.0.0.css')) }}">
</head>
<body class="pc-public pc-verification-v2">
    <header class="pc-public-header">
        <a class="pc-brand" href="{{ url('/') }}" aria-label="{{ __('certificates.public.home') }}">
            <img src="{{ asset('assets/brand/iuoamc-pro-logo.png') }}" alt="IUOAMC" width="74" height="74">
            <span>IUOAMC<span class="pc-brand-domain">International Union of Arab Master Chefs</span></span>
        </a>
        <nav class="pc-languages" aria-label="{{ __('certificates.public.language') }}">
            @foreach(['ar' => 'العربية', 'en' => 'EN', 'fr' => 'FR'] as $code => $label)
                <a href="{{ route('pro-certificates.verify', ['token' => $token, 'lang' => $code]) }}" lang="{{ $code }}" hreflang="{{ $code }}" @if($locale === $code) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </header>

    <main class="pc-public-main">
        @if($public)
            <section class="pc-verified-hero" aria-labelledby="verification-title">
                <div class="pc-verification-seal" aria-hidden="true"><span>✓</span></div>
                <div class="pc-verified-copy">
                    <span class="pc-verified-kicker">{{ __('certificates.public.official_registry') }}</span>
                    <h1 id="verification-title">{{ __('certificates.public.verified_title') }}</h1>
                    <p>{{ __('certificates.public.verified_summary') }}</p>
                    <div class="pc-verification-badges">
                        <span class="pc-verified-badge">{{ __('certificates.public.authenticity_passed') }}</span>
                        <span class="pc-status-badge pc-status-{{ $public['status'] }}">{{ __('certificates.states.'.$public['status']) }}</span>
                    </div>
                </div>
                <dl class="pc-verification-meta">
                    <div><dt>{{ __('certificates.public.verified_at') }}</dt><dd><bdi dir="ltr">{{ $public['verified_at'] }}</bdi></dd></div>
                    <div><dt>{{ __('certificates.public.registry_reference') }}</dt><dd><bdi dir="ltr">{{ $public['number'] }}</bdi></dd></div>
                </dl>
            </section>

            <section class="pc-public-state pc-public-state-{{ $public['status'] }}" aria-label="{{ __('certificates.public.state') }}">
                <div><span>{{ __('certificates.public.state') }}</span><strong>{{ __('certificates.states.'.$public['status']) }}</strong></div>
                <p>{{ __('certificates.public.'.$public['status'].'_notice') }}</p>
            </section>

            <article class="pc-credential-card">
                <header class="pc-credential-header">
                    <div><span>{{ __('certificates.public.credential_details') }}</span><h2><bdi>{{ $public['title'] }}</bdi></h2></div>
                    @if(!empty($public['type_code']))<span class="pc-type-code"><bdi dir="ltr">{{ $public['type_code'] }}</bdi></span>@endif
                </header>
                <div class="pc-credential-identity">
                    <div class="pc-recipient-block"><span>{{ __('certificates.public.public_name') }}</span><strong><bdi>{{ $public['public_name'] }}</bdi></strong></div>
                    <div class="pc-program-block"><span>{{ $public['type_name'] ?? __('certificates.types.'.$public['type']) }}</span><strong><bdi>{{ $public['program'] }}</bdi></strong>@if(!empty($public['specialization']))<small><bdi>{{ $public['specialization'] }}</bdi></small>@endif</div>
                </div>
                <dl class="pc-public-facts">
                    <div class="pc-public-full pc-certificate-number"><dt>{{ __('certificates.number') }}</dt><dd><bdi class="pc-number" dir="ltr">{{ $public['number'] }}</bdi></dd></div>
                    <div><dt>{{ __('certificates.achievement_date') }}</dt><dd><bdi dir="ltr">{{ $public['achievement_date'] }}</bdi></dd></div>
                    <div><dt>{{ __('certificates.issued_at') }}</dt><dd><bdi dir="ltr">{{ $public['issued_at'] }}</bdi></dd></div>
                    <div><dt>{{ __('certificates.expires_on') }}</dt><dd><bdi dir="ltr">{{ $public['expires_on'] ?: __('certificates.no_expiry') }}</bdi></dd></div>
                    <div><dt>{{ __('certificates.public.issuer') }}</dt><dd><bdi>{{ $public['issuer'] }}</bdi></dd></div>
                    @if(!empty($public['credential_basis']))<div><dt>{{ __('certificates.credential_basis') }}</dt><dd>{{ __('certificates.credential_bases.'.$public['credential_basis']) }}</dd></div>@endif
                    @if(!empty($public['accreditation_reference']))<div><dt>{{ __('certificates.accreditation_reference') }}</dt><dd><bdi dir="ltr">{{ $public['accreditation_reference'] }}</bdi></dd></div>@endif
                    @if(!empty($public['accreditation_date']))<div><dt>{{ __('certificates.accreditation_date') }}</dt><dd><bdi dir="ltr">{{ $public['accreditation_date'] }}</bdi></dd></div>@endif
                    @if(!empty($public['program_ip_code']))<div class="pc-public-full"><dt>{{ __('certificate_catalog.program_ip_code') }}</dt><dd><bdi class="pc-number" dir="ltr">{{ $public['program_ip_code'] }}</bdi></dd></div>@endif
                </dl>
            </article>

            <section class="pc-security-panel" aria-labelledby="security-title">
                <header><span>{{ __('certificates.public.protected_record') }}</span><h2 id="security-title">{{ __('certificates.public.security_layers') }}</h2><p>{{ __('certificates.public.security_layers_intro') }}</p></header>
                <div class="pc-security-grid">
                    @foreach(['signed_record', 'audit_binding', 'pdf_integrity', 'lifecycle'] as $layer)
                        <article><span aria-hidden="true">✓</span><div><h3>{{ __('certificates.public.'.$layer) }}</h3><p>{{ __('certificates.public.'.$layer.'_desc') }}</p></div></article>
                    @endforeach
                </div>
            </section>

            <section class="pc-public-privacy">
                <div class="pc-privacy-copy"><span aria-hidden="true">◈</span><div><h2>{{ __('certificates.public.privacy_title') }}</h2><p>{{ __('certificates.public.privacy') }}</p></div></div>
                <details>
                    <summary>{{ __('certificates.public.technical_evidence') }}</summary>
                    <p>{{ __('certificates.public.proof_notice') }}</p>
                    <p>{{ __('certificates.public.pdf_signature_notice') }}</p>
                    <dl class="pc-technical-facts">
                        <div><dt>{{ __('certificates.public.record_uuid') }}</dt><dd><code>{{ $public['record_uuid'] }}</code></dd></div>
                        <div><dt>{{ __('certificates.public.signing_key_id') }}</dt><dd><code>{{ $public['signing_key_id'] }}</code></dd></div>
                        <div><dt>{{ __('certificates.public.pdf_fingerprint') }}</dt><dd><code>{{ $public['pdf_sha256'] }}</code></dd></div>
                        <div><dt>{{ __('certificates.public.payload_fingerprint') }}</dt><dd><code>{{ $public['payload_sha256'] }}</code></dd></div>
                    </dl>
                    <a class="pc-proof-link" href="{{ route('pro-certificates.proof', ['token' => $token, 'lang' => $locale]) }}" rel="nofollow">{{ __('certificates.public.open_machine_proof') }} <span dir="ltr">JSON ↗</span></a>
                </details>
            </section>
        @else
            <article class="pc-verification-card pc-unavailable">
                <span class="pc-unavailable-mark" aria-hidden="true">!</span>
                <span class="pc-verified-kicker">{{ __('certificates.public.official_registry') }}</span>
                <h1>{{ __('certificates.public.unavailable') }}</h1>
                <p>{{ __('certificates.public.unavailable_detail') }}</p>
            </article>
        @endif
    </main>

    <footer class="pc-public-footer"><span>IUOAMC · iuoamc.pro</span><p>{{ __('certificates.public.footer') }}</p></footer>
</body>
</html>
