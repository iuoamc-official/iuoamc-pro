@extends('layouts.account')
@section('title', $lesson['title'])
@section('content')
<div class="learning learning-study" data-learning-reader>
@include('account.learning._nav')
@include('account.learning._checkpoint_feedback')
<nav class="learning-reader-tools" aria-label="{{ __('learning.reading_options') }}"><span>{{ __('learning.reading_options') }}</span><button type="button" data-reader-action="smaller" aria-label="{{ __('learning.font_smaller') }}">A−</button><button type="button" data-reader-action="larger" aria-label="{{ __('learning.font_larger') }}">A+</button><button type="button" data-reader-action="night" aria-pressed="false">{{ __('learning.night_mode') }}</button><button type="button" data-reader-action="reset">{{ __('learning.reset_reading') }}</button></nav>
<div class="learning-reading-layout">
<aside class="learning-lesson-sidebar"><div class="learning-study-progress"><span>{{ __('learning.completed_pages') }}</span><strong>{{ count($completed) }} / 30</strong><progress aria-label="{{ __('learning.progress') }}" value="{{ count($completed) }}" max="30"></progress><small>{{ __('learning.step_by_step') }}</small></div><details open><summary>{{ __('learning.lesson_outline') }}</summary><nav aria-label="{{ __('learning.lesson_outline') }}" lang="ar" dir="rtl">
@foreach($course['sections'] as $item)
@if(in_array($item['id'],$available,true))<a href="{{ route('account.learning.lesson',['locale'=>app()->getLocale(),'section'=>$item['id']]) }}" @if($item['id']===$lesson['id']) aria-current="page" @endif><span>{{ $item['id'] }}</span><div>{{ $item['title'] }}@if(in_array($item['id'],$completed,true))<small class="learning-check">✓ {{ __('learning.completed') }}</small>@endif</div></a>
@else<div class="learning-sidebar-locked" aria-disabled="true"><span>{{ $item['id'] }}</span><div>{{ $item['title'] }}<small>{{ __('learning.locked') }}</small></div></div>@endif
@endforeach</nav></details></aside>
<article class="learning-card learning-reading" lang="ar" dir="rtl"><header class="learning-study-header"><span class="learning-kicker">صفحة {{ $lesson['id'] }} من 30</span><h1>{{ $lesson['title'] }}</h1>
@php($readingWords = count(preg_split('/\s+/u', implode(' ', $lesson['paragraphs']).' '.$lesson['support']['explanation'].' '.$lesson['support']['example'], -1, PREG_SPLIT_NO_EMPTY)))
<p class="learning-muted">{{ max(3, (int)ceil($readingWords / 150)) }} {{ __('learning.read_minutes') }} · {{ __('learning.checkpoint_meta') }}</p></header>
<section class="learning-goal"><h2>{{ __('learning.learning_goal') }}</h2><p>{{ $lesson['support']['goal'] }}</p></section>
<figure class="learning-figure"><img src="{{ asset('assets/images/learning/'.$lesson['support']['figure'].'.svg') }}" alt="{{ $lesson['support']['caption'] }}" width="900" height="460" decoding="async"><figcaption>{{ $lesson['support']['caption'] }}<small>{{ __('learning.figure_note') }}</small></figcaption></figure>
<nav class="learning-content-links" aria-label="{{ __('learning.contents') }}"><a href="#core-lesson">{{ __('learning.core_explanation') }}</a><a href="#further-explanation">{{ __('learning.deeper_explanation') }}</a><a href="#worked-example">{{ __('learning.worked_example') }}</a><a href="#practice">{{ __('learning.practice_task') }}</a><a href="#lesson-checkpoint">{{ __('learning.checkpoint_title') }}</a></nav>
<section id="core-lesson">
@foreach($lesson['paragraphs'] as $paragraph)
@if(in_array($paragraph,['تحليل متقدم','تطبيق مهني','سؤال للتفكير','مخرج الصفحة'],true))<h2>{{ $paragraph }}</h2>@else<p>{{ $paragraph }}</p>@endif
@endforeach</section>
<section id="further-explanation"><h2>{{ __('learning.deeper_explanation') }}</h2><p>{{ $lesson['support']['explanation'] }}</p></section>
<section class="learning-example" id="worked-example"><h2>{{ __('learning.worked_example') }}</h2><p>{{ $lesson['support']['example'] }}</p></section>
<section class="learning-practice" id="practice"><h2>{{ __('learning.practice_task') }}</h2><p>{{ $lesson['support']['practice'] }}</p></section>
<section class="learning-takeaway"><h2>{{ __('learning.key_takeaway') }}</h2><p>{{ $lesson['support']['takeaway'] }}</p></section>
<p class="learning-muted">{{ __('learning.references') }}: @foreach($lesson['support']['references'] as $reference)<a href="{{ route('account.learning.index',['locale'=>app()->getLocale()]) }}#references">[{{ $reference }}]</a> @endforeach</p>
<section class="learning-checkpoint" id="lesson-checkpoint" aria-labelledby="checkpoint-title">
@if(in_array($lesson['id'],$completed,true))
<div class="learning-completed-banner"><span aria-hidden="true">✓</span><div><h2 id="checkpoint-title">{{ __('learning.completed') }}</h2><p>{{ __('learning.lesson_already_completed') }}</p></div></div>
@else
<header><span class="learning-checkpoint-number">{{ $lesson['id'] }} / 30</span><h2 id="checkpoint-title">{{ __('learning.checkpoint_title') }}</h2><p>{{ __('learning.checkpoint_hint') }}</p></header>
<form method="post" action="{{ route('account.learning.complete',['locale'=>app()->getLocale(),'section'=>$lesson['id']]) }}">@csrf
<fieldset><legend>{{ $checkpoint['prompt'] }}</legend>
@foreach($checkpoint['options'] as $index=>$option)<label class="learning-option learning-checkpoint-option"><input type="radio" name="lesson_answer" value="{{ $index }}" required @checked((string)old('lesson_answer','')===(string)$index) @error('lesson_answer') aria-invalid="true" aria-describedby="checkpoint-error" @enderror><span>{{ $option }}</span></label>@endforeach
</fieldset>
@error('lesson_answer')<p class="learning-checkpoint-error" id="checkpoint-error" role="alert">{{ $message }}</p>@enderror
<button type="submit">{{ $lesson['id']===30 ? __('learning.checkpoint_finish') : __('learning.checkpoint_continue') }} ←</button>
</form>
@endif
</section>
<section class="learning-notes" id="lesson-notes"><h2>{{ __('learning.private_notes') }}</h2><p>{{ __('learning.notes_hint') }}</p><form data-learning-notes data-dirty="{{ old('note_body') !== null && old('note_body') !== ($note?->body ?? '') ? 'true' : 'false' }}" data-unsaved="{{ __('learning.notes_unsaved') }}" method="post" action="{{ route('account.learning.notes.save',['locale'=>app()->getLocale(),'section'=>$lesson['id']]) }}">@csrf<input type="hidden" name="note_revision" value="{{ old('note_revision',$note?->revision ?? 0) }}"><label class="learning-visually-hidden" for="note-body">{{ __('learning.private_notes') }}</label><textarea id="note-body" name="note_body" maxlength="12000" rows="7">{{ old('note_body',$note?->body ?? '') }}</textarea>@error('note_body')<p role="alert">{{ $message }}</p>@enderror @error('note_revision')<p class="learning-checkpoint-error" role="alert">{{ $message }}</p>@enderror<button type="submit">{{ __('learning.save_notes') }}</button>@if($note)<small>{{ __('learning.note_saved_at') }}: <bdi>{{ $note->updated_at }} UTC</bdi></small>@endif</form></section>
<div class="learning-row learning-study-footer">@if($lesson['id']>1 && in_array($lesson['id']-1,$available,true))<a class="learning-button secondary" href="{{ route('account.learning.lesson',['locale'=>app()->getLocale(),'section'=>$lesson['id']-1]) }}">{{ __('learning.previous') }}</a>@endif
@if($lesson['id']<30)
@if(in_array($lesson['id']+1,$available,true))<a class="learning-button" href="{{ route('account.learning.lesson',['locale'=>app()->getLocale(),'section'=>$lesson['id']+1]) }}">{{ __('learning.next') }} ←</a>@else<span class="learning-lock-note">{{ __('learning.lesson_locked') }}</span>@endif
@elseif(count($completed)===30)<a class="learning-button" href="{{ route('account.learning.exam',['locale'=>app()->getLocale()]) }}">{{ __('learning.exam') }} ←</a>@endif
</div>
</article></div></div>
<script src="{{ asset('assets/js/iuoamc-learning-experience-1.0.0.js') }}?v={{ filemtime(public_path('assets/js/iuoamc-learning-experience-1.0.0.js')) }}" defer></script>
@endsection
