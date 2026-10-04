@extends('layouts.control')
@section('title', __('learning.manage_email'))
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-learning-1.0.0.css') }}?v={{ filemtime(public_path('assets/css/iuoamc-learning-1.0.0.css')) }}">@endpush
@section('content')
<div class="learning">
<header class="learning-hero"><h1>{{ __('learning.manage_email') }}</h1><p><bdi>{{ $email }}</bdi></p></header>
<p><a class="learning-button secondary" href="{{ route('learning.control.index',['locale'=>app()->getLocale()]) }}">{{ __('learning.manage_back') }}</a></p>
@if($editUrl)
<form class="learning-card" method="post" action="{{ $editUrl }}">
@csrf @method('PUT')
<h2>{{ __('learning.edit_invitation_email') }}</h2><p>{{ __('learning.edit_invitation_hint') }}</p>
<label for="edit-confirmation">{{ __('learning.confirm_email_label') }}</label>
<input id="edit-confirmation" name="confirmation_email" type="email" dir="ltr" required maxlength="254" autocomplete="off">
<label for="edit-email">{{ __('learning.student_email') }}</label>
<input id="edit-email" name="email" type="email" dir="ltr" required maxlength="254" value="{{ old('email',$email) }}">
<p><button>{{ __('learning.edit_invitation_email') }}</button></p>
</form>
@endif
<form class="learning-card" method="post" action="{{ $deleteUrl }}">
@csrf @if($isEnrollment) @method('DELETE') @endif
<h2>{{ __($isEnrollment ? 'learning.delete_registration' : 'learning.archive_invitation') }}</h2>
<p>{{ __($isEnrollment ? 'learning.delete_registration_hint' : 'learning.archive_invitation_hint') }}</p>
<label for="delete-confirmation">{{ __('learning.confirm_email_label') }}</label>
<input id="delete-confirmation" name="confirmation_email" type="email" dir="ltr" required maxlength="254" autocomplete="off">
<p><button>{{ __($isEnrollment ? 'learning.delete_registration' : 'learning.archive_invitation') }}</button></p>
</form>
</div>
@endsection
