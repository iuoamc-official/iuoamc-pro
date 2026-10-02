
@extends('layouts.account')
@section('title', __('learning.title'))
@section('content')
<div class="learning">
<header class="learning-hero"><span class="learning-kicker">IUOAMC · {{ __('learning.track') }}</span><h1>{{ __('learning.title') }}</h1><p>{{ __('learning.introduction') }}</p><div class="learning-stats"><span>{{ __('learning.pages') }}</span><span>{{ __('learning.questions') }}</span><span>{{ __('learning.ten_lines') }}</span></div></header>
@if(!$enrollment)
<div class="learning-card"><h2>{{ __('learning.enrollment_required') }}</h2><p>{{ __('learning.enrollment_description') }}</p></div>
@else
@include('account.learning._nav')
<div class="learning-card"><div class="learning-row"><h2>{{ __('learning.progress') }}</h2><strong>{{ count($completed) }} / 30</strong></div><progress value="{{ count($completed) }}" max="30">{{ count($completed) }} / 30</progress><p class="learning-muted">{{ __('learning.progress_hint') }}</p>
@if($enrollment->reviewed_at)<div class="learning-feedback"><h2>{{ __('learning.final_score') }}: {{ $enrollment->final_score }} / 100</h2><strong>{{ $enrollment->final_score >= 75 ? __('learning.passed') : __('learning.needs_improvement') }}</strong><p class="learning-pre">{{ $enrollment->review_feedback }}</p></div>
@elseif($enrollment->exam_submitted_at)<p>{{ __('learning.pending_review') }}</p>
@endif
</div>
<div class="learning-grid" lang="ar" dir="rtl">
@foreach($course['sections'] as $section)<article class="learning-card"><small>{{ __('learning.lesson') }} {{ $section['id'] }}</small><h2><a href="{{ route('account.learning.lesson', ['locale'=>app()->getLocale(),'section'=>$section['id']]) }}">{{ $section['title'] }}</a></h2>
@if(in_array($section['id'],$completed,true))<span class="learning-check">✓ {{ __('learning.completed') }}</span>
@endif</article>
@endforeach</div>
<details class="learning-card"><summary>{{ __('learning.references') }}</summary><div lang="ar" dir="rtl">
@foreach($course['references'] as $reference)<p class="learning-source">{{ is_array($reference) ? implode(' — ', $reference) : $reference }}</p>
@endforeach</div></details>
@endif
</div>
@endsection
