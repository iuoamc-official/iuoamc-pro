
@extends('layouts.account')
@section('title', __('learning.assignment'))
@section('content')
<div class="learning">
@include('account.learning._nav')<header class="learning-hero"><h1>{{ __('learning.assignment') }}</h1><p>{{ __('learning.assignment_instructions') }}</p></header>
<div class="learning-card"><p lang="en" dir="ltr">Plate shape and colour interact to influence taste and quality judgments (2013)</p><a target="_blank" rel="noopener" class="learning-source" href="{{ $course['assignment']['source_url'] }}">{{ __('learning.read_paper') }} ↗</a></div>
@if($enrollment->summary_submitted_at)<div class="learning-card" lang="ar" dir="rtl"><h2>{{ __('learning.submitted') }}</h2><ol>
@foreach($lines as $line)<li><p>{{ $line }}</p></li>
@endforeach</ol></div>
@else
<div class="learning-summary-meter" role="status">{{ __('learning.word_count') }}: <strong data-word-count>0</strong> / 180–250 · {{ __('learning.ten_lines') }}</div>
<form data-learning-summary data-confirm="{{ __('learning.confirm_submission') }}" method="post" action="{{ route('account.learning.assignment.submit',['locale'=>app()->getLocale()]) }}">
@csrf
<div lang="ar" dir="rtl">
@foreach($course['assignment']['line_prompts'] as $index=>$prompt)<div class="learning-card"><label for="summary-{{ $index }}">{{ $index+1 }}. {{ $prompt }}</label><textarea id="summary-{{ $index }}" name="lines[{{ $index }}]" maxlength="1500" required>{{ old('lines.'.$index,$lines[$index]??'') }}</textarea></div>
@endforeach</div>
<button type="submit">{{ __('learning.submit_assignment') }}</button></form>
@endif</div>
<script src="{{ asset('assets/js/iuoamc-learning-1.1.0.js') }}" defer></script>
@endsection
