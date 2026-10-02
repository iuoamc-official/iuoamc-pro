
@extends('layouts.control')
@section('title', __('learning.title'))
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-learning-1.0.0.css') }}">
@endpush
@section('content')
<div class="learning"><header class="learning-hero"><h1>{{ __('learning.title') }}</h1><p>{{ __('learning.admin_intro') }}</p></header>
<form class="learning-card" method="post" action="{{ route('learning.control.enroll',['locale'=>app()->getLocale()]) }}">
@csrf<label for="student-email">{{ __('learning.student_email') }}</label><input id="student-email" name="email" type="email" required value="{{ old('email') }}"><p><button>{{ __('learning.enroll_student') }}</button></p></form>
@foreach($enrollments as $record)<article class="learning-card learning-row"><div><h2>{{ $record->name }}</h2><bdi>{{ $record->email }}</bdi><p>{{ $record->reviewed_at ? __('learning.reviewed') : __('learning.pending_review') }}
@if($record->final_score!==null) · {{ $record->final_score }} / 100
@endif</p></div><a class="learning-button" href="{{ route('learning.control.show',['locale'=>app()->getLocale(),'enrollment'=>$record->id]) }}">{{ __('learning.review') }}</a></article>
@endforeach
{{ $enrollments->links() }}</div>
@endsection
