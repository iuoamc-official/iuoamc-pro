@extends('layouts.control')
@section('title', __('wicp.new_program'))
@section('content')
<div class="wicp-module" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
@include('control.wicp._header',['heading'=>__('wicp.new_program')])
<form method="post" action="{{ route('wicp.programs.store',['locale'=>app()->getLocale()]) }}" class="wicp-card">
@csrf
<h2>{{ __('wicp.program_identity') }}</h2><p class="wicp-muted">{{ __('wicp.program_help') }}</p>
<div class="wicp-fields">
<label class="wicp-field wicp-full"><span>{{ __('wicp.organization') }} *</span><select name="organization_id" required><option value="">{{ __('wicp.choose') }}</option>
@foreach($organizations as $organization)
<option value="{{ $organization->id }}" @selected((string)old('organization_id')===(string)$organization->id)>{{ $organization->display_name }}</option>
@endforeach
</select></label>
<label class="wicp-field"><span>{{ __('wicp.program_code') }} *</span><input name="program_code" value="{{ old('program_code') }}" required maxlength="80" pattern="[A-Z0-9][A-Z0-9._\-]{0,79}" dir="ltr" autocomplete="off"><small>{{ __('wicp.code_help') }}</small></label>
<label class="wicp-field"><span>{{ __('wicp.program_version') }} *</span><input name="program_version" value="{{ old('program_version') }}" required maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9._\-]{0,79}" dir="ltr" autocomplete="off"></label>
<label class="wicp-field wicp-full"><span>{{ __('wicp.program_title') }} *</span><input name="program_title" value="{{ old('program_title') }}" required maxlength="255"></label>
</div>
<div class="wicp-note"><strong>{{ __('wicp.authority') }}:</strong> {{ $authority->display_name }}<p>{{ __('wicp.public_fields_notice') }}</p></div>
<label class="wicp-confirm"><input type="checkbox" name="confirmation" value="1" required @checked(old('confirmation'))><span>{{ __('wicp.confirm_program') }}</span></label>
<div class="wicp-actions"><button type="submit" class="wicp-button">{{ __('wicp.register_program') }}</button><a class="wicp-text-link" href="{{ route('wicp.index',['locale'=>app()->getLocale()]) }}">{{ __('wicp.cancel') }}</a></div>
</form></div>
@endsection
