@extends('layouts.public')

@section('content')
    <section class="public-hero {{ $page->slug === 'home' ? 'home-hero' : '' }}">
        <div class="hero-orbit" aria-hidden="true"></div>
        <div class="public-container hero-grid">
            <div class="hero-copy">
                <span class="public-eyebrow">{{ $page->localized('eyebrow') }}</span>
                <h1>{{ $page->localized('title') }}</h1>
                <p>{{ $page->localized('summary') }}</p>
                @if($page->slug === 'home')
                    <div class="hero-actions">
                        <a class="gold-action" href="{{ route('public.pages.show', ['locale' => app()->getLocale(), 'public_page' => 'about']) }}">{{ __('public_site.discover') }}</a>
                        <a class="quiet-action" href="{{ route('public.pages.show', ['locale' => app()->getLocale(), 'public_page' => 'programmes']) }}">{{ __('public_site.explore_programmes') }}</a>
                    </div>
                @endif
            </div>
            <aside class="trust-panel">
                <span>{{ __('public_site.trust_protocol') }}</span>
                <strong>{{ __('public_site.trust_title') }}</strong>
                <ul>
                    <li>{{ __('public_site.trust_items.audit') }}</li>
                    <li>{{ __('public_site.trust_items.verify') }}</li>
                    <li>{{ __('public_site.trust_items.privacy') }}</li>
                </ul>
                <div class="trust-status"><i></i>{{ __('public_site.system_operational') }}</div>
            </aside>
        </div>
    </section>

    @if($page->slug === 'home')
        <section class="proof-strip">
            <div class="public-container proof-grid">
                <div><b>QR + NFC</b><span>{{ __('public_site.proof.digital') }}</span></div>
                <div><b>SHA-256</b><span>{{ __('public_site.proof.integrity') }}</span></div>
                <div><b>AR · EN · FR</b><span>{{ __('public_site.proof.languages') }}</span></div>
                <div><b>24/7</b><span>{{ __('public_site.proof.verification') }}</span></div>
            </div>
        </section>

        <section class="public-section public-container">
            <div class="section-heading"><span>{{ __('public_site.capabilities_eyebrow') }}</span><h2>{{ __('public_site.capabilities_title') }}</h2><p>{{ $page->localized('body') }}</p></div>
            <div class="capability-grid">
                @foreach(__('public_site.capabilities') as $index => $capability)
                    <article><span>0{{ $index + 1 }}</span><h3>{{ $capability['title'] }}</h3><p>{{ $capability['body'] }}</p></article>
                @endforeach
            </div>
        </section>

        <section class="verify-stage">
            <div class="public-container verify-grid">
                <div><span class="public-eyebrow">{{ __('public_site.verify.eyebrow') }}</span><h2>{{ __('public_site.verify.title') }}</h2><p>{{ __('public_site.verify.body') }}</p></div>
                <form class="verify-card" data-verify-form data-base="{{ url('/verify/c') }}">
                    <label for="credential-token">{{ __('public_site.verify.label') }}</label>
                    <div><input id="credential-token" name="token" dir="ltr" autocomplete="off" placeholder="64-character verification token" required minlength="64" maxlength="64" pattern="[A-Fa-f0-9]{64}"><button type="submit">{{ __('public_site.verify.action') }}</button></div>
                    <small>{{ __('public_site.verify.help') }}</small>
                </form>
            </div>
        </section>

        <section class="public-section public-container entity-stage">
            <div class="section-heading"><span>{{ __('public_site.entities_eyebrow') }}</span><h2>{{ __('public_site.entities_title') }}</h2></div>
            <div class="entity-marquee" aria-label="{{ __('public_site.entities_title') }}">
                <div class="entity-track">
                @foreach(array_merge($siteProfile['entities'], $siteProfile['entities']) as $index => $entity)
                    <a class="entity-card entity-card-compact" href="{{ route('public.entities.show', ['locale' => app()->getLocale(), 'entity' => $entity['slug']]) }}" @if($index >= count($siteProfile['entities'])) aria-hidden="true" tabindex="-1" @endif>
                        <img src="{{ asset($entity['logo']) }}" alt="{{ $entity['legal_name'] }}" loading="lazy">
                        <strong>{{ $entity['code'] }}@if($entity['registered_mark'] ?? false)<sup aria-label="Registered trademark">®</sup>@endif</strong>
                        <span class="entity-legal-name" dir="ltr">{{ $entity['legal_name'] }}</span>
                        <span class="entity-card-action">{{ __('public_site.view_entity') }} <b aria-hidden="true">↗</b></span>
                    </a>
                @endforeach
                </div>
            </div>
        </section>
    @else
        <section class="public-section public-container content-stage">
            <div class="content-prose">{!! nl2br(e($page->localized('body'))) !!}</div>
            @if($page->template === 'entities')
                <div class="entity-logos page-entities">
                    @foreach($siteProfile['entities'] as $entity)
                        <a class="entity-card" href="{{ route('public.entities.show', ['locale' => app()->getLocale(), 'entity' => $entity['slug']]) }}">
                            <img src="{{ asset($entity['logo']) }}" alt="{{ $entity['legal_name'] }}">
                            <strong>{{ $entity['code'] }}@if($entity['registered_mark'] ?? false)<sup aria-label="Registered trademark">®</sup>@endif</strong>
                            <span class="entity-legal-name" dir="ltr">{{ $entity['legal_name'] }}</span>
                            @if($entity['registrations'] !== [])
                                <dl class="entity-registrations" dir="ltr">
                                    @foreach($entity['registrations'] as $label => $number)
                                        <div><dt>{{ $label }}</dt><dd>{{ $number }}</dd></div>
                                    @endforeach
                                </dl>
                            @endif
                            <span class="entity-card-action">{{ __('public_site.view_entity') }} <b aria-hidden="true">↗</b></span>
                        </a>
                    @endforeach
                </div>
            @endif
            @if($page->template === 'contact')
                <a class="contact-card" href="mailto:{{ $siteProfile['contact_email'] }}"><span>{{ __('public_site.official_email') }}</span><strong>{{ $siteProfile['contact_email'] }}</strong></a>
            @endif
        </section>
    @endif
@endsection
