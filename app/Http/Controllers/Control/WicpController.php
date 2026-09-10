<?php
declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\WicpRecord;
use App\Services\InstitutionalAccess;
use App\Services\ProCertificateRegistry;
use App\Services\WicpRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class WicpController extends Controller
{
    private function registry(): WicpRegistry { return app(WicpRegistry::class); }

    private function page(string $view, array $data = []): Response
    {
        return response()->view('control.wicp.'.$view, $data, 200, [
            'Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive', 'Referrer-Policy' => 'no-referrer',
        ]);
    }

    private function record(Request $request): WicpRecord
    {
        return $this->registry()->query($request->user())->with(['organization', 'parent', 'certificate'])
            ->findOrFail((int) $request->route('record'));
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'],
            'kind' => ['nullable', 'in:program,certificate'], 'status' => ['nullable', 'in:registered,revoked']]);
        $records = $this->registry()->query($request->user())->with(['organization', 'parent'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($sub) => $sub
                ->where('reference', 'like', '%'.$term.'%')->orWhere('program_code', 'like', '%'.$term.'%')
                ->orWhere('program_title', 'like', '%'.$term.'%')->orWhere('source_number', 'like', '%'.$term.'%')))
            ->when($filters['kind'] ?? null, fn ($q, $value) => $q->where('kind', $value))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->latest('id')->paginate(20)->withQueryString();
        $checks = $records->getCollection()->mapWithKeys(fn ($record) => [$record->id => $this->registry()->verify($record)]);
        $statuses = $records->getCollection()->mapWithKeys(fn ($record) => [$record->id =>
            $checks[$record->id] ? $this->registry()->effectiveStatus($record) : 'integrity_failed']);
        return $this->page('index', compact('records', 'checks', 'statuses'));
    }

    public function createProgram(Request $request): Response
    {
        $this->registry()->requirePermission($request->user(), 'wicp.register');
        $authority = $this->registry()->authority();
        $organizations = app(InstitutionalAccess::class)->constrainOrganizations(Organization::query(), $request->user())
            ->where('status', 'active')->orderBy('display_name')->get(['id', 'display_name']);
        return $this->page('program', compact('organizations', 'authority'));
    }

    public function storeProgram(Request $request): RedirectResponse
    {
        $this->registry()->requirePermission($request->user(), 'wicp.register');
        $data = $request->validate([
            'organization_id' => ['required', 'integer', 'min:1'],
            'program_code' => ['required', 'string', 'max:80', 'regex:/\A[A-Z0-9][A-Z0-9._-]{0,79}\z/D'],
            'program_title' => ['required', 'string', 'max:255'],
            'program_version' => ['required', 'string', 'max:80', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]{0,79}\z/D'],
            'confirmation' => ['accepted'],
        ]);
        unset($data['confirmation']);
        $record = $this->registry()->registerProgram($request->user(), $data);
        return $this->saved($record);
    }

    public function createCertificate(Request $request): Response
    {
        $this->registry()->requirePermission($request->user(), 'wicp.register');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'certificate' => ['nullable', 'integer', 'min:1'],
            'program_q' => ['nullable', 'string', 'max:80']]);
        $registry = app(ProCertificateRegistry::class);
        $base = $registry->query($request->user())->where('status', 'issued')->with('organization');
        $selected = null;
        $programs = collect();
        $certificates = collect();
        if (!empty($filters['certificate'])) {
            $selected = (clone $base)->findOrFail((int) $filters['certificate']);
            abort_unless($registry->verify($selected) && $registry->effectiveStatus($selected) === 'issued', 409, __('wicp.errors.source'));
            $programs = $this->registry()->query($request->user())->where('kind', 'program')->where('status', 'registered')
                ->where('organization_id', $selected->organization_id)
                ->when($filters['program_q'] ?? null, fn ($q, $value) => $q->where(fn ($sub) => $sub
                    ->where('program_code', 'like', '%'.$value.'%')->orWhere('program_title', 'like', '%'.$value.'%')))
                ->latest('id')->limit(100)->get()->filter(fn ($program) => $this->registry()->verify($program));
        } else {
            $certificates = $base->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($sub) => $sub
                ->where('certificate_number', 'like', '%'.$term.'%')->orWhere('program_title', 'like', '%'.$term.'%')))
                ->where(fn ($q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', today('UTC')))
                ->latest('id')->limit(50)->get();
        }
        return $this->page('certificate', compact('selected', 'programs', 'certificates'));
    }

    public function storeCertificate(Request $request): RedirectResponse
    {
        $this->registry()->requirePermission($request->user(), 'wicp.register');
        $data = $request->validate(['certificate_id' => ['required', 'integer', 'min:1'],
            'parent_id' => ['required', 'integer', 'min:1'], 'confirmation' => ['accepted']]);
        return $this->saved($this->registry()->registerCertificate($request->user(), (int) $data['certificate_id'], (int) $data['parent_id']));
    }

    public function show(Request $request): Response
    {
        $record = $this->record($request);
        $integrity = $this->registry()->verify($record);
        $status = $integrity ? $this->registry()->effectiveStatus($record) : 'integrity_failed';
        return $this->page('show', compact('record', 'integrity', 'status'));
    }

    public function revoke(Request $request): RedirectResponse
    {
        $this->registry()->requirePermission($request->user(), 'wicp.revoke');
        $record = $this->record($request);
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1500'], 'confirmation' => ['accepted']]);
        $this->registry()->revoke($request->user(), (int) $record->id, (int) $data['lock_version'], $data['reason']);
        return redirect()->route('wicp.show', ['locale' => app()->getLocale(), 'record' => $record->id])->with('success', __('wicp.revoked_saved'));
    }

    private function saved(WicpRecord $record): RedirectResponse
    {
        return redirect()->route('wicp.show', ['locale' => app()->getLocale(), 'record' => $record->id])->with('success', __('wicp.saved'));
    }
}
