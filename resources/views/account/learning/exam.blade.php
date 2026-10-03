@extends('layouts.account')
@section('title', __('learning.exam'))
@section('content')
<div class="learning">
@include('account.learning._nav')
@include('account.learning._checkpoint_feedback')
<header class="learning-hero"><h1>{{ __('learning.exam') }}</h1><p>{{ $isObjective ? __('learning.objective_instructions') : __('learning.exam_instructions') }}</p><div class="learning-stats"><span>{{ $isObjective ? __('learning.objective_parts') : __('learning.exam_parts') }}</span></div></header>
@if($enrollment->exam_submitted_at)
<div class="learning-card learning-feedback"><h2>{{ __('learning.submitted') }}</h2>
@if($isObjective)<p class="learning-exam-score">{{ __('learning.exam_result') }}: <strong>{{ number_format((float)$enrollment->objective_score,2) }} / 100</strong></p><p>{{ __('learning.correct_count') }}: {{ $enrollment->exam_correct_count }} / 30</p><strong>{{ $enrollment->objective_score >= 75 ? __('learning.exam_passed') : __('learning.exam_not_passed') }}</strong><p>{{ __('learning.objective_result_note') }}</p>
@else<p>{{ __('learning.objective_score') }}: {{ $enrollment->objective_score }} / 48</p>@endif
@if($enrollment->reviewed_at)<p>{{ __('learning.final_score') }}: {{ $enrollment->final_score }} / 100</p>
@else<p>{{ $isObjective ? __('learning.summary_pending') : __('learning.pending_review') }}</p>@endif
<p>{{ __('learning.submitted_at') }}: <bdi>{{ $enrollment->exam_submitted_at }} UTC</bdi></p><a class="learning-button" href="{{ route('account.learning.assignment',['locale'=>app()->getLocale()]) }}">{{ __('learning.assignment') }} ←</a></div>
@elseif(!$enrollment->exam_started_at)
<div class="learning-notice">{{ $isObjective ? __('learning.objective_start_hint') : __('learning.exam_start_hint') }}</div>
@if(!$ready)<p class="learning-notice">{{ __('learning.progress_hint') }}</p>@endif
<form method="post" action="{{ route('account.learning.exam.start',['locale'=>app()->getLocale()]) }}">@csrf<button type="submit" @disabled(!$ready)>{{ __('learning.start_exam') }}</button></form>
@else
<div class="learning-notice" role="status">{{ __('learning.deadline') }}: <bdi>{{ $deadline->format('Y-m-d H:i:s') }} UTC</bdi>. {{ $isObjective ? __('learning.objective_autosave_hint') : __('learning.save_hint') }}</div>
@if(!$isObjective && $deadline->isPast())<p class="learning-notice">{{ __('learning.time_expired') }}</p><form method="post" action="{{ route('account.learning.exam.save',['locale'=>app()->getLocale()]) }}">@csrf<input type="hidden" name="answers[1]" value=""><button name="action" value="submit">{{ __('learning.submit_saved') }}</button></form>
@else
<div class="learning-exam-toolbar"><div class="learning-row"><span>{{ __('learning.answered_count') }}: <strong data-answered-count>0 / {{ count($course['questions']) }}</strong></span><span>{{ __('learning.remaining_time') }}: <bdi class="learning-timer" @if($isObjective) data-remaining-ms="{{ max(0, $deadline->getTimestampMs() - now()->getTimestampMs()) }}" @else data-deadline="{{ $deadline->utc()->toIso8601String() }}" @endif>—</bdi></span></div>
<nav class="learning-question-map" aria-label="{{ __('learning.question_map') }}">@foreach($course['questions'] as $item)<a data-question-link="{{ $item['id'] }}" href="#question-{{ $item['id'] }}">{{ $loop->iteration }}</a>@endforeach</nav>
@if($isObjective)<p data-autosave-status role="status" data-saving="{{ __('learning.autosave_saving') }}" data-saved="{{ __('learning.autosave_saved') }}" data-failed="{{ __('learning.autosave_failed') }}" data-expired="{{ __('learning.objective_expired') }}" data-conflict="{{ __('learning.stale_draft') }}">{{ __('learning.objective_autosave_hint') }}</p>@else<p hidden data-time-expired role="status">{{ __('learning.time_expired') }}</p>@endif</div>
<form data-learning-exam data-question-count="{{ count($course['questions']) }}" @if($isObjective) data-objective-exam data-page-size="5" data-result-url="{{ route('account.learning.exam',['locale'=>app()->getLocale()]) }}" @endif data-confirm="{{ __('learning.confirm_submission') }}" method="post" action="{{ route('account.learning.exam.save',['locale'=>app()->getLocale()]) }}">@csrf
@if($isObjective)<input type="hidden" name="draft_revision" value="{{ $enrollment->exam_draft_revision }}"><input type="hidden" name="answers[1]" value=""><p class="learning-muted">{{ __('learning.objective_scoring') }}</p>@endif
@if($isObjective)<nav class="learning-exam-pages learning-row" data-exam-pagination hidden aria-label="{{ __('learning.exam_pages') }}"><button type="button" class="secondary" data-exam-previous>{{ __('learning.previous_page') }}</button><strong data-exam-page-status data-label="{{ __('learning.exam_page_status') }}" role="status"></strong><button type="button" class="secondary" data-exam-next>{{ __('learning.next_page') }}</button></nav>@endif
@foreach($course['questions'] as $question)
@if(!$isObjective && ($question['id']===1 || $question['id']===25))<h2 class="learning-question-part">{{ $question['id']===1 ? __('learning.objective_section') : __('learning.written_section') }}</h2>@endif
<fieldset id="question-{{ $question['id'] }}" data-question-id="{{ $question['id'] }}" @if($isObjective) tabindex="-1" @endif class="learning-card" lang="ar" dir="rtl"><legend>{{ $loop->iteration }} · {{ $isObjective ? __('learning.choose_one') : $question['points'].' '.__('learning.points') }}</legend><h2>{{ $question['prompt'] }}</h2>
@if($question['type']==='single_choice')
@foreach($question['options'] as $index=>$option)<label class="learning-option"><input type="radio" name="answers[{{ $question['id'] }}]" value="{{ $index }}" @checked((string)old('answers.'.$question['id'],$answers[$question['id']]??'') === (string)$index)><span>{{ $option }}</span></label>@endforeach
@else<label for="answer-{{ $question['id'] }}">{{ __('learning.your_answer') }}</label><textarea id="answer-{{ $question['id'] }}" name="answers[{{ $question['id'] }}]" maxlength="6000">{{ old('answers.'.$question['id'],$answers[$question['id']]??'') }}</textarea>@endif
</fieldset>@endforeach
<div class="learning-row learning-exam-actions"><button class="secondary" name="action" value="save">{{ __('learning.save_draft') }}</button><button name="action" value="submit" @if($isObjective) data-exam-final-submit @endif>{{ __('learning.submit_exam') }}</button></div></form>
@endif
@endif</div>
<script src="{{ asset($isObjective ? 'assets/js/iuoamc-objective-exam-1.0.0.js' : 'assets/js/iuoamc-learning-1.1.0.js') }}?v={{ filemtime(public_path($isObjective ? 'assets/js/iuoamc-objective-exam-1.0.0.js' : 'assets/js/iuoamc-learning-1.1.0.js')) }}" defer></script>
@endsection
