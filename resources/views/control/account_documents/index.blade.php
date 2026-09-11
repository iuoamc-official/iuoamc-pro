@extends('layouts.control')
@section('title', __('account.admin_documents'))
@section('content')
<section class="page-heading"><div><span class="eyebrow">IUOAMC / ACCOUNT DOCUMENTS</span><h1>{{ __('account.admin_documents') }}</h1><p>{{ __('account.admin_documents_intro') }}</p></div>@if(auth()->user()->canDo('account-documents.manage'))<a class="primary-action" href="{{ route('account-documents.create',['locale'=>app()->getLocale()]) }}">{{ __('account.issue_document') }}</a>@endif</section>
<section class="table-card"><div class="table-wrap"><table class="data-table"><thead><tr><th>{{ __('account.category') }}</th><th>{{ __('account.title') }}</th><th>{{ __('account.recipient_email') }}</th><th>{{ __('account.reference') }}</th><th>{{ __('account.issued_at') }}</th><th>{{ __('account.action') }}</th></tr></thead><tbody>
@forelse($documents as $document)<tr><td>{{ __('account.category_'.$document->category) }}</td><td><bdi>{{ $document->title }}</bdi></td><td><bdi dir="ltr">{{ $document->recipient_email }}</bdi></td><td><bdi dir="ltr">{{ $document->reference ?: '—' }}</bdi></td><td>{{ $document->issued_at->format('Y-m-d H:i') }}</td><td>@if($checks[$document->id])<a class="table-action" href="{{ route('account-documents.download',['locale'=>app()->getLocale(),'document'=>$document->id]) }}">{{ __('account.download_pdf') }}</a>@else<span>{{ __('account.integrity_blocked') }}</span>@endif</td></tr>
@empty<tr><td colspan="6">{{ __('account.no_admin_documents') }}</td></tr>@endforelse
</tbody></table></div>{{ $documents->links() }}</section>
@endsection
