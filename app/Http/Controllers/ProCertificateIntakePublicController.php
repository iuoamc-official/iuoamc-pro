<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProCertificateIntake;
use App\Services\ProCertificateIntakeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ProCertificateIntakePublicController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache',
        'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow, noarchive'];

    private function registry(): ProCertificateIntakeRegistry { return app(ProCertificateIntakeRegistry::class); }

    public function form(Request $request): Response
    {
        $token = (string) $request->route('token', '');
        $intake = $this->registry()->publicRecord($token);
        if ($intake === null) { return $this->unavailable(); }
        $source = $intake->source_snapshot;
        // Only names are copied into a recipient form; no old contact or certificate authority fields.
        $values = ['name_ar' => $source['name_ar'] ?? '', 'name_en' => $source['name_en'] ?? '',
            'email' => '', 'phone' => '', 'country' => '', 'specialization' => '', 'notes' => '', 'confirmation' => false];
        return $this->page($intake, $token, $values);
    }

    public function submit(Request $request): Response|RedirectResponse
    {
        $token = (string) $request->route('token', '');
        $intake = $this->registry()->publicRecord($token);
        if ($intake === null) { return $this->unavailable(); }
        $values = $request->only(ProCertificateIntakeRegistry::RESPONSE_FIELDS);
        $validator = $this->registry()->responseValidator($values);
        if ($validator->fails()) { return $this->page($intake, $token, $this->safeValues($values), $validator->errors(), 422); }
        try {
            $this->registry()->submit($token, $validator->validated());
        } catch (ValidationException $error) {
            return $this->page($intake, $token, $this->safeValues($values), new MessageBag($error->errors()), 422);
        } catch (HttpExceptionInterface $error) {
            if ($error->getStatusCode() === 404) { return $this->unavailable(); }
            throw $error;
        }
        // Relative, tokenless, fixed destination: never redirect to a supplied return URL.
        $locale = (string) $request->route('locale');
        if (!in_array($locale, ['ar', 'en', 'fr'], true)) { $locale = 'ar'; }
        return redirect('/'.$locale.'/certificate-data/received', 303)->withHeaders(self::HEADERS);
    }

    public function received(): Response
    {
        return response()->view('certificate_data.received', [], 200, self::HEADERS);
    }

    public function unavailable(): Response
    {
        return response()->view('certificate_data.unavailable', [], 404, self::HEADERS);
    }

    private function page(ProCertificateIntake $intake, string $token, array $values, ?MessageBag $messages = null, int $status = 200): Response
    {
        $errors = (new ViewErrorBag())->put('default', $messages ?? new MessageBag());
        return response()->view('certificate_data.form', compact('intake', 'token', 'values', 'errors'), $status, self::HEADERS);
    }

    private function safeValues(array $values): array
    {
        $safe = [];
        foreach (ProCertificateIntakeRegistry::RESPONSE_FIELDS as $field) {
            $value = $values[$field] ?? '';
            $safe[$field] = is_scalar($value) ? mb_substr((string) $value, 0, 2000) : '';
        }
        return $safe;
    }
}
