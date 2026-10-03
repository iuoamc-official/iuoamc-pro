@extends('layouts.account')
@section('title', __('learning.title'))
@section('content')
<div class="learning">
<header class="learning-hero"><span class="learning-kicker">IUOAMC · {{ __('learning.track') }}</span><h1>{{ __('learning.title') }}</h1><p>{{ __('learning.introduction') }}</p><div class="learning-stats"><span>{{ __('learning.pages') }}</span><span>{{ __('learning.questions') }}</span><span>{{ __('learning.ten_lines') }}</span></div></header>
@if(!$enrollment)
<div class="learning-card"><h2>{{ __('learning.enrollment_required') }}</h2><p>{{ __('learning.enrollment_description') }}</p><a class="learning-button" href="{{ route('learning.access.index',['locale'=>app()->getLocale()]) }}">{{ __('learning.activate_title') }}</a></div>
@else
@include('account.learning._nav')
<div class="learning-card"><div class="learning-row"><h2>{{ __('learning.progress') }}</h2><strong>{{ count($completed) }} / 30</strong></div><progress aria-label="{{ __('learning.progress') }}" value="{{ count($completed) }}" max="30">{{ count($completed) }} / 30</progress><p class="learning-muted">{{ __('learning.progress_hint') }}</p>
@php($nextSection = collect($course['sections'])->first(fn($section)=>!in_array($section['id'],$completed,true)))
@if($nextSection)<a class="learning-button" href="{{ route('account.learning.lesson',['locale'=>app()->getLocale(),'section'=>$nextSection['id']]) }}">{{ __('learning.continue_from') }} {{ $nextSection['id'] }} ←</a>@endif
@if($enrollment->reviewed_at)<div class="learning-feedback"><h2>{{ __('learning.final_score') }}: {{ $enrollment->final_score }} / 100</h2><strong>{{ $enrollment->final_score >= 75 ? __('learning.passed') : __('learning.needs_improvement') }}</strong><p class="learning-pre">{{ $enrollment->review_feedback }}</p></div>
@elseif($enrollment->exam_submitted_at)<p>{{ ($enrollment->exam_format===\App\Services\SensoryObjectiveExam::FORMAT ? __('learning.summary_pending') : __('learning.pending_review')) }}</p>@endif
</div>
<div lang="ar" dir="rtl">
@foreach(array_chunk($course['sections'],5) as $phase=>$sections)
<section class="learning-phase" aria-labelledby="phase-{{ $phase }}"><header class="learning-phase-header"><span class="learning-phase-number">{{ $phase+1 }}</span><div><small>{{ __('learning.phase') }} {{ $phase+1 }} / 6</small><h2 id="phase-{{ $phase }}">{{ __('learning.phase_names')[$phase] }}</h2></div></header>
<div class="learning-grid">@foreach($sections as $section)<article class="learning-card learning-lesson-card"><small>{{ __('learning.lesson') }} {{ $section['id'] }}</small><h3><a href="{{ route('account.learning.lesson', ['locale'=>app()->getLocale(),'section'=>$section['id']]) }}">{{ $section['title'] }}</a></h3><p>{{ $section['support']['goal'] }}</p>
@if(in_array($section['id'],$completed,true))<span class="learning-check">✓ {{ __('learning.completed') }}</span>@endif
</article>@endforeach</div></section>@endforeach
</div>
<details class="learning-card" id="references"><summary>{{ __('learning.references') }}</summary><ol lang="en" dir="ltr">
@foreach($course['references'] as $reference)<li class="learning-source"><p>{{ $reference[1] }}<br><a href="{{ $reference[2] }}" target="_blank" rel="noopener">{{ $reference[2] }}</a></p><p lang="ar" dir="rtl">{{ $reference[3] }}</p></li>@endforeach
</ol></details>
@endif</div>
@endsection
