<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\AccountDocument;
use App\Models\Organization;
use App\Services\AccountDocumentRegistry;
use App\Services\InstitutionalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AccountDocumentController extends Controller
{
    public function index(Request $request, AccountDocumentRegistry $registry): View
    {
        $documents = $registry->query($request->user())->with(['organization', 'issuer'])
            ->latest('issued_at')->paginate(25);
        $checks = $documents->getCollection()->mapWithKeys(fn (AccountDocument $document): array => [
            $document->id => $registry->verify($document),
        ]);

        return view('control.account_documents.index', compact('documents', 'checks'));
    }

    public function create(Request $request): View
    {
        $organizations = app(InstitutionalAccess::class)->constrainOrganizations(
            Organization::query()->where('status', 'active'), $request->user()
        )->orderBy('display_name')->get(['id', 'display_name']);

        return view('control.account_documents.create', compact('organizations'));
    }

    public function store(Request $request, AccountDocumentRegistry $registry): RedirectResponse
    {
        $organizationIds = app(InstitutionalAccess::class)->organizationIds($request->user());
        if ($request->filled('currency')) {
            $request->merge(['currency' => strtoupper(trim((string) $request->input('currency')))]);
        }
        $data = $request->validate([
            'organization_id' => ['required', 'integer', Rule::in($organizationIds)],
            'category' => ['required', Rule::in(['document', 'invoice', 'receipt'])],
            'title' => ['required', 'string', 'max:180'],
            'reference' => ['nullable', 'string', 'max:100'],
            'recipient_email' => ['required', 'email:rfc', 'max:254'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['nullable', 'regex:/^[A-Z]{3}$/'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:15360'],
        ]);
        $registry->issue($request->user(), $data, $request->file('pdf'));

        return redirect()->route('account-documents.index', ['locale' => app()->getLocale()])
            ->with('success', __('account.document_issued'));
    }

    public function download(Request $request, AccountDocumentRegistry $registry): BinaryFileResponse
    {
        $document = $registry->query($request->user())->findOrFail((int) $request->route('document'));
        $path = $registry->downloadPath($document);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', $document->reference ?: $document->title).'.pdf';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
