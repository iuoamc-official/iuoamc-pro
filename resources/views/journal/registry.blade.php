@extends('layouts.journal')
@php($translation = $article->translation())

@section('title', __('journal.wicp_registry_title'))
@section('description', __('journal.wicp_registry_description'))

@section('content')
<main class="public-container journal-registry-record">
    <nav class="journal-breadcrumb" aria-label="{{ __('journal.breadcrumb') }}">
        <a href="{{ route('journal.public.index', ['locale' => app()->getLocale()]) }}">{{ __('journal.home') }}</a><span>/</span>{{ __('journal.wicp_registry') }}
    </nav>

    <header>
        <span class="journal-registry-seal">{{ __('journal.verified_record') }}</span>
        <h1>{{ __('journal.wicp_registry_title') }}</h1>
        <p>{{ __('journal.wicp_registry_notice') }}</p>
    </header>

    <dl class="journal-registry-facts">
        <div><dt>{{ __('journal.wicp_number') }}</dt><dd><bdi dir="ltr">{{ $article->wicp_registration_number }}</bdi></dd></div>
        <div><dt>{{ __('journal.article') }}</dt><dd><a href="{{ route('journal.public.articles.show', ['locale' => app()->getLocale(), 'article' => $article->slug]) }}">{{ $translation?->title }}</a></dd></div>
        <div><dt>{{ __('journal.author') }}</dt><dd>{{ $article->authors->pluck('name')->join(', ') }}</dd></div>
        <div><dt>{{ __('journal.wicp_registered_at') }}</dt><dd>{{ $article->wicp_registered_at?->format('Y-m-d') }}</dd></div>
        <div><dt>{{ __('journal.wicp_verified_at') }}</dt><dd>{{ $article->wicp_verified_at?->format('Y-m-d H:i') }} UTC</dd></div>
        <div><dt>{{ __('journal.article_code') }}</dt><dd><bdi dir="ltr">{{ $article->article_code }}</bdi></dd></div>
        <div><dt>{{ __('journal.version_of_record') }}</dt><dd>V{{ $article->version_of_record }}</dd></div>
        <div><dt>{{ __('journal.pdf_fingerprint') }}</dt><dd><bdi dir="ltr">{{ $article->pdf_sha256 }}</bdi></dd></div>
        <div><dt>{{ __('journal.integrity') }}</dt><dd><bdi dir="ltr">{{ $article->version_of_record_hash }}</bdi></dd></div>
    </dl>

    @if($article->wicp_verification_url)
        <a class="journal-external-verification" rel="external noopener" target="_blank" href="{{ $article->wicp_verification_url }}">{{ __('journal.verify_with_wicp') }} ↗</a>
    @endif
    <p class="journal-registry-disclaimer">{{ __('journal.wicp_issuer_disclaimer') }}</p>
</main>
@endsection
