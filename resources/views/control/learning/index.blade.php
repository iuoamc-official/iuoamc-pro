@extends('layouts.control')
@section('title', __('learning.title'))
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/iuoamc-learning-1.0.0.css') }}?v={{ filemtime(public_path('assets/css/iuoamc-learning-1.0.0.css')) }}">@endpush
@section('content')
<div class="learning"><header class="learning-hero"><span class="learning-kicker">IUOAMC · {{ __('learning.student_portal') }}</span><h1>{{ __('learning.title') }}</h1><p>{{ __('learning.admin_intro') }}</p><a class="learning-button" href="{{ route('learning.access.index',['locale'=>app()->getLocale()]) }}">{{ __('learning.student_form_link') }} ↗</a></header>
@if(session('learning_invitation'))<section class="learning-card learning-issued-code" role="status"><h2>{{ __('learning.code_ready') }}</h2><p><bdi>{{ session('learning_invitation.email') }}</bdi></p><label for="issued-code">{{ __('learning.access_code') }}</label><input id="issued-code" class="learning-code-input" readonly dir="ltr" value="{{ session('learning_invitation.code') }}"><p>{{ __('learning.copy_code_hint') }}</p><p><bdi>{{ route('learning.access.index',['locale'=>app()->getLocale()]) }}</bdi></p></section>@endif
<div class="learning-grid">
<form class="learning-card" method="post" action="{{ route('learning.control.invitations.store',['locale'=>app()->getLocale()]) }}">@csrf<h2>{{ __('learning.issue_code') }}</h2><p class="learning-muted">{{ __('learning.issue_code_hint') }}</p><label for="invitation-email">{{ __('learning.student_email') }}</label><input id="invitation-email" name="email" type="email" required dir="ltr" value="{{ old('email') }}"><p><button>{{ __('learning.issue_code') }}</button></p></form>
<form class="learning-card" method="post" action="{{ route('learning.control.enroll',['locale'=>app()->getLocale()]) }}">@csrf<h2>{{ __('learning.enroll_existing') }}</h2><p class="learning-muted">{{ __('learning.active_verified_required') }}</p><label for="student-email">{{ __('learning.student_email') }}</label><input id="student-email" name="email" type="email" required dir="ltr"><p><button>{{ __('learning.enroll_student') }}</button></p></form>
</div>
<h2>{{ __('learning.issued_codes') }}</h2>
@forelse($invitations as $invitation)<article class="learning-card learning-row"><div><bdi>{{ $invitation->email }}</bdi><p>{{ $invitation->used_at ? __('learning.code_used') : ($invitation->revoked_at ? __('learning.code_cancelled') : (now()->gte($invitation->expires_at) ? __('learning.code_expired') : __('learning.code_active'))) }} · {{ __('learning.code_expiry') }}: <bdi>{{ $invitation->expires_at }} UTC</bdi></p>@if($invitation->used_at)<p>{{ $invitation->student_name }} · <bdi>{{ $invitation->student_phone }}</bdi> · {{ $invitation->student_country }}</p>@endif</div>
@if(!$invitation->used_at && !$invitation->revoked_at && now()->lt($invitation->expires_at))<form method="post" action="{{ route('learning.control.invitations.destroy',['locale'=>app()->getLocale(),'invitation'=>$invitation->id]) }}">@csrf @method('DELETE')<button class="secondary">{{ __('learning.revoke_code') }}</button></form>@endif</article>
@empty<p class="learning-muted">{{ __('learning.no_codes') }}</p>@endforelse
{{ $invitations->links() }}
<h2>{{ __('learning.enrolled_students') }}</h2>
@foreach($enrollments as $record)<article class="learning-card learning-row"><div><h3>{{ $record->name }}</h3><bdi>{{ $record->email }}</bdi><p>{{ $record->reviewed_at ? __('learning.reviewed') : __('learning.pending_review') }}@if($record->final_score!==null) · {{ $record->final_score }} / 100 @endif</p></div><a class="learning-button" href="{{ route('learning.control.show',['locale'=>app()->getLocale(),'enrollment'=>$record->id]) }}">{{ __('learning.review') }}</a></article>@endforeach
{{ $enrollments->links() }}</div>
@endsection
