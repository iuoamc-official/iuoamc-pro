<?php
declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\InstitutionalAccess;
use App\Services\ProCertificateRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ProMasterTemplateController extends Controller
{
    private function issuer(Request $request): Organization
    {
        abort_unless($request->user() !== null, 403);
        app(ProCertificateRegistry::class)->requirePermission($request->user(), 'certificates.view');
        $candidates = Organization::query()
            ->whereRaw('UPPER(TRIM(jurisdiction)) = ?', ['GB'])
            ->whereRaw('UPPER(TRIM(registration_number)) = ?', ['16846998'])->get();
        abort_unless($candidates->count() === 1, 409, __('master_certificates.issuer_unavailable'));
        $issuer = $candidates->first();
        $name = strtoupper((string) preg_replace('/\s+/u', ' ', trim((string) $issuer->legal_name)));
        abort_unless($issuer->status === 'active'
            && $name === 'INTERNATIONAL CULINARY & GASTRONOMY ARBITRATION LTD',
            409, __('master_certificates.issuer_unavailable'));
        app(InstitutionalAccess::class)->authorizeOrganization($request->user(), $issuer);
        return $issuer;
    }

    private function headers(): array
    {
        return ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'X-Content-Type-Options' => 'nosniff'];
    }

    public function index(Request $request): Response
    {
        $issuer = $this->issuer($request);
        return response()->view('control.pro_certificates.master.index', compact('issuer'), 200, $this->headers());
    }

    public function preview(Request $request): BinaryFileResponse
    {
        $this->issuer($request);
        $model = (string) $request->route('model');
        abort_unless(in_array($model, ['tasting', 'judging'], true), 404);
        $directory = realpath(resource_path('certificates/previews'));
        $candidate = resource_path('certificates/previews/'.$model.'.pdf');
        $path = realpath($candidate);
        abort_unless($directory !== false && $path !== false && ! is_link($candidate)
            && dirname($path) === $directory && is_file($path) && is_readable($path)
            && filesize($path) > 100 && filesize($path) <= 8388608,
            503, __('master_certificates.preview_unavailable'));
        return response()->file($path, array_merge($this->headers(), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="IUOAMC-Master-'.$model.'-NOT-ISSUED.pdf"',
        ]));
    }
}
