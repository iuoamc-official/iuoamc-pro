@extends('layouts.control')
@section('title', __('learning.project_review'))
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-learning-1.0.0.css') }}?v={{ filemtime(public_path('assets/css/iuoamc-learning-1.0.0.css')) }}">@endpush
@section('content')
<div class="learning learning-project"><header class="learning-hero"><h1>{{ __('learning.project_review') }} — {{ $student->name }}</h1><p>{{ __('learning.project_independent') }}</p><span>{{ __('learning.project_'.($project?->status ?? 'not_started')) }}</span></header><p><a class="learning-button secondary" href="{{ route('learning.control.show',['locale'=>app()->getLocale(),'enrollment'=>$record->id]) }}">{{ __('learning.previous') }}</a></p>
@if(!$project)<p class="learning-notice">{{ __('learning.project_not_started') }}</p>@else
@include('account.learning._project_rubric')
@if($project->feedback)<section class="learning-concept-feedback"><h2>{{ __('learning.project_feedback') }}</h2><p class="learning-pre">{{ $project->feedback }}</p></section>@endif
@if($project->status==='draft' || $project->status==='revision_requested')<p class="learning-notice">{{ __('learning.project_await_submission') }}</p>@else
@foreach(\App\Services\SensoryLearningProject::FIELDS as $key)<section class="learning-card"><h2>{{ __('learning.project_fields.'.$key) }}</h2><p class="learning-pre">{{ $fields[$key] ?? '' }}</p></section>@endforeach
@endif
@if($project->status==='submitted')<form method="post" action="{{ route('learning.control.project.review',['locale'=>app()->getLocale(),'enrollment'=>$record->id]) }}">@csrf<input type="hidden" name="revision" value="{{ old('revision',$project->revision) }}"><section class="learning-card"><h2>{{ __('learning.project_review') }}</h2><p>{{ __('learning.project_scoring_hint') }}</p>@error('revision')<p role="alert">{{ $message }}</p>@enderror
@foreach(__('learning.project_criteria') as $index=>$criterion)<label for="project-score-{{ $index }}">{{ $criterion }} / 4</label><input type="number" id="project-score-{{ $index }}" name="rubric_scores[{{ $index }}]" min="0" max="4" step="1" required value="{{ old('rubric_scores.'.$index) }}">@endforeach
@error('rubric_scores')<p class="learning-checkpoint-error" role="alert">{{ $message }}</p>@enderror
<label for="project-feedback">{{ __('learning.project_feedback') }}</label><textarea id="project-feedback" name="feedback" rows="6" minlength="30" maxlength="5000" required>{{ old('feedback') }}</textarea><p class="learning-row"><button name="action" value="revise" class="secondary">{{ __('learning.project_revise') }}</button><button name="action" value="approve">{{ __('learning.project_approve') }}</button></p></section></form>@endif
@endif
@include('account.learning._project_history')
</div>
@endsection
