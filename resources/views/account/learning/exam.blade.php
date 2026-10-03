
@extends('layouts.account')
@section('title', __('learning.exam'))
@section('content')
<div class="learning">
@include('account.learning._nav')<header class="learning-hero"><h1>{{ __('learning.exam') }}</h1><p>{{ __('learning.exam_instructions') }}</p></header>
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
<form method="post" action="{{ route('account.learning.exam.save',['locale'=>app()->getLocale()]) }}">
@csrf
@foreach($course['questions'] as $question)<fieldset class="learning-card" lang="ar" dir="rtl"><legend>{{ $question['id'] }} · {{ $question['points'] }} {{ __('learning.points') }}</legend><h2>{{ $question['prompt'] }}</h2>
@if($question['type']==='single_choice')
@foreach($question['options'] as $index=>$option)<label class="learning-option"><input type="radio" name="answers[{{ $question['id'] }}]" value="{{ $index }}"
@checked((string)old('answers.'.$question['id'],$answers[$question['id']]??'') === (string)$index)><span>{{ $option }}</span></label>
@endforeach
@else<label for="answer-{{ $question['id'] }}">{{ __('learning.your_answer') }}</label><textarea id="answer-{{ $question['id'] }}" name="answers[{{ $question['id'] }}]" maxlength="6000">{{ old('answers.'.$question['id'],$answers[$question['id']]??'') }}</textarea>
@endif
</fieldset>
@endforeach
<div class="learning-row"><button class="secondary" name="action" value="save">{{ __('learning.save_draft') }}</button><button name="action" value="submit">{{ __('learning.submit_exam') }}</button></div></form>
@endif
@endif</div>
@endsection
