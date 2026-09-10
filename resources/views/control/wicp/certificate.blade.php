@extends('layouts.control')
@section('title', __('wicp.new_certificate'))
@section('content')
<div class="wicp-module" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
@include('control.wicp._header',['heading'=>__('wicp.new_certificate')])
<section class="wicp-card"><h2>{{ __('wicp.select_certificate') }}</h2><p class="wicp-muted">{{ __('wicp.certificate_help') }}</p>
@if(!$selected)
<form method="get" action="{{ route('wicp.certificates.create',['locale'=>app()->getLocale()]) }}" class="wicp-filters">
<label class="wicp-field"><span>{{ __('wicp.search') }}</span><input name="q" value="{{ request('q') }}" maxlength="120" placeholder="{{ __('wicp.certificate_search') }}"></label><button class="wicp-button" type="submit">{{ __('wicp.search') }}</button>
</form><p class="wicp-muted">{{ __('wicp.selection_limit') }}</p>
@forelse($certificates as $certificate)
<article class="wicp-selection"><div><h3><bdi dir="ltr">{{ $certificate->certificate_number }}</bdi></h3><p>{{ $certificate->program_title }}</p><small>{{ $certificate->organization?->display_name }}</small></div><a class="wicp-button wicp-button-secondary" href="{{ route('wicp.certificates.create',['locale'=>app()->getLocale(),'certificate'=>$certificate->id]) }}">{{ __('wicp.select') }}</a></article>
@empty
<p class="wicp-empty">{{ __('wicp.no_certificates') }}</p>
@endforelse
@else
<div class="wicp-note"><strong><bdi dir="ltr">{{ $selected->certificate_number }}</bdi></strong><p>{{ $selected->program_title }}</p><small>{{ $selected->organization?->display_name }}</small></div>
<form method="get" action="{{ route('wicp.certificates.create',['locale'=>app()->getLocale()]) }}" class="wicp-filters">
<input type="hidden" name="certificate" value="{{ $selected->id }}"><label class="wicp-field"><span>{{ __('wicp.find_program') }}</span><input name="program_q" value="{{ request('program_q') }}" maxlength="80"></label><button class="wicp-button wicp-button-secondary" type="submit">{{ __('wicp.search') }}</button></form>
<form method="post" action="{{ route('wicp.certificates.store',['locale'=>app()->getLocale()]) }}">
@csrf
<input type="hidden" name="certificate_id" value="{{ $selected->id }}">
<label class="wicp-field"><span>{{ __('wicp.parent_program') }} *</span><select name="parent_id" required><option value="">{{ __('wicp.choose') }}</option>
@foreach($programs as $program)
<option value="{{ $program->id }}" @selected((string)old('parent_id')===(string)$program->id)>{{ $program->reference }} — {{ $program->program_title }} — {{ $program->program_code }} / {{ $program->program_version }}</option>
@endforeach
</select><small>{{ __('wicp.program_selection_limit') }}</small></label>
@if($programs->isEmpty())
<p class="wicp-note">{{ __('wicp.no_programs') }} <a class="wicp-text-link" href="{{ route('wicp.programs.create',['locale'=>app()->getLocale()]) }}">{{ __('wicp.new_program') }}</a></p>
@endif
<label class="wicp-confirm"><input type="checkbox" name="confirmation" value="1" required @checked(old('confirmation'))><span>{{ __('wicp.confirm_certificate') }}</span></label>
<div class="wicp-actions"><button type="submit" class="wicp-button" @disabled($programs->isEmpty())>{{ __('wicp.register_certificate') }}</button><a class="wicp-text-link" href="{{ route('wicp.certificates.create',['locale'=>app()->getLocale()]) }}">{{ __('wicp.change_certificate') }}</a></div>
</form>
@endif
</section></div>
@endsection
