@extends('layouts.control')
@section('title', __('certificate_catalog.batch_title'))
@section('content')
<div class="pc-module" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
<header class="pc-heading"><div><span class="pc-eyebrow">{{ __('certificates.eyebrow') }}</span><h1>{{ __('certificate_catalog.batch_title') }}</h1><p>{{ __('certificate_catalog.batch_lead') }}</p></div>@if(auth()->user()->canDo('certificates.manage'))<a class="pc-button pc-button-primary" href="{{ route('certificates.batches.create',['locale'=>app()->getLocale()]) }}">{{ __('certificate_catalog.new_batch') }} +</a>@endif</header>
@include('control.pro_certificates._tabs')
@include('control.pro_certificates._messages')
@if($collections->isNotEmpty())
<section class="pc-card pc-collection-card"><header class="pc-card-heading"><div><h2>{{ __('certificate_catalog.collections_title') }}</h2><p>{{ __('certificate_catalog.collections_help') }}</p></div></header><div class="pc-table-scroll"><table class="pc-table"><thead><tr><th>{{ __('certificates.program_title') }}</th><th>{{ __('certificates.achievement_date') }}</th><th>{{ __('certificate_catalog.collection_batches') }}</th><th>{{ __('certificate_catalog.collection_certificates') }}</th><th>{{ __('certificates.actions_label') }}</th></tr></thead><tbody>
@foreach($collections as $collection)<tr><td><strong><bdi>{{ $collection['program_title'] }}</bdi></strong><small>{{ $collection['organization_name'] }}</small></td><td><bdi dir="ltr">{{ $collection['achievement_date'] }}</bdi></td><td>{{ $collection['batches']->count() }}</td><td>{{ $collection['members']->count() }}</td><td><a class="pc-button pc-button-primary" href="{{ route('certificates.batches.collections.show',['locale'=>app()->getLocale(),'collection'=>$collection['key']]) }}">{{ __('certificate_catalog.open_collection') }}</a></td></tr>@endforeach
</tbody></table></div></section>
@endif
<section class="pc-card">@if($batches->count())<div class="pc-table-scroll"><table class="pc-table"><thead><tr><th>{{ __('certificate_catalog.batch_name') }}</th><th>{{ __('certificates.organization') }}</th><th>{{ __('certificate_catalog.batch_count') }}</th><th>{{ __('certificate_catalog.batch_created_at') }}</th><th>{{ __('certificates.actions_label') }}</th></tr></thead><tbody>
@foreach($batches as $batch)<tr>@if($checks[$batch->id])<td><strong><bdi>{{ $batch->label }}</bdi></strong></td><td>{{ $batch->organization?->display_name }}</td><td>{{ $batch->count }}</td><td><bdi dir="ltr">{{ $batch->created_at?->format('Y-m-d H:i') }} UTC</bdi></td><td><a class="pc-text-link" href="{{ route('certificates.batches.show',['locale'=>app()->getLocale(),'batch'=>$batch->id]) }}">{{ __('certificates.open') }}</a></td>@else<td colspan="5">{{ __('certificates.integrity_failed') }}</td>@endif</tr>@endforeach
</tbody></table></div>@include('control.pro_certificates._pagination',['paginator'=>$batches])@else<div class="pc-empty"><h2>{{ __('certificate_catalog.batch_empty') }}</h2><p>{{ __('certificate_catalog.batch_lead') }}</p></div>@endif</section>
</div>
@endsection
