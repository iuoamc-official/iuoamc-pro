@extends('layouts.public')

@section('content')
    <section class="public-hero leadership-hero">
        <div class="hero-orbit" aria-hidden="true"></div>
        <div class="public-container leadership-hero-grid">
            <figure class="leadership-portrait">
                <div class="leadership-portrait-frame">
                    <img
                        src="{{ asset('assets/brand/leadership/ahmad-maadarani-president-general-v1.webp') }}"
                        alt="{{ __('public_site.leadership.portrait_alt') }}"
                        width="660"
                        height="880"
                        fetchpriority="high"
                    >
                </div>
                <figcaption>
                    <strong>Master Chef Ahmad Maadarani</strong>
                    <span>{{ __('public_site.leadership.role_value') }}</span>
                </figcaption>
            </figure>
            <div class="hero-copy">
                <span class="public-eyebrow">{{ $page->localized('eyebrow') }}</span>
                <h1>{{ $page->localized('title') }}</h1>
                <p>{{ $page->localized('summary') }}</p>
                <div class="leadership-office">
                    <span>{{ __('public_site.leadership.office') }}</span>
                    <strong>IUOAMC</strong>
                </div>
            </div>
        </div>
    </section>

    <section class="public-section public-container leadership-content">
        <article>
            <span class="public-eyebrow">{{ __('public_site.leadership.profile') }}</span>
            <div class="content-prose">{!! nl2br(e($page->localized('body'))) !!}</div>
        </article>
        <aside class="leadership-facts">
            <span>{{ __('public_site.leadership.credentials') }}</span>
            <dl>
                <div><dt>{{ __('public_site.leadership.role') }}</dt><dd>{{ __('public_site.leadership.role_value') }}</dd></div>
                <div><dt>{{ __('public_site.leadership.education') }}</dt><dd>2002 · 2005</dd></div>
                <div><dt>{{ __('public_site.leadership.master_chef') }}</dt><dd>2015</dd></div>
                <div><dt>{{ __('public_site.leadership.judge') }}</dt><dd>2017</dd></div>
                <div><dt>{{ __('public_site.leadership.digital_verification') }}</dt><dd>2019</dd></div>
            </dl>
            <a href="mailto:{{ $siteProfile['contact_email'] }}">{{ $siteProfile['contact_email'] }}</a>
        </aside>
    </section>
@endsection
