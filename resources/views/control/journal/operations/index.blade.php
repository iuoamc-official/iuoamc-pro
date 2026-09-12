@extends('layouts.control')
@section('title', __('journal.launch_operations'))
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-journal-1.0.0.css') }}">@endpush
@section('content')
<div class="journal-control">
    @include('control.journal._nav')
    <section class="page-heading"><div><span class="eyebrow">MCIJ / PRE-LAUNCH CONTROL</span><h1>{{ __('journal.launch_operations') }}</h1><p>{{ __('journal.launch_operations_intro') }}</p></div><span class="status-badge status-{{ $journal->isPubliclyLaunched() ? 'published' : 'draft' }}">{{ $journal->isPubliclyLaunched() ? __('journal.launch_live') : __('journal.launch_locked') }}</span></section>

    @if($errors->any())<div class="alert alert-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <section class="data-card">
        <div class="data-card-header"><div><h2>{{ __('journal.publication_metrics') }}</h2><p>{{ __('journal.publication_metrics_help') }}</p></div></div>
        <div class="journal-readiness-list">
            @foreach($publicationMetrics as $metric => $value)<article class="passed"><strong>{{ number_format($value) }}</strong><p>{{ __('journal.metrics.'.$metric) }}</p></article>@endforeach
        </div>
    </section>

    <section class="data-card">
        <div class="data-card-header"><div><h2>{{ __('journal.launch_checklist') }}</h2><p>{{ __('journal.launch_checklist_help') }}</p></div></div>
        <div class="journal-readiness-list">@foreach($checks as $key => $check)<article class="{{ $check['passed'] ? 'passed' : 'failed' }}"><strong>{{ $check['passed'] ? '✓' : '!' }} {{ __('journal.readiness.'.$key) }}</strong><p>{{ $check['detail'] }}</p></article>@endforeach</div>
    </section>

    <div class="journal-control-grid">
        <section class="data-card"><div class="data-card-header"><div><h2>{{ __('journal.publication_settings') }}</h2></div></div>
            <form class="journal-stack-form" method="post" action="{{ route('journal.control.operations.update',['locale'=>app()->getLocale()]) }}">@csrf @method('put')
                <label>{{ __('journal.contact_email') }}<input type="email" name="contact_email" maxlength="254" required value="{{ old('contact_email',$journal->setting('contact_email')) }}"></label>
                <label>{{ __('journal.publication_frequency') }}<select name="publication_frequency" required><option value="">{{ __('journal.choose') }}</option>@foreach(\App\Services\JournalLaunchReadiness::FREQUENCIES as $frequency)<option value="{{ $frequency }}" @selected(old('publication_frequency',$journal->setting('publication_frequency'))===$frequency)>{{ __('journal.frequencies.'.$frequency) }}</option>@endforeach</select></label>
                <label>{{ __('journal.fee_policy') }}<select name="fee_policy" required><option value="">{{ __('journal.choose') }}</option>@foreach(\App\Services\JournalLaunchReadiness::FEE_POLICIES as $policy)<option value="{{ $policy }}" @selected(old('fee_policy',$journal->setting('fee_policy'))===$policy)>{{ __('journal.fee_policies.'.$policy) }}</option>@endforeach</select></label>
                <label>{{ __('journal.publisher_person') }}<input name="publisher_person_name" maxlength="255" required value="{{ old('publisher_person_name',$journal->setting('publisher_person_name','Ahmad Maadarani')) }}"></label>
                @foreach(['ar'=>'العربية','en'=>'English','fr'=>'Français'] as $language=>$label)<fieldset><legend>{{ $label }} — {{ __('journal.publisher_profile') }}</legend><label>{{ __('journal.professional_title') }}<input name="publisher_title[{{ $language }}]" maxlength="255" required value="{{ old('publisher_title.'.$language,($journal->setting('publisher_title',[]))[$language]??'') }}"></label><label>{{ __('journal.biography') }}<textarea name="publisher_biography[{{ $language }}]" maxlength="3000" required>{{ old('publisher_biography.'.$language,($journal->setting('publisher_biography',[]))[$language]??'') }}</textarea></label></fieldset>@endforeach
                <button class="primary-action">{{ __('journal.save_settings') }}</button>
            </form>
        </section>
        <section class="data-card"><div class="data-card-header"><div><h2>{{ __('journal.backup_verification') }}</h2><p>{{ __('journal.backup_verification_help') }}</p></div></div>
            <form class="journal-stack-form" method="post" action="{{ route('journal.control.operations.backup-verifications.store',['locale'=>app()->getLocale()]) }}">@csrf
                <label>{{ __('journal.backup_reference') }}<input name="backup_reference" maxlength="255" required value="{{ old('backup_reference',$journal->setting('backup_reference')) }}"></label>
                <label class="journal-check"><input type="checkbox" name="backup_confirmed" value="1" required> {{ __('journal.backup_confirmation') }}</label>
                <button class="secondary-action">{{ __('journal.confirm_backup') }}</button>
            </form>
            <p>{{ __('journal.notification_outbox') }}: {{ __('journal.outbox_summary',['pending'=>$outboxCounts['pending']??0,'sent'=>$outboxCounts['sent']??0,'failed'=>$outboxCounts['failed']??0]) }}</p>
        </section>
    </div>

    <section class="data-card journal-launch-card">
        @if(!$journal->isPubliclyLaunched())
            <h2>{{ __('journal.launch_locked') }}</h2><p>{{ __('journal.launch_locked_help') }}</p>
            <form method="post" action="{{ route('journal.control.operations.public-launch.store',['locale'=>app()->getLocale()]) }}">@csrf<button class="primary-action" @disabled(collect($checks)->contains(fn($check)=>!$check['passed']))>{{ __('journal.authorize_public_launch') }}</button></form>
        @else
            <h2>{{ __('journal.launch_live') }}</h2><p>{{ __('journal.launch_live_help') }}</p>
            <form class="journal-stack-form" method="post" action="{{ route('journal.control.operations.public-launch.destroy',['locale'=>app()->getLocale()]) }}">@csrf @method('delete')<textarea name="reason" required maxlength="1000" placeholder="{{ __('journal.reason_required') }}"></textarea><button class="secondary-action">{{ __('journal.suspend_public_access') }}</button></form>
        @endif
    </section>
</div>
@endsection
