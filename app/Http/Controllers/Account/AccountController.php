<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\AccountDocument;
use App\Models\Membership;
use App\Models\MembershipCredential;
use App\Models\ProCertificate;
use App\Services\AccountDocumentRegistry;
use App\Services\AccountRecordAccess;
use App\Services\AuditTrail;
use App\Services\MembershipCredentialPrintArchive;
use App\Services\MembershipCredentialRegistry;
use App\Services\MembershipRegistry;
use App\Services\ProCertificateRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AccountController extends Controller
{
    public function index(Request $request, AccountRecordAccess $access): View
    {
        $access->sync($request->user());
        $memberships = Membership::query()->with(['organization', 'periods', 'credentials'])
            ->whereIn('id', $access->ids($request->user(), AccountRecordAccess::MEMBERSHIP))
            ->latest('id')->get();
        $certificates = ProCertificate::query()->with('organization')
            ->whereIn('id', $access->ids($request->user(), AccountRecordAccess::PRO_CERTIFICATE))
            ->latest('issued_at')->get();
        $documents = AccountDocument::query()->with('organization')
            ->whereIn('id', $access->ids($request->user(), AccountRecordAccess::DOCUMENT))
            ->latest('issued_at')->get();
        $membershipChecks = $memberships->mapWithKeys(fn (Membership $record): array => [
            $record->id => app(MembershipRegistry::class)->verify($record),
        ]);
        $certificateChecks = $certificates->mapWithKeys(fn (ProCertificate $record): array => [
            $record->id => app(ProCertificateRegistry::class)->verify($record),
        ]);
        $documentChecks = $documents->mapWithKeys(fn (AccountDocument $record): array => [
            $record->id => app(AccountDocumentRegistry::class)->verify($record),
        ]);

        return view('account.dashboard', compact(
            'memberships', 'certificates', 'documents',
            'membershipChecks', 'certificateChecks', 'documentChecks'
        ));
    }

    public function refresh(Request $request, AccountRecordAccess $access): RedirectResponse
    {
        $access->sync($request->user(), true);

        return back()->with('success', __('account.records_refreshed'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'preferred_locale' => ['required', Rule::in(['ar', 'en', 'fr'])],
        ]);
        $user = $request->user();
        $old = ['name' => $user->name, 'preferred_locale' => $user->preferred_locale];
        $user->forceFill([
            'name' => trim($data['name']),
            'preferred_locale' => $data['preferred_locale'],
        ])->save();
        AuditTrail::record('account.profile_updated', $user, $old, [
            'name' => $user->name, 'preferred_locale' => $user->preferred_locale,
        ], [], (int) $user->id);

        return redirect()->route('account.dashboard', ['locale' => $data['preferred_locale']])
            ->with('success', __('account.profile_saved'));
    }

    public function downloadCertificate(Request $request, AccountRecordAccess $access): BinaryFileResponse
    {
        $id = (int) $request->route('certificate');
        abort_unless($access->owns($request->user(), AccountRecordAccess::PRO_CERTIFICATE, $id), 404);
        $certificate = ProCertificate::query()->findOrFail($id);
        $path = app(ProCertificateRegistry::class)->downloadPath($certificate);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $certificate->certificate_number).'.pdf';

        return response()->download($path, $filename, $this->downloadHeaders());
    }

    public function downloadMembershipCredential(Request $request, AccountRecordAccess $access): BinaryFileResponse
    {
        $membershipId = (int) $request->route('membership');
        abort_unless($access->owns($request->user(), AccountRecordAccess::MEMBERSHIP, $membershipId), 404);
        $credential = MembershipCredential::query()->where('membership_id', $membershipId)
            ->findOrFail((int) $request->route('credential'));
        $kind = (string) $request->route('kind');
        $path = app(MembershipCredentialRegistry::class)->downloadPath($credential, $kind);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $credential->membership_number)
            .'-v'.$credential->version.'-'.$kind.'.pdf';

        return response()->download($path, $filename, $this->downloadHeaders());
    }

    public function downloadMembershipCardPrintImages(Request $request, AccountRecordAccess $access): BinaryFileResponse
    {
        $membershipId = (int) $request->route('membership');
        abort_unless($access->owns($request->user(), AccountRecordAccess::MEMBERSHIP, $membershipId), 404);
        $credential = MembershipCredential::query()->where('membership_id', $membershipId)
            ->findOrFail((int) $request->route('credential'));
        $path = app(MembershipCredentialPrintArchive::class)->create($credential);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $credential->membership_number)
            .'-v'.$credential->version.'-card-print-images-300dpi.zip';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }

    public function downloadDocument(Request $request, AccountRecordAccess $access): BinaryFileResponse
    {
        $id = (int) $request->route('document');
        abort_unless($access->owns($request->user(), AccountRecordAccess::DOCUMENT, $id), 404);
        $document = AccountDocument::query()->findOrFail($id);
        $path = app(AccountDocumentRegistry::class)->downloadPath($document);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', $document->reference ?: $document->title).'.pdf';

        return response()->download($path, $filename, $this->downloadHeaders());
    }

    private function downloadHeaders(): array
    {
        return [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
