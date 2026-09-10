<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->when($validated['q'] ?? null, function ($query, string $term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('name', 'like', "%{$term}%")
                        ->orWhere('slug', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('control.roles.index', compact('roles'));
    }

    public function create(): View
    {
        return view('control.roles.create', [
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRole($request);

        $role = DB::transaction(function () use ($validated): Role {
            $role = Role::create([
                'name' => trim($validated['name']),
                'slug' => Str::slug($validated['slug']),
                'description' => $validated['description'] ?: null,
                'is_system' => false,
            ]);
            $role->permissions()->sync(array_map('intval', $validated['permission_ids'] ?? []));

            AuditTrail::record('role.created', $role, [], [
                'name' => $role->name,
                'slug' => $role->slug,
                'permission_ids' => array_map('intval', $validated['permission_ids'] ?? []),
            ]);

            return $role;
        });

        return redirect()
            ->route('roles.edit', ['locale' => $request->route('locale'), 'role' => $role])
            ->with('success', trans('institutional.messages.role_created'));
    }

    public function edit(Role $role): View
    {
        $role->load('permissions:id');

        return view('control.roles.edit', [
            'role' => $role,
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $this->validateRole($request, $role);
        $permissionIds = array_map('intval', $validated['permission_ids'] ?? []);

        if ($role->slug === 'super-admin') {
            abort_unless($request->user()->hasRole('super-admin'), 403);
            $permissionIds = Permission::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        DB::transaction(function () use ($role, $validated, $permissionIds): void {
            $role->load('permissions:id');
            $old = [
                'name' => $role->name,
                'description' => $role->description,
                'permission_ids' => $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ];

            $role->fill([
                'name' => trim($validated['name']),
                'description' => $validated['description'] ?: null,
            ])->save();
            $role->permissions()->sync($permissionIds);

            AuditTrail::record('role.updated', $role, $old, [
                'name' => $role->name,
                'description' => $role->description,
                'permission_ids' => $permissionIds,
            ]);
        });

        return back()->with('success', trans('institutional.messages.role_updated'));
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        $slugRules = ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9-]*$/'];
        if (! $role) {
            $slugRules[] = Rule::unique('roles', 'slug');
        }

        if ($role && $request->filled('slug') && $request->input('slug') !== $role->slug) {
            throw ValidationException::withMessages([
                'slug' => trans('institutional.validation.role_slug_immutable'),
            ]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => $slugRules,
            'description' => ['nullable', 'string', 'max:1000'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);
    }

    private function permissionGroups()
    {
        return Permission::query()
            ->orderBy('module')
            ->orderBy('code')
            ->get()
            ->groupBy('module');
    }
}
