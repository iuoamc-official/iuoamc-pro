@extends('layouts.journal')
@section('title', __('journal.submission_received'))
@push('metadata')<meta name="robots" content="noindex,nofollow,noarchive">@endpush
@section('content')
<section class="public-container journal-receipt"><span>MCIJ · RECEIVED</span><h1>{{ __('journal.submission_received') }}</h1><p>{{ __('journal.submission_received_text') }}</p><div><label>{{ __('journal.submission_code') }}</label><bdi dir="ltr">{{ $receipt['code'] }}</bdi><label>{{ __('journal.tracking_token') }}</label><bdi dir="ltr">{{ $receipt['token'] }}</bdi></div><strong>{{ __('journal.save_tracking_credentials') }}</strong><a class="journal-primary-link" href="{{ route('journal.public.submissions.tracking',['locale'=>app()->getLocale()]) }}">{{ __('journal.track_submission') }}</a></section>
@endsection
