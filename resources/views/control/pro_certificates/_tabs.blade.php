<nav class="pc-module-tabs" aria-label="{{ __('certificate_catalog.navigation') }}">
    <a href="{{ route('certificates.index',['locale'=>app()->getLocale()]) }}" @if(!request()->routeIs('certificates.catalog.*','certificates.batches.*','certificates.runs.*','certificates.master.*','wicp.*','certificates.intakes.*')) aria-current="page" @endif>{{ __('certificate_catalog.register_tab') }}</a>
    <a href="{{ route('certificates.catalog.index',['locale'=>app()->getLocale()]) }}" @if(request()->routeIs('certificates.catalog.*')) aria-current="page" @endif>{{ __('certificate_catalog.catalog_tab') }}</a>
    <a href="{{ route('certificates.batches.index',['locale'=>app()->getLocale()]) }}" @if(request()->routeIs('certificates.batches.*','certificates.runs.*')) aria-current="page" @endif>{{ __('certificate_catalog.batches_tab') }}</a>
    <a href="{{ route('certificates.master.index',['locale'=>app()->getLocale()]) }}" @if(request()->routeIs('certificates.master.*')) aria-current="page" @endif>{{ __('master_certificates.tab') }}</a>
    @if(auth()->user()->canDo('wicp.view'))
    <a href="{{ route('wicp.index',['locale'=>app()->getLocale()]) }}">{{ __('wicp.title') }}</a>
    @endif

    {{-- IUOAMC_CERTIFICATE_DATA_FORM_1_0_0 --}}
    @if(auth()->user()->canDo('certificates.manage'))
        <a href="{{ route('certificates.intakes.programs.index',['locale'=>app()->getLocale()]) }}" @if(request()->routeIs('certificates.intakes.*')) aria-current="page" @endif>{{ __('certificate_intake.title') }}</a>
    @endif
</nav>
<script src="{{ asset('assets/js/iuoamc-pro-certificates-1.1.0.js') }}" defer></script>
