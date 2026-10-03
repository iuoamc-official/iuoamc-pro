
@extends('layouts.account')
@section('title', $lesson['title'])
@section('content')
<div class="learning">
@include('account.learning._nav')
<article class="learning-card learning-reading" lang="ar" dir="rtl"><span class="learning-kicker">صفحة {{ $lesson['id'] }} من 30</span><h1>{{ $lesson['title'] }}</h1>
@foreach($lesson['paragraphs'] as $paragraph)
@if(in_array($paragraph,['تحليل متقدم','تطبيق مهني','سؤال للتفكير','مخرج الصفحة'],true))<h2>{{ $paragraph }}</h2>
@else<p>{{ $paragraph }}</p>
@endif
@endforeach
<form method="post" action="{{ route('account.learning.complete',['locale'=>app()->getLocale(),'section'=>$lesson['id']]) }}">
@csrf<button type="submit">{{ __('learning.mark_complete') }}</button></form></article>
<div class="learning-nav">
@if($lesson['id']>1)<a href="{{ route('account.learning.lesson',['locale'=>app()->getLocale(),'section'=>$lesson['id']-1]) }}">{{ __('learning.previous') }}</a>
@endif
@if($lesson['id']<30)<a href="{{ route('account.learning.lesson',['locale'=>app()->getLocale(),'section'=>$lesson['id']+1]) }}">{{ __('learning.next') }}</a>
@endif</div>
</div>
@endsection
