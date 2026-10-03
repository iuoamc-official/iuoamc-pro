
@extends('layouts.account')
@section('title', __('learning.exam'))
@section('content')
<div class="learning">
@include('account.learning._nav')<header class="learning-hero"><h1>{{ __('learning.exam') }}</h1><p>{{ __('learning.exam_instructions') }}</p><div class="learning-stats"><span>{{ __('learning.exam_parts') }}</span></div></header>
@if($enrollment->exam_submitted_at)
<div class="learning-card"><h2>{{ __('learning.submitted') }}</h2><p>{{ __('learning.objective_score') }}: {{ $enrollment->objective_score }} / 48</p>@if($enrollment->reviewed_at)
<p>{{ __('learning.final_score') }}: {{ $enrollment->final_score }} / 100</p>
@else
<p>{{ __('learning.pending_review') }}</p>
@endif
<p>{{ __('learning.submitted_at') }}: <bdi>{{ $enrollment->exam_submitted_at }} UTC</bdi></p></div>
@elseif(!$enrollment->exam_started_at)
<div class="learning-notice">{{ __('learning.exam_start_hint') }}</div><form method="post" action="{{ route('account.learning.exam.start',['locale'=>app()->getLocale()]) }}">
@csrf<button type="submit">{{ __('learning.start_exam') }}</button></form>
@else
<div class="learning-notice" role="status">{{ __('learning.deadline') }}: <bdi>{{ $deadline->format('Y-m-d H:i:s') }} UTC</bdi>. {{ __('learning.save_hint') }}</div>
@if($deadline->isPast())<p class="learning-notice">{{ __('learning.time_expired') }}</p><form method="post" action="{{ route('account.learning.exam.save',['locale'=>app()->getLocale()]) }}">
@csrf<input type="hidden" name="answers[1]" value=""><button name="action" value="submit">{{ __('learning.submit_saved') }}</button></form>
@else
<div class="learning-exam-toolbar"><div class="learning-row"><span>{{ __('learning.answered_count') }}: <strong data-answered-count>0 / 40</strong></span><span>{{ __('learning.remaining_time') }}: <bdi class="learning-timer" data-deadline="{{ $deadline->utc()->toIso8601String() }}">—</bdi></span></div><nav class="learning-question-map" aria-label="{{ __('learning.question_map') }}">@foreach($course['questions'] as $item)<a data-question-link="{{ $item['id'] }}" href="#question-{{ $item['id'] }}">{{ $item['id'] }}</a>@endforeach</nav><p hidden data-time-expired role="status">{{ __('learning.time_expired') }}</p></div>
<form data-learning-exam data-confirm="{{ __('learning.confirm_submission') }}" method="post" action="{{ route('account.learning.exam.save',['locale'=>app()->getLocale()]) }}">
@csrf
@foreach($course['questions'] as $question)
@if($question['id']===1 || $question['id']===25)<h2 class="learning-question-part">{{ $question['id']===1 ? __('learning.objective_section') : __('learning.written_section') }}</h2>@endif
<fieldset id="question-{{ $question['id'] }}" data-question-id="{{ $question['id'] }}" class="learning-card" lang="ar" dir="rtl"><legend>{{ $question['id'] }} · {{ $question['points'] }} {{ __('learning.points') }}</legend><h2>{{ $question['prompt'] }}</h2>
@if($question['type']==='single_choice')
@foreach($question['options'] as $index=>$option)<label class="learning-option"><input type="radio" name="answers[{{ $question['id'] }}]" value="{{ $index }}"
@checked((string)old('answers.'.$question['id'],$answers[$question['id']]??'') === (string)$index)><span>{{ $option }}</span></label>
@endforeach
@else<label for="answer-{{ $question['id'] }}">{{ __('learning.your_answer') }}</label><textarea id="answer-{{ $question['id'] }}" name="answers[{{ $question['id'] }}]" maxlength="6000">{{ old('answers.'.$question['id'],$answers[$question['id']]??'') }}</textarea>
@endif
</fieldset>
@endforeach
<div class="learning-row learning-exam-actions"><button class="secondary" name="action" value="save">{{ __('learning.save_draft') }}</button><button name="action" value="submit">{{ __('learning.submit_exam') }}</button></div></form>
@endif
@endif</div>
<script src="{{ asset('assets/js/iuoamc-learning-1.1.0.js') }}" defer></script>
@endsection
