<?php
declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GovernanceHubController extends Controller
{
    public function index(Request $request): View
    {
        $allowed = collect(['organizations.view', 'core.users.view', 'core.roles.view', 'core.audit.view'])
            ->contains(fn ($permission) => $request->user()->canDo($permission));
        abort_unless($allowed, 403);
        return view('control.navigation.governance');
    }
}
