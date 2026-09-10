@extends('layouts.control')
@section('title', $membership->full_name)
@section('content')
<div class="membership-module">
    <section class="page-heading"><div><span class="eyebrow">IUOAMC / MEMBER PROFILE</span><h1><bdi>{{ $membership->full_name }}</bdi></h1><p><bdi dir="ltr">{{ $membership->membership_number ?? $membership->record_uuid }}</bdi></p></div><a class="secondary-action" href="{{ route('memberships.index',['locale'=>app()->getLocale()]) }}">{{ __('memberships.back') }}</a></section>
    @if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif
    @if(!$integrity)<div class="alert alert-error" role="alert">{{ __('memberships.errors.integrity') }}</div>@endif
    <div class="membership-profile-header"><span class="membership-badge state-{{ $membership->effectiveStatus() }}">{{ __('memberships.states.'.$membership->effectiveStatus()) }}</span><span class="membership-integrity {{ $integrity?'is-valid':'is-invalid' }}">{{ __($integrity?'memberships.verified':'memberships.integrity_failed') }}</span><span>{{ __('memberships.revision') }} {{ $membership->lock_version }}</span></div>
    <div class="membership-detail-grid">
        <section class="form-card"><header><div><h2>{{ __('memberships.identity') }}</h2><p>{{ $membership->organization->display_name }}</p></div></header>
            <dl class="membership-facts"><div><dt>{{ __('memberships.full_name') }}</dt><dd><bdi>{{ $membership->full_name }}</bdi></dd></div><div><dt>{{ __('memberships.latin_name') }}</dt><dd><bdi dir="ltr">{{ $membership->latin_name ?: '—' }}</bdi></dd></div><div><dt>{{ __('memberships.type') }}</dt><dd>{{ $membership->membership_type }}</dd></div><div><dt>{{ __('memberships.professional_title') }}</dt><dd>{{ $membership->professional_title ?: '—' }}</dd></div><div><dt>{{ __('memberships.country') }}</dt><dd><bdi dir="ltr">{{ $membership->country_code ?: '—' }}</bdi></dd></div></dl>
            @if($integrity)<dl class="membership-facts"><div><dt>{{ __('memberships.email') }}</dt><dd><bdi dir="ltr">{{ $membership->email ?: '—' }}</bdi></dd></div><div><dt>{{ __('memberships.phone') }}</dt><dd><bdi dir="ltr">{{ $membership->phone ?: '—' }}</bdi></dd></div></dl><p class="membership-notes">{{ $membership->private_notes }}</p>@endif
            @if($integrity && auth()->user()->canDo('memberships.manage') && in_array($membership->status,['draft','active','suspended'],true))<a class="secondary-action" href="{{ route('memberships.edit',['locale'=>app()->getLocale(),'membership'=>$membership->id]) }}">{{ __('memberships.edit') }}</a>@endif
        </section>
        <section class="form-card"><header><div><h2>{{ __('memberships.workflow') }}</h2><p>{{ __('memberships.workflow_notice') }}</p></div></header>
            @if($integrity)
                @if($membership->status==='draft' && auth()->user()->canDo('memberships.manage'))<form method="post" action="{{ route('memberships.transition',['locale'=>app()->getLocale(),'membership'=>$membership->id,'action'=>'submit']) }}">@csrf<input type="hidden" name="lock_version" value="{{ $membership->lock_version }}"><button class="primary-action" type="submit">{{ __('memberships.actions.submit') }}</button></form>@endif
                @if($membership->status==='pending' && auth()->user()->canDo('memberships.review'))@foreach(['approve','return','reject'] as $action)@include('control.memberships._action')@endforeach @endif
                @if($membership->status==='rejected' && auth()->user()->canDo('memberships.manage'))@include('control.memberships._action',['action'=>'reopen'])@endif
                @if($membership->status==='active' && auth()->user()->canDo('memberships.renew'))@include('control.memberships._action',['action'=>'renew'])@endif
                @if(in_array($membership->status,['active','suspended'],true) && auth()->user()->canDo('memberships.status'))@include('control.memberships._action',['action'=>$membership->status==='active'?'suspend':'reinstate'])@include('control.memberships._action',['action'=>'revoke'])@endif
                @if($membership->status==='revoked')<p>{{ __('memberships.revoked_notice') }}</p>@endif
            @endif
        </section>
    </div>
    <section class="form-card"><header><div><h2>{{ __('memberships.periods') }}</h2><p>{{ __('memberships.periods_notice') }}</p></div></header>
        @forelse($membership->periods as $period)<article class="membership-period"><div><strong>{{ __('memberships.version') }} {{ $period->version }}</strong><span class="membership-integrity {{ $periodChecks[$period->id]?'is-valid':'is-invalid' }}">{{ __($periodChecks[$period->id]?'memberships.verified':'memberships.integrity_failed') }}</span></div><p><bdi dir="ltr">{{ $period->valid_from->format('Y-m-d') }} → {{ $period->valid_until->format('Y-m-d') }}</bdi></p><p>{{ __('memberships.approver') }}: {{ $period->approver?->name }}</p><details><summary>{{ __('memberships.fingerprint') }}</summary><code class="membership-hash" dir="ltr">{{ $period->payload_sha256 }}</code><small>{{ __('memberships.hash_notice') }}</small></details></article>@empty<p>{{ __('memberships.no_periods') }}</p>@endforelse
    </section>
    <section class="form-card"><header><div><h2>{{ __('memberships.history') }}</h2><p>{{ __('memberships.history_notice') }}</p></div></header>
        <ol class="membership-timeline">@foreach($history as $event)<li><strong>{{ __('memberships.events.'.str_replace('membership.','',$event->event)) }}</strong><div><span>{{ $event->actor?->name }}</span> · <time>{{ $event->occurred_at?->format('Y-m-d H:i') }} UTC</time></div>@if($reasons[$event->id])<p>{{ $reasons[$event->id] }}</p>@endif</li>@endforeach</ol>
        @include('control.organizations._pagination',['paginator'=>$history])
    </section>
</div>
@endsection
