<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\InstitutionalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        $users = app(InstitutionalAccess::class)
            ->constrainUsers(User::query(), $request->user())
            ->with(['roles:id,name,slug', 'organizations:id,display_name,code'])
            ->when($validated['q'] ?? null, function ($query, string $term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('control.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('control.users.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);
        $roleIds = array_map('intval', $validated['role_ids']);
        $organizationIds = array_map('intval', $validated['organization_ids']);
        app(InstitutionalAccess::class)->authorizeOrganizationIds($request->user(), $organizationIds);
        $this->authorizeRoleAssignment($request, $roleIds);
        $this->validatePrimaryOrganization($validated, $organizationIds);

        $user = DB::transaction(function () use ($request, $validated, $roleIds, $organizationIds): User {
            $user = User::create([
                'name' => trim($validated['name']),
                'email' => Str::lower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
                'status' => $validated['status'],
                'preferred_locale' => $validated['preferred_locale'],
                'must_change_password' => true,
                'created_by' => $request->user()->id,
            ]);

            $user->roles()->sync($roleIds);
            $user->organizations()->sync($this->organizationPivot(
                $organizationIds,
                (int) $validated['primary_organization_id']
            ));

            AuditTrail::record('user.created', $user, [], [
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'preferred_locale' => $user->preferred_locale,
                'role_ids' => $roleIds,
                'organization_ids' => $organizationIds,
            ]);

            return $user;
        });

        return redirect()
            ->route('users.edit', ['locale' => $request->route('locale'), 'user' => $user])
            ->with('success', trans('institutional.messages.user_created'));
    }

    public function edit(User $user): View
    {
        app(InstitutionalAccess::class)->authorizeUser(auth()->user(), $user);

        if ($user->hasRole('super-admin') && ! auth()->user()->hasRole('super-admin')) {
            abort(403);
        }

        $user->load(['roles:id', 'organizations:id']);

        return view('control.users.edit', ['user' => $user] + $this->formOptions());
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        app(InstitutionalAccess::class)->authorizeUser($request->user(), $user);

        if ($user->hasRole('super-admin') && ! $request->user()->hasRole('super-admin')) {
            abort(403);
        }

        $validated = $this->validateUser($request, $user);
        $roleIds = array_map('intval', $validated['role_ids']);
        $organizationIds = array_map('intval', $validated['organization_ids']);
        app(InstitutionalAccess::class)->authorizeOrganizationIds($request->user(), $organizationIds);
        $this->authorizeRoleAssignment($request, $roleIds);
        $this->validatePrimaryOrganization($validated, $organizationIds);

        if ($request->user()->is($user) && $validated['status'] !== 'active') {
            throw ValidationException::withMessages([
                'status' => trans('institutional.validation.cannot_disable_self'),
            ]);
        }

        $emailChanged = false;
        DB::transaction(function () use ($request, $user, $validated, $roleIds, $organizationIds, &$emailChanged): void {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->protectLastSuperAdministrator(
                $request,
                $user,
                $roleIds,
                $validated['status']
            );
            $user->load(['roles:id', 'organizations:id']);
            $old = [
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'preferred_locale' => $user->preferred_locale,
                'role_ids' => $user->roles->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'organization_ids' => $user->organizations->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ];

            $updates = [
                'name' => trim($validated['name']),
                'email' => Str::lower(trim($validated['email'])),
                'status' => $validated['status'],
                'preferred_locale' => $validated['preferred_locale'],
            ];
            if (! hash_equals(Str::lower((string) $user->email), $updates['email'])) {
                $updates['email_verified_at'] = null;
                $emailChanged = true;
            }

            if (! empty($validated['password'])) {
                $updates['password'] = Hash::make($validated['password']);
                $updates['must_change_password'] = true;
            }

            $user->forceFill($updates)->save();
            $user->roles()->sync($roleIds);
            $user->organizations()->sync($this->organizationPivot(
                $organizationIds,
                (int) $validated['primary_organization_id']
            ));

            AuditTrail::record('user.updated', $user, $old, [
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'preferred_locale' => $user->preferred_locale,
                'role_ids' => $roleIds,
                'organization_ids' => $organizationIds,
                'password_reset_required' => ! empty($validated['password']),
            ]);
        });

        if ($emailChanged) {
            $user->refresh()->sendEmailVerificationNotification();
        }

        return back()->with('success', trans('institutional.messages.user_updated'));
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        $passwordRules = $user
            ? ['nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]
            : ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'preferred_locale' => ['required', Rule::in(['ar', 'en', 'fr'])],
            'password' => $passwordRules,
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'distinct', 'exists:roles,id'],
            'organization_ids' => ['required', 'array', 'min:1'],
            'organization_ids.*' => ['integer', 'distinct', 'exists:organizations,id'],
            'primary_organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ]);
    }

    private function formOptions(): array
    {
        $roles = Role::query()->orderByDesc('is_system')->orderBy('name');

        if (! auth()->user()->hasRole('super-admin')) {
            $roles->where('slug', '!=', 'super-admin');
        }

        return [
            'roles' => $roles->get(),
            'organizations' => app(InstitutionalAccess::class)
                ->constrainOrganizations(Organization::query(), auth()->user())
                ->where('status', 'active')
                ->orderByDesc('is_root')
                ->orderBy('display_name')
                ->get(),
        ];
    }

    private function authorizeRoleAssignment(Request $request, array $roleIds): void
    {
        $superAdminId = Role::query()->where('slug', 'super-admin')->value('id');

        if ($superAdminId && in_array((int) $superAdminId, $roleIds, true)) {
            abort_unless($request->user()->hasRole('super-admin'), 403);
        }
    }

    private function validatePrimaryOrganization(array $validated, array $organizationIds): void
    {
        if (! in_array((int) $validated['primary_organization_id'], $organizationIds, true)) {
            throw ValidationException::withMessages([
                'primary_organization_id' => trans('institutional.validation.primary_must_be_selected'),
            ]);
        }
    }

    private function protectLastSuperAdministrator(
        Request $request,
        User $user,
        array $newRoleIds,
        string $newStatus
    ): void {
        $superAdmin = Role::query()
            ->where('slug', 'super-admin')
            ->lockForUpdate()
            ->first();

        if (! $superAdmin || ! $user->roles()->whereKey($superAdmin->id)->exists()) {
            return;
        }

        $keepsRole = in_array((int) $superAdmin->id, $newRoleIds, true);
        if ($keepsRole && $newStatus === 'active') {
            return;
        }

        $activeSuperAdmins = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('roles.id', $superAdmin->id))
            ->count();

        if ($activeSuperAdmins <= 1) {
            throw ValidationException::withMessages([
                'role_ids' => trans('institutional.validation.last_super_admin'),
            ]);
        }

        abort_unless($request->user()->hasRole('super-admin'), 403);
    }

    private function organizationPivot(array $organizationIds, int $primaryId): array
    {
        $pivot = [];

        foreach ($organizationIds as $organizationId) {
            $pivot[$organizationId] = ['is_primary' => $organizationId === $primaryId];
        }

        return $pivot;
    }
}
