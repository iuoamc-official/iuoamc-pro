<section class="pc-card pc-type-picker"><header class="pc-card-heading"><div><h2>{{ __('certificate_catalog.choose_type') }}</h2><p>{{ __('certificate_catalog.choose_type_help') }}</p></div></header>
@if($types->isEmpty())
<div class="pc-empty"><h2>{{ __('certificate_catalog.empty_catalog') }}</h2><p>{{ __('certificate_catalog.empty_catalog_help') }}</p>
@if(auth()->user()->canDo('certificates.catalog'))<a class="pc-button pc-button-primary" href="{{ route('certificates.catalog.create',['locale'=>app()->getLocale()]) }}">{{ __('certificate_catalog.configure_type') }}</a>@endif</div>
@else
<form method="get" action="{{ $pickerAction }}" class="pc-fields">
<label class="pc-field"><span>{{ __('certificate_catalog.type_name') }} *</span><select name="type_id" required><option value="">{{ __('certificates.choose') }}</option>@foreach($types as $choice)<option value="{{ $choice->id }}" @selected((int)request('type_id')===(int)$choice->id)>{{ $choice->{'name_'.app()->getLocale()} }} — {{ $choice->organization?->display_name }} ({{ $choice->code }})</option>@endforeach</select></label>
<label class="pc-field"><span>{{ __('certificates.language') }} *</span><select name="language" required>@foreach(['ar'=>'العربية','en'=>'English','fr'=>'Français'] as $key=>$label)<option value="{{ $key }}" @selected(request('language',app()->getLocale())===$key)>{{ $label }}</option>@endforeach</select></label>
<div class="pc-full"><button class="pc-button pc-button-primary" type="submit">{{ __('certificate_catalog.continue') }}</button></div>
</form>
@endif</section>
