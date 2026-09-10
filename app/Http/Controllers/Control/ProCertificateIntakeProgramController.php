<?php
declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\{ProCertificateIntakeProgram, ProCertificateIntakeResponse};
use App\Services\ProCertificateIntakeProgramRegistry;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Validator;

final class ProCertificateIntakeProgramController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache',
        'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow, noarchive'];
    private function registry(): ProCertificateIntakeProgramRegistry { return app(ProCertificateIntakeProgramRegistry::class); }

    public function index(Request $request): Response
    {
        $programs = $this->registry()->query($request->user())->with('organization')->withCount('responses')->orderBy('id')->paginate(20);
        return $this->page('programs.index', compact('programs'));
    }
    public function show(Request $request): Response
    {
        $program = $this->program($request);
        $responses = $this->registry()->responses($request->user(), (int) $program->id)->with('matchedIntake')->orderByDesc('id')->paginate(30);
        $intakes = $this->registry()->candidates($request->user(), $program);
        $sharedUrl = $this->registry()->sharedUrl($program, $this->locale($request));
        return $this->page('programs.show', compact('program', 'responses', 'intakes', 'sharedUrl'));
    }
    public function toggle(Request $request): RedirectResponse
    {
        $data = Validator::make($request->only('status'), ['status' => ['required', 'in:open,closed']])->validate();
        $program = $this->registry()->setStatus($request->user(), (int) $request->route('program'), $data['status']);
        return $this->saved('/'.$this->locale($request).'/control/certificates/data-confirmations/programs/'.$program->id, 'program_'.$program->status);
    }
    public function response(Request $request): Response
    {
        $responseRecord = $this->responseRecord($request);
        $program = $responseRecord->program;
        $candidates = $this->registry()->candidates($request->user(), $program);
        return $this->page('responses.show', compact('responseRecord', 'program', 'candidates'));
    }
    public function review(Request $request): RedirectResponse
    {
        $data = Validator::make($request->only('matched_intake_id'), ['matched_intake_id' => ['required', 'integer', 'min:1']])->validate();
        $record = $this->registry()->review($request->user(), (int) $request->route('response'), (int) $data['matched_intake_id']);
        return $this->saved('/'.$this->locale($request).'/control/certificates/data-confirmations/responses/'.$record->id, 'response_reviewed');
    }
    public function dismiss(Request $request): RedirectResponse
    {
        $record = $this->registry()->dismiss($request->user(), (int) $request->route('response'));
        return $this->saved('/'.$this->locale($request).'/control/certificates/data-confirmations/responses/'.$record->id, 'response_dismissed');
    }
    private function program(Request $request): ProCertificateIntakeProgram
    {
        $program = $this->registry()->query($request->user())->with('organization')->findOrFail((int) $request->route('program'));
        abort_unless($this->registry()->verifyProgram($program), 409, __('certificate_intake.errors.integrity'));
        return $program;
    }
    private function responseRecord(Request $request): ProCertificateIntakeResponse
    {
        $response = $this->registry()->responseQuery($request->user())->with(['program', 'matchedIntake'])->findOrFail((int) $request->route('response'));
        abort_unless($this->registry()->verifyResponse($response), 409, __('certificate_intake.errors.integrity'));
        return $response;
    }
    private function page(string $view, array $data): Response { return response()->view('control.pro_certificates.intakes.'.$view, $data, 200, self::HEADERS); }
    private function saved(string $path, string $action): RedirectResponse { return redirect($path)->withHeaders(self::HEADERS)->with('success', __('certificate_intake.saved.'.$action)); }
    private function locale(Request $request): string
    {
        $locale = (string) $request->route('locale');
        return in_array($locale, ['ar', 'en', 'fr'], true) ? $locale : 'ar';
    }
}
