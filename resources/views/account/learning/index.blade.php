@extends('layouts.account')
@section('title', __('learning.title'))
@section('content')
<div class="learning">
<header class="learning-hero"><span class="learning-kicker">IUOAMC · {{ __('learning.track') }}</span><h1>{{ __('learning.title') }}</h1><p>{{ __('learning.introduction') }}</p><div class="learning-stats"><span>{{ __('learning.pages') }}</span><span>{{ __('learning.questions') }}</span><span>{{ __('learning.ten_lines') }}</span></div></header>
@if(!$enrollment)
<div class="learning-card"><h2>{{ __('learning.enrollment_required') }}</h2><p>{{ __('learning.enrollment_description') }}</p><a class="learning-button" href="{{ route('learning.access.index',['locale'=>app()->getLocale()]) }}">{{ __('learning.activate_title') }}</a></div>
@else
@include('account.learning._nav')
<section class="learning-pathway"><header class="learning-row"><div><h2>{{ __('learning.dashboard_title') }}</h2><p>{{ __('learning.dashboard_hint') }}</p></div><span class="learning-status-badge">{{ __('learning.'.$learningStatus) }}</span></header>
<div class="learning-pathway-grid">
<article><small>01</small><h3>{{ __('learning.step_lessons') }}</h3><strong>{{ count($completed) }} / 30</strong><progress value="{{ count($completed) }}" max="30" aria-label="{{ __('learning.progress') }}"></progress></article>
<article><small>02</small><h3>{{ __('learning.step_exam') }}</h3>@if($enrollment->exam_submitted_at)<strong>{{ $enrollment->objective_score }} / {{ $enrollment->exam_format===\App\Services\SensoryObjectiveExam::FORMAT ? 100 : 48 }}</strong>@else<strong>{{ $enrollment->exam_started_at ? __('learning.exam_in_progress') : __('learning.not_started') }}</strong>@endif</article>
<article><small>03</small><h3>{{ __('learning.step_assignment') }}</h3><strong>{{ $enrollment->summary_submitted_at ? __('learning.submission_received') : __('learning.not_started') }}</strong></article>
<article><small>04</small><h3>{{ __('learning.step_review') }}</h3><strong>{{ $enrollment->reviewed_at ? number_format((float)$enrollment->final_score,2).' / 100' : __('learning.pending_review') }}</strong></article>
</div>
@if($canReport)<div class="learning-row"><strong>{{ __('learning.report_available') }}</strong><a class="learning-button" href="{{ route('account.learning.report',['locale'=>app()->getLocale()]) }}">{{ __('learning.report_title') }}</a></div>@else<p class="learning-muted">{{ __('learning.report_hint') }}</p>@endif
</section>
<section class="learning-project-strip"><div class="learning-row"><div><h2>{{ __('learning.project_title') }}</h2><span class="learning-status-badge">{{ __('learning.project_'.($project?->status ?? 'not_started')) }}</span><p>{{ __('learning.project_independent') }}</p></div><a class="learning-button" href="{{ route('account.learning.project',['locale'=>app()->getLocale()]) }}">{{ __('learning.project_title') }} ←</a></div></section>
<div class="learning-card"><div class="learning-row"><h2>{{ __('learning.progress') }}</h2><strong>{{ count($completed) }} / 30</strong></div><progress aria-label="{{ __('learning.progress') }}" value="{{ count($completed) }}" max="30">{{ count($completed) }} / 30</progress><p class="learning-muted">{{ __('learning.lesson_locked') }}</p>
@php($nextSection = collect($course['sections'])->first(fn($section)=>!in_array($section['id'],$completed,true)))
@if($nextSection)<a class="learning-button" href="{{ route('account.learning.lesson',['locale'=>app()->getLocale(),'section'=>$nextSection['id']]) }}">{{ __('learning.continue_from') }} {{ $nextSection['id'] }} ←</a>@else<p>{{ __('learning.all_lessons_completed') }}</p><a class="learning-button" href="{{ route('account.learning.exam',['locale'=>app()->getLocale()]) }}">{{ __('learning.exam') }} ←</a>@endif
@if($enrollment->reviewed_at)<div class="learning-feedback"><h2>{{ __('learning.final_score') }}: {{ $enrollment->final_score }} / 100</h2><strong>{{ $enrollment->final_score >= 75 ? __('learning.passed') : __('learning.needs_improvement') }}</strong><p class="learning-pre">{{ $enrollment->review_feedback }}</p></div>
@elseif($enrollment->exam_submitted_at)<p>{{ ($enrollment->exam_format===\App\Services\SensoryObjectiveExam::FORMAT ? __('learning.summary_pending') : __('learning.pending_review')) }}</p>@endif
</div>
<div lang="ar" dir="rtl">
@foreach(array_chunk($course['sections'],5) as $phase=>$sections)
<section class="learning-phase" aria-labelledby="phase-{{ $phase }}"><header class="learning-phase-header"><span class="learning-phase-number">{{ $phase+1 }}</span><div><small>{{ __('learning.phase') }} {{ $phase+1 }} / 6</small><h2 id="phase-{{ $phase }}">{{ __('learning.phase_names')[$phase] }}</h2></div></header>
<div class="learning-grid">@foreach($sections as $section)<article class="learning-card learning-lesson-card {{ in_array($section['id'],$available,true) ? 'learning-lesson-available' : 'learning-lesson-locked' }}"><small>{{ __('learning.lesson') }} {{ $section['id'] }}</small><h3>@if(in_array($section['id'],$available,true))<a href="{{ route('account.learning.lesson', ['locale'=>app()->getLocale(),'section'=>$section['id']]) }}">{{ $section['title'] }}</a>@else{{ $section['title'] }}@endif</h3><p>{{ $section['support']['goal'] }}</p>
@if(in_array($section['id'],$completed,true))<span class="learning-status-badge learning-check">✓ {{ __('learning.completed') }}</span>@elseif(in_array($section['id'],$available,true))<span class="learning-status-badge learning-status-ready">{{ __('learning.available_now') }}</span>@else<span class="learning-status-badge learning-status-locked">{{ __('learning.locked') }}</span>@endif
</article>@endforeach</div></section>@endforeach
</div>
<details class="learning-card" id="references"><summary>{{ __('learning.references') }}</summary><ol lang="en" dir="ltr">
@foreach($course['references'] as $reference)<li class="learning-source"><p>{{ $reference[1] }}<br><a href="{{ $reference[2] }}" target="_blank" rel="noopener">{{ $reference[2] }}</a></p><p lang="ar" dir="rtl">{{ $reference[3] }}</p></li>@endforeach
</ol></details>
@endif</div>
@endsection
