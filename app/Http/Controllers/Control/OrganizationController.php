<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\AuditTrail;
use App\Services\InstitutionalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])],
        ]);

        $organizations = app(InstitutionalAccess::class)
            ->constrainOrganizations(Organization::query(), $request->user())
            ->with('parent:id,display_name')
            ->withCount(['users', 'children'])
            ->when($validated['q'] ?? null, function ($query, string $term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('display_name', 'like', "%{$term}%")
                        ->orWhere('legal_name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%")
                        ->orWhere('registration_number', 'like', "%{$term}%");
                });
            })
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('is_root')
            ->orderBy('display_name')
            ->paginate(20)
            ->withQueryString();

        return view('control.organizations.index', compact('organizations'));
    }

    public function create(Request $request): View
    {
        $parents = app(InstitutionalAccess::class)
            ->constrainOrganizations(Organization::query(), $request->user())
            ->where('status', 'active')
            ->orderByDesc('is_root')
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'code']);

        return view('control.organizations.create', compact('parents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateOrganization($request);
        $this->authorizeParent($request, $validated['parent_id'] ?? null);

        $organization = DB::transaction(function () use ($validated): Organization {
            $organization = Organization::create($validated + ['is_root' => false]);

            AuditTrail::record(
                'organization.created',
                $organization,
                [],
                $organization->only($this->auditableFields())
            );

            return $organization;
        });

        return redirect()
            ->route('organizations.edit', [
                'locale' => $request->route('locale'),
                'organization' => $organization,
            ])
            ->with('success', trans('institutional.messages.organization_created'));
    }

    public function edit(Request $request, Organization $organization): View
    {
        app(InstitutionalAccess::class)->authorizeOrganization($request->user(), $organization);
        $excluded = array_merge([$organization->id], $this->descendantIds($organization));

        $parents = app(InstitutionalAccess::class)
            ->constrainOrganizations(Organization::query(), $request->user())
            ->where('status', 'active')
            ->whereNotIn('id', $excluded)
            ->orderByDesc('is_root')
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'code']);

        return view('control.organizations.edit', compact('organization', 'parents'));
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        app(InstitutionalAccess::class)->authorizeOrganization($request->user(), $organization);

        if ($organization->is_root && ! $request->user()->hasRole('super-admin')) {
            abort(403);
        }

        $validated = $this->validateOrganization($request, $organization);
        $this->authorizeParent($request, $validated['parent_id'] ?? null);

        if ($organization->is_root && $validated['status'] !== 'active') {
            throw ValidationException::withMessages([
                'status' => trans('institutional.validation.root_must_stay_active'),
            ]);
        }

        if (isset($validated['parent_id'])) {
            $forbidden = array_merge([$organization->id], $this->descendantIds($organization));

            if (in_array((int) $validated['parent_id'], $forbidden, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => trans('institutional.validation.circular_parent'),
                ]);
            }
        }

        DB::transaction(function () use ($organization, $validated): void {
            Organization::query()->select('id')->lockForUpdate()->get();

            if (isset($validated['parent_id'])) {
                $forbidden = array_merge([$organization->id], $this->descendantIds($organization));

                if (in_array((int) $validated['parent_id'], $forbidden, true)) {
                    throw ValidationException::withMessages([
                        'parent_id' => trans('institutional.validation.circular_parent'),
                    ]);
                }
            }

            $old = $organization->only($this->auditableFields());
            $organization->fill($validated)->save();

            AuditTrail::record(
                'organization.updated',
                $organization,
                $old,
                $organization->fresh()->only($this->auditableFields())
            );
        });

        return back()->with('success', trans('institutional.messages.organization_updated'));
    }

    private function validateOrganization(Request $request, ?Organization $organization = null): array
    {
        $request->merge([
            'code' => mb_strtoupper(trim((string) $request->input('code'))),
            'jurisdiction' => mb_strtoupper(trim((string) $request->input('jurisdiction'))),
            'parent_id' => $request->filled('parent_id') ? $request->input('parent_id') : null,
        ]);

        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'code' => [
                'required', 'string', 'max:80', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('organizations', 'code')->ignore($organization?->id),
            ],
            'legal_name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'jurisdiction' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
        ]);
    }

    private function descendantIds(Organization $organization): array
    {
        $descendants = [];
        $frontier = [$organization->id];

        while ($frontier !== []) {
            $children = Organization::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $children = array_values(array_diff($children, $descendants));
            $descendants = array_values(array_unique(array_merge($descendants, $children)));
            $frontier = $children;
        }

        return $descendants;
    }

    private function authorizeParent(Request $request, ?int $parentId): void
    {
        if ($request->user()->hasRole('super-admin')) {
            return;
        }

        if ($parentId === null) {
            throw ValidationException::withMessages([
                'parent_id' => trans('institutional.validation.parent_required_for_scoped_admin'),
            ]);
        }

        $parent = Organization::query()->findOrFail($parentId);
        app(InstitutionalAccess::class)->authorizeOrganization($request->user(), $parent);
    }

    private function auditableFields(): array
    {
        return [
            'parent_id', 'code', 'legal_name', 'display_name', 'jurisdiction',
            'registration_number', 'status', 'is_root',
        ];
    }
}
