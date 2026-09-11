<?php
declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\ProCertificate;
use App\Services\InstitutionalAccess;
use App\Services\ProCertificateCatalog;
use App\Services\ProCertificateImage;
use App\Services\ProCertificatePdf;
use App\Services\ProCertificateRegistry;
use App\Services\ProCertificateWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

final class ProCertificateController extends Controller
{
    private function registry(): ProCertificateRegistry { return app(ProCertificateRegistry::class); }

    private function headers(): array
    {
        return ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive', 'Referrer-Policy' => 'no-referrer'];
    }

    private function page(string $view, array $data = []): Response
    {
        return response()->view($view, $data, 200, $this->headers());
    }

    private function organizations(Request $request)
    {
        $this->registry()->requirePermission($request->user(), 'certificates.view');
        return app(InstitutionalAccess::class)->constrainOrganizations(Organization::query(), $request->user())
            ->orderBy('display_name')->get(['id', 'display_name', 'status']);
    }

    private function record(Request $request): ProCertificate
    {
        return $this->registry()->query($request->user())->with(['organization', 'creator', 'approver', 'issuer'])
            ->findOrFail((int) $request->route('certificate'));
    }

    public function index(Request $request, ProCertificateWorkspace $workspace): Response
    {
        $organizations = $this->organizations($request);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'organization_id' => ['nullable', 'integer', Rule::in($organizations->pluck('id')->all())],
            'status' => ['nullable', Rule::in(['draft', 'review', 'approved', 'issued', 'expired', 'revoked'])],
            'scope' => ['nullable', Rule::in(['current', 'archive', 'all'])],
        ]);
        $base = $this->registry()->query($request->user());
        $workspaceRecords = $workspace->partition((clone $base)->latest('id')->get([
            'id', 'organization_id', 'catalog_type_id', 'certificate_type', 'program_title', 'recipient_name',
            'public_name', 'achievement_date', 'status', 'expires_on',
        ]));
        $currentRecords = $workspaceRecords['current'];
        $archiveRecords = $workspaceRecords['archive'];
        $stats = [
            'total' => $currentRecords->count(),
            'review' => $currentRecords->where('status', 'review')->count(),
            'issued' => $currentRecords->filter(static fn (ProCertificate $record): bool =>
                $record->status === 'issued'
                && ($record->expires_on === null || $record->expires_on->toDateString() >= today('UTC')->toDateString())
            )->count(),
            'archived' => $archiveRecords->count(),
        ];
        $scope = $filters['scope'] ?? 'current';
        $query = $base->with('organization')
            ->when($filters['q'] ?? null, fn ($query, $value) => $query->where(fn ($sub) => $sub
                ->where('recipient_name', 'like', '%'.$value.'%')->orWhere('public_name', 'like', '%'.$value.'%')
                ->orWhere('program_title', 'like', '%'.$value.'%')->orWhere('certificate_number', 'like', '%'.$value.'%')
                ->orWhere('record_uuid', $value)))
            ->when($filters['organization_id'] ?? null, fn ($query, $value) => $query->where('organization_id', $value));
        if ($scope !== 'all') {
            $query->whereIn('id', $workspaceRecords[$scope]->pluck('id'));
        }
        $state = $filters['status'] ?? null;
        if ($state === 'expired') {
            $query->where('status', 'issued')->whereDate('expires_on', '<', today('UTC'));
        } elseif ($state === 'issued') {
            $query->where('status', 'issued')->where(fn ($sub) => $sub->whereNull('expires_on')->orWhereDate('expires_on', '>=', today('UTC')));
        } elseif ($state) {
            $query->where('status', $state);
        }
        $certificates = $query->latest('id')->paginate(20)->withQueryString();
        $checks = $certificates->getCollection()->mapWithKeys(fn ($record) => [$record->id => $this->registry()->verify($record)]);
        $statuses = $certificates->getCollection()->mapWithKeys(fn ($record) => [$record->id => $this->registry()->effectiveStatus($record)]);
        return $this->page('control.pro_certificates.index', compact(
            'certificates',
            'organizations',
            'stats',
            'checks',
            'statuses',
            'scope',
        ));
    }

    public function create(Request $request): Response
    {
        $this->registry()->requirePermission($request->user(), 'certificates.manage');
        $choice=$request->validate(['type_id'=>['nullable','integer','min:1'],'language'=>['nullable','in:ar,en,fr']]);
        $catalog=app(ProCertificateCatalog::class);
        $types=$catalog->query($request->user())->where('active',true)->with('organization')->orderBy('name_'.app()->getLocale())->get()
            ->filter(fn($type)=>$catalog->verify($type)&&$type->organization?->status==='active');
        $selectedType=null;$defaults=['language'=>app()->getLocale(),'certificate_type'=>'completion'];
        if(!empty($choice['type_id'])){
            $selectedType=$types->firstWhere('id',(int)$choice['type_id']);abort_unless($selectedType,404);
            $defaults=$catalog->defaults($request->user(),(int)$selectedType->id,$choice['language']??app()->getLocale());
        }
        return $this->page('control.pro_certificates.form', [
            'certificate'=>new ProCertificate($defaults), 'types'=>$types, 'selectedType'=>$selectedType,
            'organizations'=>$this->organizations($request)->where('status','active'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['catalog_type_id'=>['required','integer','min:1']]);
        $record = $this->registry()->create($request->user(), $this->profile($request));
        return redirect()->route('certificates.show', ['locale' => app()->getLocale(), 'certificate' => $record->id])
            ->with('success', __('certificates.saved'));
    }

    public function edit(Request $request): Response
    {
        $this->registry()->requirePermission($request->user(), 'certificates.manage');
        $certificate = $this->record($request);
        abort_unless($this->registry()->verify($certificate), 409, __('certificates.errors.integrity'));
        abort_unless($certificate->status === 'draft', 409, __('certificates.errors.transition'));
        return $this->page('control.pro_certificates.form', ['certificate' => $certificate, 'organizations' => $this->organizations($request)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $record = $this->record($request);
        $version = $request->validate(['lock_version' => ['required', 'integer', 'min:1']]);
        $this->registry()->update($request->user(), (int) $record->id, (int) $version['lock_version'], $this->profile($request));
        return redirect()->route('certificates.show', ['locale' => app()->getLocale(), 'certificate' => $record->id])
            ->with('success', __('certificates.saved'));
    }

    public function show(Request $request): Response
    {
        $certificate = $this->record($request);
        $integrity = $this->registry()->verify($certificate);
        $status = $integrity ? $this->registry()->effectiveStatus($certificate) : 'unavailable';
        $readiness = $integrity && in_array($certificate->status, ['draft', 'review', 'approved'], true)
            ? $this->registry()->issuanceReadiness($request->user(), $certificate)
            : null;
        $history = AuditLog::query()->where('auditable_type', $certificate->getMorphClass())->where('auditable_id', $certificate->id)
            ->with('actor:id,name')->orderByDesc('sequence_number')->paginate(15);
        $reasons = [];
        foreach ($history as $event) {
            try { $reasons[$event->id] = isset($event->metadata['reason_encrypted']) ? Crypt::decryptString($event->metadata['reason_encrypted']) : null; }
            catch (Throwable) { $reasons[$event->id] = __('certificates.unreadable'); }
        }
        return $this->page('control.pro_certificates.show', compact('certificate', 'integrity', 'status', 'readiness', 'history', 'reasons'));
    }

    public function transition(Request $request): RedirectResponse
    {
        $record = $this->record($request);
        $action = (string) $request->route('action');
        $data = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
            'reason' => [in_array($action, ['return', 'revoke'], true) ? 'required' : 'nullable', 'string', 'max:1500'],
            'confirm_issue' => [$action === 'issue' ? 'accepted' : 'nullable'],
            'confirm_revoke' => [$action === 'revoke' ? 'accepted' : 'nullable'],
        ], ['confirm_issue.accepted' => __('certificates.errors.confirmation'), 'confirm_revoke.accepted' => __('certificates.errors.confirmation'),
            'reason.required' => __('certificates.errors.reason')]);
        $this->registry()->transition($request->user(), (int) $record->id, (int) $data['lock_version'], $action, $data);
        return redirect()->route('certificates.show', ['locale' => app()->getLocale(), 'certificate' => $record->id])
            ->with('success', __('certificates.action_saved'));
    }

    public function download(Request $request): BinaryFileResponse
    {
        $certificate = $this->record($request);
        abort_unless($this->registry()->verify($certificate), 409, __('certificates.errors.integrity'));
        $path = $this->registry()->downloadPath($certificate);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $certificate->certificate_number).'.pdf';
        return response()->download($path, $filename, $this->headers() + ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function downloadImage(Request $request, ProCertificateImage $images): Response
    {
        $certificate = $this->record($request);
        abort_unless($this->registry()->verify($certificate), 409, __('certificates.errors.integrity'));
        abort_unless(in_array($certificate->status, ['issued', 'revoked'], true), 409, __('certificates.errors.transition'));

        $variant = (string) $request->route('variant');
        $path = $this->registry()->downloadPath($certificate);
        $image = $images->render($path, $variant);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $certificate->certificate_number)
            .'-'.($variant === 'print' ? '300dpi' : 'share').'.'.$image['extension'];

        return response($image['bytes'], 200, $this->headers() + [
            'Content-Type' => $image['mime'],
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($image['bytes']),
            'X-Content-Type-Options' => 'nosniff',
            'X-IUOAMC-Source-PDF-SHA256' => (string) $certificate->pdf_sha256,
        ]);
    }

    public function preview(Request $request): Response
    {
        $certificate = $this->record($request);
        $payload = $this->registry()->previewPayload($request->user(), $certificate);
        $bytes = app(ProCertificatePdf::class)->render($payload);
        return response($bytes, 200, $this->headers() + ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="draft-certificate-'.$certificate->id.'.pdf"', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function profile(Request $request): array
    {
        return $request->only(['organization_id', 'recipient_name', 'public_name', 'recipient_email', 'program_title', 'certificate_title',
            'certificate_type', 'credential_basis', 'accreditation_reference', 'accreditation_date', 'language',
            'achievement_date', 'expires_on', 'statement', 'signatory_name', 'signatory_title', 'catalog_type_id', 'specialization']);
    }
}
