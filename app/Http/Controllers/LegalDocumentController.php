<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MembershipApplicationPolicy;
use Illuminate\View\View;

final class LegalDocumentController extends Controller
{
    public function membershipTerms(MembershipApplicationPolicy $policy): View
    {
        return $this->render('membership_terms', MembershipApplicationPolicy::TERMS_VERSION, $policy);
    }

    public function membershipPrivacy(MembershipApplicationPolicy $policy): View
    {
        return $this->render('membership_privacy', MembershipApplicationPolicy::PRIVACY_VERSION, $policy);
    }

    private function render(
        string $document,
        string $version,
        MembershipApplicationPolicy $policy
    ): View {
        return view('legal.show', [
            'document' => $document,
            'version' => $version,
            'termFees' => $policy->termFees(),
            'feeAllocation' => $policy->feeAllocation(),
        ]);
    }
}
