<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
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

        return view('control.dashboard', compact('stats', 'modules'));
    }
}
