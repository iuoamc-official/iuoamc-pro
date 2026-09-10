@extends('certificate_data.layout')
@section('title', __('certificate_intake.received_title'))
@section('languages')
<nav class="ci-languages" aria-label="{{ __('certificate_intake.language') }}">@foreach(['ar'=>'العربية','en'=>'English','fr'=>'Français'] as $language=>$label)<a href="{{ route('certificate-data.received',['locale'=>$language]) }}" lang="{{ $language }}" hreflang="{{ $language }}" @if(app()->getLocale()===$language) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
@endsection
@section('content')
<section class="ci-result-card"><span class="ci-result-mark ci-result-success" aria-hidden="true"><svg viewBox="0 0 32 32" width="36" height="36"><path d="m7 16 6 6L26 9" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span class="ci-eyebrow">{{ __('certificate_intake.public_eyebrow') }}</span><h1>{{ __('certificate_intake.received_title') }}</h1><p>{{ __('certificate_intake.received_lead') }}</p><div class="ci-result-note">{{ __('certificate_intake.received_next') }}</div><p class="ci-muted">{{ __('certificate_intake.close_window') }}</p></section>
@endsection
