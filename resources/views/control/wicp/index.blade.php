@extends('layouts.control')
@section('title', __('wicp.title'))
@section('content')
<div class="wicp-module" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
@include('control.wicp._header',['heading'=>__('wicp.title')])
<section class="wicp-card">
<form method="get" action="{{ route('wicp.index',['locale'=>app()->getLocale()]) }}" class="wicp-filters">
<label class="wicp-field"><span>{{ __('wicp.search') }}</span><input name="q" maxlength="120" value="{{ request('q') }}" placeholder="{{ __('wicp.search_hint') }}"></label>
<label class="wicp-field"><span>{{ __('wicp.kind') }}</span><select name="kind"><option value="">{{ __('wicp.all') }}</option>
@foreach(['program','certificate'] as $kind)
<option value="{{ $kind }}" @selected(request('kind')===$kind)>{{ __('wicp.kinds.'.$kind) }}</option>
@endforeach
</select></label>
<label class="wicp-field"><span>{{ __('wicp.record_status') }}</span><select name="status"><option value="">{{ __('wicp.all') }}</option>
@foreach(['registered','revoked'] as $state)
<option value="{{ $state }}" @selected(request('status')===$state)>{{ __('wicp.statuses.'.$state) }}</option>
@endforeach
</select></label>
<button class="wicp-button" type="submit">{{ __('wicp.search') }}</button>
</form>
@if($records->isEmpty())
<div class="wicp-empty"><h2>{{ __('wicp.empty') }}</h2><p>{{ __('wicp.empty_help') }}</p></div>
@else
<p class="wicp-muted">{{ __('wicp.total') }}: {{ $records->total() }}</p>
<div class="wicp-record-grid">
@foreach($records as $record)
<article class="wicp-record">
@if($checks[$record->id])
<header><span class="wicp-kind">{{ __('wicp.kinds.'.$record->kind) }}</span><span class="wicp-status {{ $statuses[$record->id]==='registered'?'wicp-status-good':'wicp-status-review' }}">{{ __('wicp.statuses.'.$statuses[$record->id]) }}</span></header>
<h2><bdi dir="ltr">{{ $record->reference }}</bdi></h2>
<p>{{ $record->kind==='program'?$record->program_title:$record->parent?->program_title }}</p>
<dl class="wicp-facts"><div><dt>{{ __('wicp.organization') }}</dt><dd>{{ $record->organization?->display_name }}</dd></div>
<div><dt>{{ __('wicp.registered_at') }}</dt><dd><bdi>{{ $record->registered_at?->format('Y-m-d') }}</bdi></dd></div></dl>
@else
<p class="wicp-alert">{{ __('wicp.errors.integrity') }}</p>
@endif
<a class="wicp-text-link" href="{{ route('wicp.show',['locale'=>app()->getLocale(),'record'=>$record->id]) }}">{{ __('wicp.view') }}</a>
</article>
@endforeach
</div>
@if($records->hasPages())
<nav class="wicp-pagination" aria-label="{{ __('wicp.pagination') }}">
@if($records->previousPageUrl())
<a href="{{ $records->previousPageUrl() }}" rel="prev">{{ __('wicp.previous') }}</a>
@endif
<span>{{ $records->currentPage() }} / {{ $records->lastPage() }}</span>
@if($records->nextPageUrl())
<a href="{{ $records->nextPageUrl() }}" rel="next">{{ __('wicp.next') }}</a>
@endif
</nav>
@endif
@endif
</section></div>
@endsection
