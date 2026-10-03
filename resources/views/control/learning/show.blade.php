
@extends('layouts.control')
@section('title', __('learning.review'))
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-learning-1.0.0.css') }}?v={{ filemtime(public_path('assets/css/iuoamc-learning-1.0.0.css')) }}">
@endpush
@section('content')
<div class="learning"><header class="learning-hero"><h1>{{ __('learning.review') }} — {{ $student->name }}</h1><p>{{ $isObjective ? __('learning.objective_grading_policy') : __('learning.grading_policy') }}</p></header>
@if($isObjective && $record->exam_submitted_at)<div class="learning-feedback"><strong>{{ __('learning.exam_result') }}: {{ $record->objective_score }} / 100 · {{ $record->exam_correct_count }} / 30</strong></div>@endif
@if(!$record->exam_submitted_at || !$record->summary_submitted_at)<div class="learning-notice">{{ __('learning.wait_for_submissions') }}</div>
@endif
<form method="post" action="{{ route('learning.control.review',['locale'=>app()->getLocale(),'enrollment'=>$record->id]) }}">
@csrf
@foreach($assessment['questions'] as $question)<article class="learning-card" lang="ar" dir="rtl"><h2>{{ $question['id'] }}. {{ $question['prompt'] }}</h2><p class="learning-pre">{{ $question['type']==='single_choice' ? ($question['options'][$answers[$question['id']]??-1]??__('learning.no_answer')) : ($answers[$question['id']]??__('learning.no_answer')) }}</p>
@if($question['type']==='single_choice')<p><strong>{{ __('learning.correct_answer') }}:</strong> {{ $question['options'][$question['correct_index']] }}</p><p>{{ $question['rationale'] }}</p>
@else<ul>
@foreach($question['rubric'] as $criterion)<li>{{ $criterion }}</li>
@endforeach</ul><label for="score-{{ $question['id'] }}">{{ __('learning.score') }} / {{ $question['points'] }}</label><input id="score-{{ $question['id'] }}" name="question_scores[{{ $question['id'] }}]" type="number" min="0" max="{{ $question['points'] }}" step="0.001" required value="{{ old('question_scores.'.$question['id'],$questionScores[$question['id']]??'') }}">
@endif</article>
@endforeach
<article class="learning-card"><h2>{{ __('learning.assignment') }}</h2><ol lang="ar" dir="rtl">
@foreach($lines as $line)<li><p>{{ $line }}</p></li>
@endforeach</ol>
@foreach($summaryCaps as $index=>$cap)<label for="summary-score-{{ $index }}">{{ __('learning.summary_criteria.'.$index) }} / {{ $cap }}</label><input id="summary-score-{{ $index }}" type="number" name="summary_scores[{{ $index }}]" min="0" max="{{ $cap }}" step="0.001" required value="{{ old('summary_scores.'.$index,$summaryScores[$index]??'') }}">
@endforeach</article>
<article class="learning-card"><label for="feedback">{{ __('learning.feedback') }}</label><textarea id="feedback" name="feedback" maxlength="6000" required>{{ old('feedback',$record->review_feedback) }}</textarea><p><button
@disabled(!$record->exam_submitted_at || !$record->summary_submitted_at)>{{ __('learning.save_review') }}</button></p></article>
</form></div>
@endsection
