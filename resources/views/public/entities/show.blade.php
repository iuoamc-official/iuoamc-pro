@extends('layouts.public')

@section('content')
    <section class="public-hero entity-profile-hero">
        <div class="hero-orbit" aria-hidden="true"></div>
        <div class="public-container entity-profile-grid">
            <div class="entity-profile-logo">
                <img src="{{ asset($entity['logo']) }}" alt="{{ $entity['legal_name'] }}">
            </div>
            <div class="hero-copy">
                <span class="public-eyebrow">{{ $page->localized('eyebrow') }}</span>
                <h1>{{ $page->localized('title') }}</h1>
                <p>{{ $page->localized('summary') }}</p>
                <div class="entity-profile-identity" dir="ltr">
                    <strong>{{ $entity['code'] }}@if($entity['registered_mark'] ?? false)<sup aria-label="Registered trademark">®</sup>@endif</strong>
                    <span>{{ $entity['legal_name'] }}</span>
                </div>
            </div>
        </div>
    </section>

    <section class="public-section public-container entity-profile-content">
        <div class="entity-profile-copy">
            <span class="public-eyebrow">{{ __('public_site.entity_profile') }}</span>
            <div class="content-prose">{!! nl2br(e($page->localized('body'))) !!}</div>
            <a class="quiet-action entity-back" href="{{ route('public.pages.show', ['locale' => app()->getLocale(), 'public_page' => 'entities']) }}">{{ __('public_site.all_entities') }}</a>
        </div>
        <aside class="entity-registry-panel" dir="ltr">
            <span>{{ __('public_site.official_registry') }}</span>
            @if($entity['registrations'] !== [])
                <dl class="entity-registrations">
                    @foreach($entity['registrations'] as $label => $number)
                        <div><dt>{{ $label }}</dt><dd>{{ $number }}</dd></div>
                    @endforeach
                </dl>
            @else
                <p>{{ __('public_site.registry_managed') }}</p>
            @endif
        </aside>
    </section>
@endsection
