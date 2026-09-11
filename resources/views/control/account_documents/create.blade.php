@extends('layouts.control')
@section('title', __('account.issue_document'))
@section('content')
<section class="page-heading"><div><span class="eyebrow">IUOAMC / ACCOUNT DOCUMENTS</span><h1>{{ __('account.issue_document') }}</h1><p>{{ __('account.issue_document_intro') }}</p></div></section>
@if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif
<form class="institutional-form" method="post" enctype="multipart/form-data" action="{{ route('account-documents.store',['locale'=>app()->getLocale()]) }}">@csrf
<section class="form-card"><header><span class="form-step">01</span><div><h2>{{ __('account.document_details') }}</h2></div></header><div class="form-grid two-columns">
<label class="field"><span>{{ __('account.organization') }} *</span><select name="organization_id" required><option value="">{{ __('account.choose') }}</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected(old('organization_id')==$organization->id)>{{ $organization->display_name }}</option>@endforeach</select></label>
<label class="field"><span>{{ __('account.category') }} *</span><select name="category" required>@foreach(['document','invoice','receipt'] as $category)<option value="{{ $category }}" @selected(old('category')===$category)>{{ __('account.category_'.$category) }}</option>@endforeach</select></label>
<label class="field"><span>{{ __('account.title') }} *</span><input name="title" required maxlength="180" value="{{ old('title') }}"></label>
<label class="field"><span>{{ __('account.reference') }}</span><input name="reference" maxlength="100" value="{{ old('reference') }}"></label>
<label class="field"><span>{{ __('account.recipient_email') }} *</span><input type="email" name="recipient_email" required maxlength="254" dir="ltr" value="{{ old('recipient_email') }}"></label>
<label class="field"><span>{{ __('account.amount') }}</span><input type="number" name="amount" min="0" step="0.01" value="{{ old('amount') }}"></label>
<label class="field"><span>{{ __('account.currency') }}</span><input name="currency" maxlength="3" dir="ltr" placeholder="USD" value="{{ old('currency') }}"></label>
<label class="field"><span>{{ __('account.pdf_file') }} *</span><input type="file" name="pdf" accept="application/pdf" required></label>
</div></section><div class="form-footer"><a class="secondary-action" href="{{ route('account-documents.index',['locale'=>app()->getLocale()]) }}">{{ __('account.cancel') }}</a><button class="primary-action" type="submit">{{ __('account.issue_document') }}</button></div></form>
@endsection
