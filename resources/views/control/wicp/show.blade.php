@extends('layouts.control')
@section('title', __('wicp.record_details'))
@section('content')
<div class="wicp-module" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
@include('control.wicp._header',['heading'=>__('wicp.record_details')])
@if(!$integrity)
<div class="wicp-notice wicp-alert" role="alert">{{ __('wicp.errors.integrity') }}</div>
@else
<section class="wicp-card"><header class="wicp-detail-heading"><div><span class="wicp-kind">{{ __('wicp.kinds.'.$record->kind) }}</span><h2><bdi dir="ltr">{{ $record->reference }}</bdi></h2></div><span class="wicp-status {{ $status==='registered'?'wicp-status-good':'wicp-status-review' }}">{{ __('wicp.statuses.'.$status) }}</span></header>
<dl class="wicp-facts wicp-facts-grid"><div><dt>{{ __('wicp.organization') }}</dt><dd>{{ $record->organization?->display_name }}</dd></div><div><dt>{{ __('wicp.registered_at') }}</dt><dd><bdi>{{ $record->registered_at?->format('Y-m-d H:i') }} UTC</bdi></dd></div>
<div><dt>{{ __('wicp.program_title') }}</dt><dd>{{ $record->kind==='program'?$record->program_title:$record->parent?->program_title }}</dd></div>
<div><dt>{{ __('wicp.program_code') }} / {{ __('wicp.program_version') }}</dt><dd><bdi dir="ltr">{{ $record->kind==='program'?$record->program_code:$record->parent?->program_code }} / {{ $record->kind==='program'?$record->program_version:$record->parent?->program_version }}</bdi></dd></div>
@if($record->kind==='certificate')
<div><dt>{{ __('wicp.parent_program') }}</dt><dd><a class="wicp-text-link" href="{{ route('wicp.show',['locale'=>app()->getLocale(),'record'=>$record->parent_id]) }}"><bdi>{{ $record->parent?->reference }}</bdi></a></dd></div>
<div><dt>{{ __('wicp.certificate_number') }}</dt><dd>
@if(auth()->user()->canDo('certificates.view'))
<a class="wicp-text-link" href="{{ route('certificates.show',['locale'=>app()->getLocale(),'certificate'=>$record->certificate_id]) }}"><bdi>{{ $record->source_number }}</bdi></a>
@else
<bdi>{{ $record->source_number }}</bdi>
@endif
</dd></div>
@endif
</dl><p class="wicp-note">{{ __('wicp.integrity_notice') }}</p>
<div class="wicp-actions"><a class="wicp-button" href="{{ route('wicp.verify',['token'=>$record->public_token,'lang'=>app()->getLocale()]) }}" target="_blank" rel="noopener noreferrer">{{ __('wicp.public_page') }}</a><a class="wicp-button wicp-button-secondary" href="{{ route('wicp.proof',['token'=>$record->public_token,'lang'=>app()->getLocale()]) }}">{{ __('wicp.download_proof') }}</a></div>
<label class="wicp-field wicp-url"><span>{{ __('wicp.verification_url') }}</span><input type="text" readonly dir="ltr" value="{{ route('wicp.verify',['token'=>$record->public_token]) }}"></label>
</section>
@if($record->status==='registered' && auth()->user()->canDo('wicp.revoke'))
<section class="wicp-card"><details class="wicp-revoke"><summary>{{ __('wicp.revoke') }}</summary><p>{{ __('wicp.revoke_help') }}</p><form method="post" action="{{ route('wicp.revoke',['locale'=>app()->getLocale(),'record'=>$record->id]) }}">
@csrf
<input type="hidden" name="lock_version" value="{{ $record->lock_version }}"><label class="wicp-field"><span>{{ __('wicp.reason') }} *</span><textarea name="reason" required maxlength="1500" rows="3">{{ old('reason') }}</textarea></label><label class="wicp-confirm"><input type="checkbox" name="confirmation" value="1" required><span>{{ __('wicp.confirm_revoke') }}</span></label><button class="wicp-button wicp-button-danger" type="submit">{{ __('wicp.revoke') }}</button></form></details></section>
@endif
@endif
</div>
@endsection
