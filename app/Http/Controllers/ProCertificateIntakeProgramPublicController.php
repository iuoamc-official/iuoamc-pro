<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProCertificateIntakeProgram;
use App\Services\ProCertificateIntakeProgramRegistry;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\{MessageBag, ViewErrorBag};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ProCertificateIntakeProgramPublicController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache',
        'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow, noarchive'];
    private function registry(): ProCertificateIntakeProgramRegistry { return app(ProCertificateIntakeProgramRegistry::class); }

    public function form(Request $request): Response
    {
        $program = $this->registry()->publicProgram((string) $request->route('code'));
        if ($program === null) { return $this->unavailable(); }
        $values = array_fill_keys(ProCertificateIntakeProgramRegistry::RESPONSE_FIELDS, '');
        return $this->page($program, $values, $this->issueNonce($request, $program));
    }

    public function submit(Request $request): Response|RedirectResponse
    {
        $program = $this->registry()->publicProgram((string) $request->route('code'));
        if ($program === null) { return $this->unavailable(); }
        $values = $request->only(ProCertificateIntakeProgramRegistry::RESPONSE_FIELDS);
        $nonce = $request->input('nonce');
        $known = $request->session()->get($this->nonceKey($program), []);
        if (!is_string($nonce) || preg_match('/\A[a-f0-9]{64}\z/D', $nonce) !== 1
            || !is_array($known) || !in_array(hash('sha256', $nonce), $known, true)) {
            return $this->page($program, $this->safeValues($values), $this->issueNonce($request, $program),
                new MessageBag(['nonce' => __('certificate_intake.errors.nonce')]), 422);
        }
        $validator = $this->registry()->responseValidator($values);
        if ($validator->fails()) { return $this->page($program, $this->safeValues($values), $nonce, $validator->errors(), 422); }
        try {
            $this->registry()->submit($program->code, $nonce, $validator->validated());
        } catch (ValidationException $error) {
            return $this->page($program, $this->safeValues($values), $nonce, new MessageBag($error->errors()), 422);
        } catch (HttpExceptionInterface $error) {
            if ($error->getStatusCode() === 404) { return $this->unavailable(); }
            if ($error->getStatusCode() === 409) {
                return $this->page($program, $this->safeValues($values), $this->issueNonce($request, $program),
                    new MessageBag(['nonce' => __('certificate_intake.errors.nonce')]), 422);
            }
            throw $error;
        }
        $locale = (string) $request->route('locale');
        if (!in_array($locale, ['ar', 'en', 'fr'], true)) { $locale = 'ar'; }
        return redirect('/'.$locale.'/certificate-data/received', 303)->withHeaders(self::HEADERS);
    }

    private function issueNonce(Request $request, ProCertificateIntakeProgram $program): string
    {
        $nonce = bin2hex(random_bytes(32));
        $known = $request->session()->get($this->nonceKey($program), []);
        $known = is_array($known) ? array_values(array_filter($known, static fn ($value): bool => is_string($value))) : [];
        $known[] = hash('sha256', $nonce);
        // Only opaque nonce hashes are kept in session; never names or submitted contact data.
        $request->session()->put($this->nonceKey($program), array_slice($known, -8));
        return $nonce;
    }

    private function nonceKey(ProCertificateIntakeProgram $program): string { return 'certificate_intake_program_nonce.'.$program->id; }
    private function unavailable(): Response { return response()->view('certificate_data.unavailable', [], 404, self::HEADERS); }
    private function page(ProCertificateIntakeProgram $program, array $values, string $nonce, ?MessageBag $messages = null, int $status = 200): Response
    {
        $errors = (new ViewErrorBag())->put('default', $messages ?? new MessageBag());
        return response()->view('certificate_data.program', compact('program', 'values', 'nonce', 'errors'), $status, self::HEADERS);
    }
    private function safeValues(array $values): array
    {
        $safe = [];
        foreach (ProCertificateIntakeProgramRegistry::RESPONSE_FIELDS as $field) {
            $value = $values[$field] ?? '';
            $safe[$field] = is_scalar($value) ? mb_substr((string) $value, 0, 2000) : '';
        }
        return $safe;
    }
}
