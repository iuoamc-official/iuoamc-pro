<!doctype html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#07182b">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('certificates.public.title') }} · IUOAMC</title>
    <link rel="stylesheet" href="{{ asset('assets/css/iuoamc-verification-2.0.0.css') }}">
</head>
<body class="pc-public pc-public--{{ $public['status'] ?? 'unavailable' }}">
    <a class="pc-skip-link" href="#verification-content">{{ __('certificates.public.skip_to_content') }}</a>

    <header class="pc-public-header">
        <div class="pc-header-shell">
            <a class="pc-brand" href="{{ url('/') }}" aria-label="{{ __('certificates.public.home') }}">
                <img src="{{ asset('assets/brand/iuoamc-pro-logo.png') }}" alt="" width="76" height="76">
                <span class="pc-brand-copy">
                    <strong>IUOAMC</strong>
                    <span>{{ __('certificates.public.service_name') }}</span>
                </span>
            </a>

            <nav class="pc-languages" aria-label="{{ __('certificates.public.language') }}">
                @foreach(['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $code => $label)
                    <a href="{{ route('pro-certificates.verify', ['token' => $token, 'lang' => $code]) }}"
                       lang="{{ $code }}"
                       hreflang="{{ $code }}"
                       @if($locale === $code) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="pc-public-main" id="verification-content">
        <header class="pc-public-intro">
            <span class="pc-eyebrow">{{ __('certificates.public.eyebrow') }}</span>
            <h1>{{ __('certificates.public.title') }}</h1>
            <p>{{ __('certificates.public.intro') }}</p>
        </header>

        @if($public)
            <article class="pc-verification-card" aria-labelledby="certificate-title">
                <header class="pc-result-header">
                    <div class="pc-authentic-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" role="img">
                            <path d="m7.5 12.5 3 3 6-7"></path>
                        </svg>
                    </div>
                    <div class="pc-result-copy">
                        <span class="pc-kicker">{{ __('certificates.public.authenticity') }}</span>
                        <h2>{{ __('certificates.public.authenticity_passed') }}</h2>
                        <p>{{ __('certificates.public.authenticity_notice') }}</p>
                    </div>
                    <div class="pc-status-seal pc-status-seal--{{ $public['status'] }}" role="status">
                        <span>{{ __('certificates.public.state') }}</span>
                        <strong>{{ __('certificates.states.'.$public['status']) }}</strong>
                    </div>
                </header>

                <section class="pc-state-notice pc-state-notice--{{ $public['status'] }}" aria-label="{{ __('certificates.public.state') }}">
                    <span class="pc-state-dot" aria-hidden="true"></span>
                    <p>{{ __('certificates.public.'.$public['status'].'_notice') }}</p>
                </section>

                <div class="pc-verification-layout">
                    <div class="pc-record-column">
                        <section class="pc-certificate-identity">
                            <span class="pc-eyebrow">{{ $public['type_name'] ?? __('certificates.types.'.$public['type']) }}</span>
                            <h2 id="certificate-title"><bdi>{{ $public['title'] }}</bdi></h2>
                            <p class="pc-public-recipient">
                                <span>{{ __('certificates.public.public_name') }}</span>
                                <strong><bdi>{{ $public['public_name'] }}</bdi></strong>
                            </p>
                            <p class="pc-public-program"><bdi>{{ $public['program'] }}</bdi></p>
                        </section>

                        <section class="pc-record-details" aria-labelledby="record-details-title">
                            <div class="pc-section-heading">
                                <span class="pc-section-number" aria-hidden="true">01</span>
                                <div>
                                    <h3 id="record-details-title">{{ __('certificates.public.record_details') }}</h3>
                                    <p>{{ __('certificates.public.record_details_intro') }}</p>
                                </div>
                            </div>

                            <dl class="pc-public-facts">
                                <div class="pc-public-full pc-reference-fact">
                                    <dt>{{ __('certificates.number') }}</dt>
                                    <dd><bdi class="pc-number" dir="ltr">{{ $public['number'] }}</bdi></dd>
                                </div>
                                @if(!empty($public['specialization']))
                                    <div class="pc-public-full">
                                        <dt>{{ __('certificate_catalog.specialization') }}</dt>
                                        <dd><bdi>{{ $public['specialization'] }}</bdi></dd>
                                    </div>
                                @endif
                                @if(!empty($public['type_code']))
                                    <div>
                                        <dt>{{ __('certificate_catalog.type_code') }}</dt>
                                        <dd><bdi dir="ltr">{{ $public['type_code'] }}</bdi></dd>
                                    </div>
                                @endif
                                <div>
                                    <dt>{{ __('certificates.achievement_date') }}</dt>
                                    <dd><time datetime="{{ $public['achievement_date'] }}"><bdi dir="ltr">{{ $public['achievement_date'] }}</bdi></time></dd>
                                </div>
                                <div>
                                    <dt>{{ __('certificates.issued_at') }}</dt>
                                    <dd><time datetime="{{ $public['issued_at'] }}"><bdi dir="ltr">{{ $public['issued_at'] }}</bdi></time></dd>
                                </div>
                                <div>
                                    <dt>{{ __('certificates.expires_on') }}</dt>
                                    <dd>
                                        @if($public['expires_on'])
                                            <time datetime="{{ $public['expires_on'] }}"><bdi dir="ltr">{{ $public['expires_on'] }}</bdi></time>
                                        @else
                                            {{ __('certificates.no_expiry') }}
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt>{{ __('certificates.public.issuer') }}</dt>
                                    <dd><bdi>{{ $public['issuer'] }}</bdi></dd>
                                </div>
                            </dl>
                        </section>
                    </div>

                    <aside class="pc-assurance-panel" aria-labelledby="assurance-title">
                        <div class="pc-assurance-heading">
                            <span class="pc-section-number" aria-hidden="true">02</span>
                            <h3 id="assurance-title">{{ __('certificates.public.assurance_title') }}</h3>
                        </div>
                        <ol class="pc-assurance-list">
                            <li>
                                <span aria-hidden="true">1</span>
                                <div>
                                    <strong>{{ __('certificates.public.assurance_record') }}</strong>
                                    <p>{{ __('certificates.public.assurance_record_text') }}</p>
                                </div>
                            </li>
                            <li>
                                <span aria-hidden="true">2</span>
                                <div>
                                    <strong>{{ __('certificates.public.assurance_status') }}</strong>
                                    <p>{{ __('certificates.public.assurance_status_text') }}</p>
                                </div>
                            </li>
                            <li>
                                <span aria-hidden="true">3</span>
                                <div>
                                    <strong>{{ __('certificates.public.assurance_privacy') }}</strong>
                                    <p>{{ __('certificates.public.assurance_privacy_text') }}</p>
                                </div>
                            </li>
                        </ol>

                        <div class="pc-issuer-block">
                            <span>{{ __('certificates.public.issuer') }}</span>
                            <strong><bdi>{{ $public['issuer'] }}</bdi></strong>
                            <small>iuoamc.pro</small>
                        </div>
                    </aside>
                </div>

                <footer class="pc-evidence-panel">
                    <div class="pc-privacy-note">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="5" y="10" width="14" height="10" rx="2"></rect>
                            <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                        </svg>
                        <p>{{ __('certificates.public.privacy') }}</p>
                    </div>
                    <details>
                        <summary>{{ __('certificates.public.proof') }}</summary>
                        <div class="pc-proof-content">
                            <p>{{ __('certificates.public.proof_notice') }}</p>
                            <a class="pc-proof-link" href="{{ route('pro-certificates.proof', ['token' => $token, 'lang' => $locale]) }}" rel="nofollow">
                                {{ __('certificates.public.open_proof') }} <span dir="ltr">JSON</span>
                            </a>
                        </div>
                    </details>
                </footer>
            </article>
        @else
            <article class="pc-verification-card pc-unavailable" aria-labelledby="unavailable-title">
                <div class="pc-unavailable-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 8v5"></path>
                        <path d="M12 17h.01"></path>
                        <circle cx="12" cy="12" r="9"></circle>
                    </svg>
                </div>
                <span class="pc-kicker">{{ __('certificates.public.eyebrow') }}</span>
                <h2 id="unavailable-title">{{ __('certificates.public.unavailable') }}</h2>
                <p>{{ __('certificates.public.unavailable_detail') }}</p>
                <a class="pc-home-link" href="{{ url('/') }}">{{ __('certificates.public.return_home') }}</a>
            </article>
        @endif
    </main>

    <footer class="pc-public-footer">
        <div>
            <strong>IUOAMC</strong>
            <span>iuoamc.pro</span>
        </div>
        <p>{{ __('certificates.public.footer') }}</p>
    </footer>
</body>
</html>
