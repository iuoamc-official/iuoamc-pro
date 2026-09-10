@extends('layouts.control')
@section('title', __('certificate_catalog.new_batch'))
@section('content')
<div class="pc-module" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
<header class="pc-heading"><div><span class="pc-eyebrow">{{ __('certificates.eyebrow') }}</span><h1>{{ __('certificate_catalog.new_batch') }}</h1><p>{{ __('certificate_catalog.batch_create_lead') }}</p></div><a class="pc-button pc-button-secondary" href="{{ route('certificates.batches.index',['locale'=>app()->getLocale()]) }}">{{ __('certificates.back') }}</a></header>
@include('control.pro_certificates._tabs')
@include('control.pro_certificates._messages')
@include('control.pro_certificates._type_picker',['pickerAction'=>route('certificates.batches.create',['locale'=>app()->getLocale()])])
@if($selectedType)
<form class="pc-form" method="post" action="{{ route('certificates.batches.store',['locale'=>app()->getLocale()]) }}">@csrf
<input type="hidden" name="request_key" value="{{ old('request_key',$requestKey) }}"><input type="hidden" name="catalog_type_id" value="{{ $selectedType->id }}"><input type="hidden" name="language" value="{{ $common['language'] }}"><input type="hidden" name="catalog_version" value="{{ old('catalog_version',$common['catalog_version']??$selectedType->lock_version) }}">
@if($ticket)<input type="hidden" name="preview_ticket" value="{{ $ticket }}">@endif
<section class="pc-card"><header class="pc-card-heading"><div><h2>{{ __('certificate_catalog.batch_common') }}</h2><p><bdi>{{ $selectedType->organization?->display_name }} · {{ $selectedType->{'name_'.app()->getLocale()} }}</bdi> · {{ __('certificate_catalog.language_'.$common['language']) }}</p></div><span class="pc-badge pc-state-approved"><bdi dir="ltr">{{ $selectedType->number_prefix }}</bdi></span></header><div class="pc-fields">
<label class="pc-field"><span>{{ __('certificate_catalog.batch_name') }} *</span><input name="batch_name" required maxlength="120" value="{{ old('batch_name',$common['batch_name']??'') }}"></label>
<label class="pc-field"><span>{{ __('certificates.program_title') }} *</span><input name="program_title" required maxlength="200" value="{{ old('program_title',$common['program_title']??'') }}"></label>
<label class="pc-field"><span>{{ __('certificates.certificate_title') }} *</span><input name="certificate_title" required maxlength="120" value="{{ old('certificate_title',$common['certificate_title']??'') }}"></label>
<label class="pc-field"><span>{{ __('certificates.achievement_date') }} *</span><input name="achievement_date" type="date" required dir="ltr" value="{{ old('achievement_date',$common['achievement_date']??'') }}"></label>
<label class="pc-field"><span>{{ __('certificates.expires_on') }}</span><input name="expires_on" type="date" dir="ltr" value="{{ old('expires_on',$common['expires_on']??'') }}"><small>{{ __('certificate_catalog.no_expiry_default') }}</small></label>
<label class="pc-field pc-full"><span>{{ __('certificates.statement') }} *</span><textarea name="statement" required maxlength="1500" rows="5">{{ old('statement',$common['statement']??'') }}</textarea></label>
<label class="pc-field"><span>{{ __('certificates.signatory_name') }} *</span><input name="signatory_name" required maxlength="120" value="{{ old('signatory_name',$common['signatory_name']??'') }}"></label>
<label class="pc-field"><span>{{ __('certificates.signatory_title') }} *</span><input name="signatory_title" required maxlength="120" value="{{ old('signatory_title',$common['signatory_title']??'') }}"></label>
</div></section>
<section class="pc-card"><header class="pc-card-heading"><div><h2>{{ __('certificate_catalog.batch_rows') }}</h2><p>{{ __('certificate_catalog.batch_tsv_help') }}</p></div></header><p class="pc-help">{{ __('certificate_catalog.batch_tsv_header') }}</p><pre class="pc-tsv-heading" dir="ltr">recipient_name&#9;public_name&#9;specialization</pre>
@if($preservedTemplate)
<div class="pc-notice pc-notice-success"><strong>{{ __('certificate_catalog.preserved_loaded',['count'=>$preservedTemplate['count']]) }}</strong><p>{{ __('certificate_catalog.preserved_public_names_required') }}</p></div>
@else
<div class="pc-button-row pc-preserved-loader"><a class="pc-button pc-button-secondary" href="{{ route('certificates.batches.create',['locale'=>app()->getLocale(),'type_id'=>$selectedType->id,'language'=>$common['language'],'source'=>'preserved_students']) }}">{{ __('certificate_catalog.load_preserved_students') }}</a><span class="pc-help">{{ __('certificate_catalog.load_preserved_students_help') }}</span></div>
@endif
<label class="pc-field"><span>{{ __('certificate_catalog.batch_rows') }} *</span><textarea name="recipient_rows" required maxlength="100000" rows="12" spellcheck="false" data-pc-tsv aria-describedby="pc-tsv-description">{{ old('recipient_rows',$preservedTemplate['tsv']??"recipient_name\tpublic_name\tspecialization\n") }}</textarea><small id="pc-tsv-description">{{ __('certificates.public_name_help') }} {{ __('certificate_catalog.specialization_help') }}</small></label></section>
@if($rows !== null)
<section class="pc-card pc-batch-preview"><header class="pc-card-heading"><div><h2>{{ __('certificate_catalog.batch_preview_heading') }}</h2><p>{{ __('certificate_catalog.batch_preview_help') }}</p></div><strong>{{ count($rows) }} {{ __('certificate_catalog.record_count') }}</strong></header>
<div class="pc-table-scroll"><table class="pc-table"><thead><tr><th>#</th><th>{{ __('certificates.recipient_name') }}</th><th>{{ __('certificates.public_name') }}</th><th>{{ __('certificate_catalog.specialization') }}</th></tr></thead><tbody>@foreach($rows as $row)<tr><td>{{ $loop->iteration }}</td><td><bdi>{{ $row['recipient_name'] }}</bdi></td><td><bdi>{{ $row['public_name'] }}</bdi></td><td><bdi>{{ ($row['specialization']!==null && $row['specialization']!=='') ? $row['specialization'] : '—' }}</bdi></td></tr>@endforeach</tbody></table></div><p class="pc-help">{{ __('certificate_catalog.batch_changes_preview') }}</p>
<label class="pc-save-confirm"><input type="checkbox" name="confirm_save" value="1"><span>{{ __('certificate_catalog.batch_save_confirm') }}</span></label>
</section>
@endif
<div class="pc-form-footer"><p>{{ __('certificate_catalog.preview_only') }}</p><div class="pc-button-row"><button name="intent" value="preview" class="pc-button pc-button-secondary" type="submit">{{ __('certificate_catalog.batch_preview') }}</button>@if($ticket)<button name="intent" value="create" class="pc-button pc-button-primary" type="submit">{{ __('certificate_catalog.batch_save') }}</button>@endif</div></div>
</form>@endif</div>
@endsection
