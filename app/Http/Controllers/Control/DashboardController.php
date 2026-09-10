<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\ProCertificate;
use App\Models\Role;
use App\Models\User;
use App\Services\ProCertificateRegistry;
use App\Services\ProCertificateWorkspace;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        ProCertificateRegistry $registry,
        ProCertificateWorkspace $workspace,
    ): View
    {
        $stats = [
            'organizations' => Organization::count(),
            'users' => User::count(),
            'roles' => Role::count(),
            'permissions' => Permission::count(),
            'audit_logs' => AuditLog::count(),
        ];

        $modules = [
            ['key' => 'certificates', 'icon' => 'certificate', 'phase' => 2],
            ['key' => 'memberships', 'icon' => 'members', 'phase' => 3],
            ['key' => 'accounting', 'icon' => 'finance', 'phase' => 4],
            ['key' => 'magazine', 'icon' => 'magazine', 'phase' => 5],
            ['key' => 'research', 'icon' => 'research', 'phase' => 5],
            ['key' => 'governance', 'icon' => 'governance', 'phase' => 1],
        ];

        $certificateOperations = null;
        if ($request->user()->canDo('certificates.view')) {
            $records = $workspace->partition($registry->query($request->user())->latest('id')->get());
            $current = $records['current'];
            $recent = $registry->query($request->user())
                ->with('organization:id,display_name')
                ->whereIn('id', $current->pluck('id')->take(5))
                ->latest('id')
                ->get();

            $certificateOperations = [
                'stats' => [
                    'current' => $current->count(),
                    'issued' => $current->filter(static fn (ProCertificate $certificate): bool =>
                        $certificate->status === 'issued'
                        && ($certificate->expires_on === null || $certificate->expires_on->toDateString() >= today('UTC')->toDateString())
                    )->count(),
                    'review' => $current->where('status', 'review')->count(),
                    'approved' => $current->where('status', 'approved')->count(),
                    'archive' => $records['archive']->count(),
                ],
                'recent' => $recent,
                'integrity' => $recent->mapWithKeys(
                    static fn (ProCertificate $certificate): array => [$certificate->id => $registry->verify($certificate)],
                ),
                'statuses' => $recent->mapWithKeys(
                    static fn (ProCertificate $certificate): array => [$certificate->id => $registry->effectiveStatus($certificate)],
                ),
            ];
        }

        return view('control.dashboard', compact('stats', 'modules', 'certificateOperations'));
    }
}
