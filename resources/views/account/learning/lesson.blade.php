@extends('layouts.account')
@section('title', $lesson['title'])
@section('content')
<div class="learning">
@include('account.learning._nav')
<div class="learning-reading-layout">
<aside class="learning-lesson-sidebar"><details open><summary>{{ __('learning.lesson_outline') }}</summary><nav aria-label="{{ __('learning.lesson_outline') }}" lang="ar" dir="rtl">@foreach($course['sections'] as $item)<a href="{{ route('account.learning.lesson',['locale'=>app()->getLocale(),'section'=>$item['id']]) }}" @if($item['id']===$lesson['id']) aria-current="page" @endif><span>{{ $item['id'] }}</span>{{ $item['title'] }}</a>@endforeach</nav></details></aside>
<article class="learning-card learning-reading" lang="ar" dir="rtl"><header><span class="learning-kicker">صفحة {{ $lesson['id'] }} من 30</span><h1>{{ $lesson['title'] }}</h1>
@php($readingWords = count(preg_split('/\s+/u', implode(' ', $lesson['paragraphs']).' '.$lesson['support']['explanation'].' '.$lesson['support']['example'], -1, PREG_SPLIT_NO_EMPTY)))
<p class="learning-muted">{{ max(3, (int)ceil($readingWords / 150)) }} {{ __('learning.read_minutes') }}</p></header>
<section class="learning-goal"><h2>{{ __('learning.learning_goal') }}</h2><p>{{ $lesson['support']['goal'] }}</p></section>
<figure class="learning-figure"><img src="{{ asset('assets/images/learning/'.$lesson['support']['figure'].'.svg') }}" alt="{{ $lesson['support']['caption'] }}" width="900" height="460" decoding="async"><figcaption>{{ $lesson['support']['caption'] }}<small>{{ __('learning.figure_note') }}</small></figcaption></figure>
<nav class="learning-content-links" aria-label="{{ __('learning.contents') }}"><a href="#core-lesson">{{ __('learning.core_explanation') }}</a><a href="#further-explanation">{{ __('learning.deeper_explanation') }}</a><a href="#worked-example">{{ __('learning.worked_example') }}</a><a href="#practice">{{ __('learning.practice_task') }}</a></nav>
<section id="core-lesson">
@foreach($lesson['paragraphs'] as $paragraph)
@if(in_array($paragraph,['تحليل متقدم','تطبيق مهني','سؤال للتفكير','مخرج الصفحة'],true))<h2>{{ $paragraph }}</h2>@else<p>{{ $paragraph }}</p>@endif
@endforeach</section>
<section id="further-explanation"><h2>{{ __('learning.deeper_explanation') }}</h2><p>{{ $lesson['support']['explanation'] }}</p></section>
<section class="learning-example" id="worked-example"><h2>{{ __('learning.worked_example') }}</h2><p>{{ $lesson['support']['example'] }}</p></section>
<section class="learning-practice" id="practice"><h2>{{ __('learning.practice_task') }}</h2><p>{{ $lesson['support']['practice'] }}</p></section>
<section class="learning-takeaway"><h2>{{ __('learning.key_takeaway') }}</h2><p>{{ $lesson['support']['takeaway'] }}</p></section>
<p class="learning-muted">{{ __('learning.references') }}: @foreach($lesson['support']['references'] as $reference)<a href="{{ route('account.learning.index',['locale'=>app()->getLocale()]) }}#references">[{{ $reference }}]</a> @endforeach</p>
<form method="post" action="{{ route('account.learning.complete',['locale'=>app()->getLocale(),'section'=>$lesson['id']]) }}">@csrf<button type="submit">{{ __('learning.mark_complete') }}</button></form>
<div class="learning-nav">@if($lesson['id']>1)<a href="{{ route('account.learning.lesson',['locale'=>app()->getLocale(),'section'=>$lesson['id']-1]) }}">{{ __('learning.previous') }}</a>@endif
@if($lesson['id']<30)<a href="{{ route('account.learning.lesson',['locale'=>app()->getLocale(),'section'=>$lesson['id']+1]) }}">{{ __('learning.next') }}</a>@endif</div>
</article></div></div>
@endsection
