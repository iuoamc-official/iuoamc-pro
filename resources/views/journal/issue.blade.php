@extends('layouts.journal')
@section('title', $issue->localized('title'))
@section('content')
<section class="journal-page-head"><div class="public-container"><span>{{ __('journal.volume') }} {{ $issue->volume }} · {{ __('journal.issue') }} {{ $issue->number }}</span><h1>{{ $issue->localized('title') }}</h1><p>{{ $issue->localized('description') }}</p></div></section>
<section class="public-container journal-issue-contents"><h2>{{ __('journal.table_of_contents') }}</h2>
    @forelse($issue->articles as $article)@php($translation=$article->translation())<article><span class="journal-type journal-type-{{ $article->type }}">{{ __('journal.types.'.$article->type) }}</span><div><h3><a href="{{ route('journal.public.articles.show',['locale'=>app()->getLocale(),'article'=>$article->slug]) }}">{{ $translation?->title }}</a></h3><p>{{ $article->authors->pluck('name')->join(' · ') }}</p></div><bdi dir="ltr">{{ $article->page_start ? $article->page_start.'–'.$article->page_end : $article->article_code }}</bdi></article>@empty<div class="journal-empty">{{ __('journal.no_publications') }}</div>@endforelse
</section>
@endsection
