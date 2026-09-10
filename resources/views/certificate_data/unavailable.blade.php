@extends('certificate_data.layout')
@section('title', __('certificate_intake.unavailable_title'))
@section('content')
<section class="ci-result-card"><span class="ci-result-mark" aria-hidden="true"><svg viewBox="0 0 32 32" width="36" height="36"><rect x="7" y="14" width="18" height="14" rx="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M11 14V9a5 5 0 0 1 10 0v5m-5 5v3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span><span class="ci-eyebrow">{{ __('certificate_intake.public_eyebrow') }}</span><h1>{{ __('certificate_intake.unavailable_title') }}</h1><p>{{ __('certificate_intake.unavailable_lead') }}</p><div class="ci-result-note">{{ __('certificate_intake.unavailable_next') }}</div></section>
@endsection
