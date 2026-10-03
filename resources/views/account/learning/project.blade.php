@extends('layouts.account')
@section('title', __('learning.project_title'))
@section('content')
<div class="learning learning-project">@include('account.learning._nav')<header class="learning-hero"><span class="learning-kicker">IUOAMC · APPLIED SENSORY PROTOCOL</span><h1>{{ __('learning.project_title') }}</h1><p>{{ __('learning.project_intro') }}</p><div class="learning-stats"><span>{{ __('learning.project_'.($project?->status ?? 'not_started')) }}</span><span>{{ __('learning.project_version') }} {{ $project?->submission_number ?? 0 }}</span></div></header><p class="learning-notice">{{ __('learning.project_independent') }}</p>
@if(!$ready)<section class="learning-card"><h2>{{ __('learning.project_requires_material') }}</h2><a class="learning-button" href="{{ route('account.learning.index',['locale'=>app()->getLocale()]) }}">{{ __('learning.lessons') }}</a></section>
@else
<section class="learning-card"><p>{{ __('learning.project_examples') }}</p><p class="learning-muted">{{ __('learning.project_field_hint') }}</p></section>
@include('account.learning._project_rubric')
@if($project?->feedback)<section class="learning-concept-feedback"><h2>{{ __('learning.project_feedback') }}</h2><p class="learning-pre">{{ $project->feedback }}</p><strong>{{ __('learning.project_score') }}: {{ $project->score }} / 20</strong></section>@endif
@if(!$project || in_array($project->status,['draft','revision_requested'],true))
<form data-learning-project data-dirty="{{ old('fields') !== null ? 'true' : 'false' }}" data-confirm="{{ __('learning.project_confirm') }}" method="post" action="{{ route('account.learning.project.save',['locale'=>app()->getLocale()]) }}">@csrf<input type="hidden" name="revision" value="{{ old('revision',$project?->revision ?? 0) }}">
@error('revision')<p class="learning-checkpoint-error" role="alert">{{ $message }}</p>@enderror
@foreach(\App\Services\SensoryLearningProject::FIELDS as $key)<section class="learning-card"><label for="project-{{ $key }}"><span class="learning-project-number">{{ $loop->iteration }}</span>{{ __('learning.project_fields.'.$key) }}</label><p class="learning-muted">{{ __('learning.project_hints.'.$key) }}</p><textarea id="project-{{ $key }}" name="fields[{{ $key }}]" rows="5" minlength="25" maxlength="2000" required>{{ old('fields.'.$key,$fields[$key]??'') }}</textarea>@error('fields.'.$key)<p class="learning-checkpoint-error" role="alert">{{ $message }}</p>@enderror</section>@endforeach
<section class="learning-card"><label class="learning-option"><input type="checkbox" name="originality" value="1" required @checked(old('originality'))><span>{{ __('learning.project_originality') }}</span></label><label class="learning-option"><input type="checkbox" name="data_truth" value="1" required @checked(old('data_truth'))><span>{{ __('learning.project_data_truth') }}</span></label>@error('originality')<p role="alert">{{ $message }}</p>@enderror @error('data_truth')<p role="alert">{{ $message }}</p>@enderror</section>
<div class="learning-row learning-exam-actions"><button type="submit" class="secondary" name="action" value="save" formnovalidate>{{ __('learning.project_save') }}</button><button type="submit" name="action" value="submit">{{ __('learning.project_submit') }}</button></div></form>
@else
@if($project->status==='submitted')<p class="learning-notice">{{ __('learning.project_pending_review') }}</p>@endif
@foreach(\App\Services\SensoryLearningProject::FIELDS as $key)<section class="learning-card"><h2>{{ __('learning.project_fields.'.$key) }}</h2><p class="learning-pre">{{ $fields[$key]??'' }}</p></section>@endforeach
@endif
@include('account.learning._project_history')
@endif</div><script src="{{ asset('assets/js/iuoamc-learning-project-1.0.0.js') }}?v={{ filemtime(public_path('assets/js/iuoamc-learning-project-1.0.0.js')) }}" defer></script>
@endsection
