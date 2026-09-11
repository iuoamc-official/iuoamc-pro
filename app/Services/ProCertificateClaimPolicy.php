<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class ProCertificateClaimPolicy
{
    public const PROGRAMME_COMPLETION = 'programme_completion';

    public const PROFESSIONAL_ACCREDITATION = 'professional_accreditation';

    /** @param array<string, mixed> $values */
    public function enforce(array $values): void
    {
        $basis = $values['credential_basis'] ?? null;
        if ($basis === null) {
            return;
        }

        if ($basis === self::PROFESSIONAL_ACCREDITATION) {
            if (($values['accreditation_reference'] ?? null) === null
                || ($values['accreditation_date'] ?? null) === null) {
                throw ValidationException::withMessages([
                    'accreditation_reference' => trans('certificates.errors.accreditation_evidence_required'),
                ]);
            }

            return;
        }

        if (($values['accreditation_reference'] ?? null) !== null
            || ($values['accreditation_date'] ?? null) !== null) {
            throw ValidationException::withMessages([
                'credential_basis' => trans('certificates.errors.completion_has_no_accreditation_evidence'),
            ]);
        }

        $claim = implode("\n", [
            (string) ($values['certificate_title'] ?? ''),
            (string) ($values['statement'] ?? ''),
        ]);

        if ($this->claimsProfessionalAccreditation($claim)) {
            throw ValidationException::withMessages([
                'statement' => trans('certificates.errors.completion_claims_accreditation'),
            ]);
        }
    }

    private function claimsProfessionalAccreditation(string $claim): bool
    {
        $patterns = [
            '/(?:محك(?:م|ّم)|حكم|مقيّم|مقوم)[^\n]{0,50}(?:معتمد|اعتماد)/u',
            '/(?:معتمد|اعتماد)[^\n]{0,50}(?:محك(?:م|ّم)|حكم|مقيّم|مقوم)/u',
            '/(?:certified|accredited)[^\n]{0,60}(?:judge|arbitrator|adjudicator|assessor)/iu',
            '/(?:judge|arbitrator|adjudicator|assessor)[^\n]{0,60}(?:certified|accredited)/iu',
            '/(?:juge|arbitre|évaluateur)[^\n]{0,60}(?:certifié|accrédité)/iu',
            '/(?:certifié|accrédité)[^\n]{0,60}(?:juge|arbitre|évaluateur)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $claim) === 1) {
                return true;
            }
        }

        return false;
    }
}
