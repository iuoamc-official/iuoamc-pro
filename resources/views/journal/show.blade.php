@extends('layouts.journal')
@php($translation = $article->translation())

@section('title', $translation?->title)
@section('description', $translation?->seo_description ?: \Illuminate\Support\Str::limit($translation?->abstract, 300))
@section('og_type', 'article')

@push('metadata')
    <meta name="citation_title" content="{{ $translation?->title }}">
    @foreach($article->authors as $author)<meta name="citation_author" content="{{ $author->latin_name ?: $author->name }}">@endforeach
    <meta name="citation_publication_date" content="{{ $article->published_at?->toDateString() }}">
    <meta name="citation_journal_title" content="{{ $journal->localized('name') }}">
    @if($article->doi)<meta name="citation_doi" content="{{ $article->doi }}">@endif
    @if($journal->issn)<meta name="citation_issn" content="{{ $journal->issn }}">@endif
    @php
        $articleSchema = [
            chr(64).'context' => 'https://schema.org',
            '@type' => $article->type === 'peer_reviewed_research' ? 'ScholarlyArticle' : 'Article',
            'headline' => $translation?->title,
            'datePublished' => $article->published_at?->toIso8601String(),
            'identifier' => $article->doi ?: $article->article_code,
            'author' => $article->authors->map(fn ($author) => ['@type' => 'Person', 'name' => $author->latin_name ?: $author->name, 'sameAs' => $author->orcid ? 'https://orcid.org/'.$author->orcid : null])->all(),
            'publisher' => ['@type' => 'Organization', 'name' => $journal->publisher_name],
        ];
    @endphp
    <script type="application/ld+json" nonce="{{ request()->attributes->get('csp_nonce') }}">{!! json_encode($articleSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush

@section('content')
    <article class="journal-article public-container">
        <nav class="journal-breadcrumb" aria-label="{{ __('journal.breadcrumb') }}">
            <a href="{{ route('journal.public.index', ['locale' => app()->getLocale()]) }}">{{ __('journal.home') }}</a><span>/</span><bdi dir="ltr">{{ $article->article_code }}</bdi>
        </nav>
        @if($article->status === 'retracted')
            <div class="journal-retraction" role="alert"><strong>{{ __('journal.retracted') }}</strong><p>{{ __('journal.retracted_notice') }}</p></div>
        @endif
        @if($article->correctionOf)
            <div class="journal-correction"><strong>{{ __('journal.correction') }}</strong><a href="{{ route('journal.public.articles.show', ['locale' => app()->getLocale(), 'article' => $article->correctionOf]) }}">{{ __('journal.original_record') }}</a></div>
        @endif
        <header class="journal-article-header">
            <span class="journal-type journal-type-{{ $article->type }}">{{ __('journal.types.'.$article->type) }}</span>
            <h1>{{ $translation?->title }}</h1>
            @if($translation?->subtitle)<p class="journal-subtitle">{{ $translation->subtitle }}</p>@endif
            <div class="journal-author-list">
                @foreach($article->authors as $author)
                    <div><strong>{{ $author->name }}</strong>@if($author->orcid)<a dir="ltr" rel="external noopener" href="https://orcid.org/{{ $author->orcid }}">ORCID {{ $author->orcid }}</a>@endif@if($author->pivot->affiliation_name)<span>{{ $author->pivot->affiliation_name }}</span>@endif</div>
                @endforeach
            </div>
        </header>

        <div class="journal-record-grid">
            <dl>
                <div><dt>{{ __('journal.article_code') }}</dt><dd><bdi dir="ltr">{{ $article->article_code }}</bdi></dd></div>
                <div><dt>{{ __('journal.received') }}</dt><dd>{{ $article->received_at?->format('Y-m-d') ?: '—' }}</dd></div>
                <div><dt>{{ __('journal.accepted') }}</dt><dd>{{ $article->accepted_at?->format('Y-m-d') ?: '—' }}</dd></div>
                <div><dt>{{ __('journal.published') }}</dt><dd>{{ $article->published_at?->format('Y-m-d') }}</dd></div>
                <div><dt>DOI</dt><dd>@if($article->doi)<a dir="ltr" href="https://doi.org/{{ $article->doi }}">{{ $article->doi }}</a>@else{{ __('journal.not_assigned') }}@endif</dd></div>
                <div><dt>{{ __('journal.integrity') }}</dt><dd><bdi dir="ltr">SHA-256 {{ \Illuminate\Support\Str::limit($article->version_of_record_hash, 18) }}</bdi></dd></div>
            </dl>
            <aside><span>{{ __('journal.version_of_record') }}</span><strong>V{{ $article->version_of_record }}</strong><p>{{ __('journal.immutable_notice') }}</p></aside>
        </div>

        <section class="journal-abstract"><h2>{{ __('journal.abstract') }}</h2><p>{!! nl2br(e($translation?->abstract)) !!}</p><div>@foreach($translation?->keywords ?? [] as $keyword)<span>{{ $keyword }}</span>@endforeach</div></section>
        <section class="journal-body">{!! nl2br(e($translation?->body)) !!}</section>

        @if(($translation?->references ?? []) !== [])
            <section class="journal-references"><h2>{{ __('journal.references') }}</h2><ol>@foreach($translation->references as $reference)<li>{{ $reference }}</li>@endforeach</ol></section>
        @endif

        <section class="journal-disclosures"><h2>{{ __('journal.disclosures') }}</h2><dl>@foreach(['conflicts', 'funding', 'ethics'] as $field)<div><dt>{{ __('journal.'.$field) }}</dt><dd>{{ $article->declarations[$field] ?? __('journal.not_declared') }}</dd></div>@endforeach</dl></section>

        @if($article->corrections->isNotEmpty())
            <section class="journal-related"><h2>{{ __('journal.corrections') }}</h2>@foreach($article->corrections as $correction)<a href="{{ route('journal.public.articles.show', ['locale' => app()->getLocale(), 'article' => $correction]) }}">{{ $correction->translation()?->title }}</a>@endforeach</section>
        @endif
    </article>
@endsection
