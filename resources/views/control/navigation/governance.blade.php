@extends('layouts.control')
@section('title', __('institutional.governance_section'))
@section('content')
<section class="page-heading"><div><span class="eyebrow">IUOAMC / GOVERNANCE</span><h1>{{ __('institutional.governance_section') }}</h1><p>{{ __('memberships.governance_lead') }}</p></div></section>
@include('control.navigation._governance_links',['hub'=>true])
@endsection
