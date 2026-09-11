@extends('layouts.journal')
@section('title', __('journal.author_guidelines'))
@section('description', __('journal.author_guidelines_intro'))
@section('content')
<section class="journal-page-head"><div class="public-container"><span>MCIJ · AUTHORS</span><h1>{{ __('journal.author_guidelines') }}</h1><p>{{ __('journal.author_guidelines_intro') }}</p></div></section>
<div class="public-container journal-guidance">
    <aside class="journal-guidance-summary"><strong>{{ __('journal.before_submission') }}</strong><p>{{ __('journal.before_submission_text') }}</p><a class="journal-primary-link" href="{{ route('journal.public.submissions.create',['locale'=>app()->getLocale()]) }}">{{ __('journal.start_submission') }}</a><a href="{{ route('journal.public.submissions.tracking',['locale'=>app()->getLocale()]) }}">{{ __('journal.track_submission') }}</a></aside>
    <section class="journal-guidance-list">
        @foreach(__('journal.guidelines') as $number => $guideline)
            <article><span>{{ str_pad((string)($number + 1), 2, '0', STR_PAD_LEFT) }}</span><div><h2>{{ $guideline['title'] }}</h2><p>{{ $guideline['body'] }}</p></div></article>
        @endforeach
    </section>
</div>
@endsection
