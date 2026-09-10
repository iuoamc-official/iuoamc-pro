<?php
declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\ProCertificateIntake;
use App\Services\ProCertificateIntakeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ProCertificateIntakeController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache',
        'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow, noarchive'];

    private function registry(): ProCertificateIntakeRegistry { return app(ProCertificateIntakeRegistry::class); }

    public function index(Request $request): Response
    {
        $base = $this->registry()->query($request->user());
        $counts = (clone $base)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all();
        $intakes = $base->with('organization')->orderByDesc('id')->paginate(30);
        return response()->view('control.pro_certificates.intakes.index', compact('intakes', 'counts'), 200, self::HEADERS);
    }

    public function show(Request $request): Response
    {
        $intake = $this->record($request);
        abort_unless($this->registry()->verify($intake), 409, __('certificate_intake.errors.integrity'));
        $invitationUrl = $this->registry()->invitationUrl($intake, $this->locale($request));
        return response()->view('control.pro_certificates.intakes.show', compact('intake', 'invitationUrl'), 200, self::HEADERS);
    }

    public function invite(Request $request): RedirectResponse
    {
        $intake = $this->registry()->invite($request->user(), (int) $request->route('intake'));
        return $this->saved($request, $intake, 'invited');
    }

    public function review(Request $request): RedirectResponse
    {
        $intake = $this->registry()->review($request->user(), (int) $request->route('intake'));
        return $this->saved($request, $intake, 'reviewed');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $intake = $this->registry()->cancel($request->user(), (int) $request->route('intake'));
        return $this->saved($request, $intake, 'cancelled');
    }

    private function record(Request $request): ProCertificateIntake
    {
        return $this->registry()->query($request->user())->with(['organization', 'reviewer'])->findOrFail((int) $request->route('intake'));
    }

    private function saved(Request $request, ProCertificateIntake $intake, string $action): RedirectResponse
    {
        $path = '/'.$this->locale($request).'/control/certificates/data-confirmations/'.$intake->id;
        return redirect($path)->withHeaders(self::HEADERS)->with('success', __('certificate_intake.saved.'.$action));
    }

    private function locale(Request $request): string
    {
        $locale = (string) $request->route('locale');
        return in_array($locale, ['ar', 'en', 'fr'], true) ? $locale : 'ar';
    }
}
