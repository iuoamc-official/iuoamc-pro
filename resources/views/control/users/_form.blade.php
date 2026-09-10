@php
    $editing = isset($user) && $user->exists;
    $selectedRoles = old('role_ids', $editing ? $user->roles->pluck('id')->all() : []);
    $selectedOrganizations = old('organization_ids', $editing ? $user->organizations->pluck('id')->all() : []);
    $primaryOrganization = old('primary_organization_id', $editing ? optional($user->organizations->firstWhere('pivot.is_primary', true))->id : null);
@endphp

@if ($errors->any())
    <div class="alert alert-error" role="alert"><strong>{{ $errors->first() }}</strong></div>
@endif

<form class="institutional-form" method="post" action="{{ $editing
    ? route('users.update', ['locale' => app()->getLocale(), 'user' => $user])
    : route('users.store', ['locale' => app()->getLocale()]) }}">
    @csrf
    @if ($editing) @method('put') @endif

    <section class="form-card">
        <header><span class="form-step">01</span><div><h2>{{ __('institutional.account_identity') }}</h2><p>{{ __('institutional.required_fields') }}</p></div></header>
        <div class="form-grid two-columns">
            <label class="field">
                <span>{{ __('institutional.full_name') }} *</span>
                <input name="name" maxlength="255" required value="{{ old('name', $user->name ?? '') }}">
                @error('name')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>{{ __('institutional.email') }} *</span>
                <input class="ltr-input" type="email" name="email" maxlength="255" autocomplete="off" required value="{{ old('email', $user->email ?? '') }}">
                @error('email')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>{{ __('institutional.status') }} *</span>
                <select name="status" required>
                    @foreach (['active', 'inactive', 'suspended'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $user->status ?? 'active') === $status)>{{ __('institutional.'.$status) }}</option>
                    @endforeach
                </select>
                @error('status')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>{{ __('institutional.preferred_language') }} *</span>
                <select name="preferred_locale" required>
                    @foreach (['ar' => 'arabic', 'en' => 'english', 'fr' => 'french'] as $locale => $key)
                        <option value="{{ $locale }}" @selected(old('preferred_locale', $user->preferred_locale ?? 'ar') === $locale)>{{ __('institutional.'.$key) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>{{ $editing ? __('institutional.new_password_optional') : __('institutional.temporary_password').' *' }}</span>
                <input type="password" name="password" autocomplete="new-password" @required(! $editing)>
                @error('password')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>{{ __('institutional.confirm_password') }}{{ $editing ? '' : ' *' }}</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" @required(! $editing)>
            </label>
        </div>
        <p class="form-note">{{ __('institutional.force_change_note') }}</p>
    </section>

    <section class="form-card">
        <header><span class="form-step">02</span><div><h2>{{ __('institutional.account_access') }}</h2><p>{{ __('institutional.selection_required') }}</p></div></header>
        <div class="access-grid">
            <fieldset class="choice-group">
                <legend>{{ __('institutional.assigned_roles') }} *</legend>
                @foreach ($roles as $role)
                    <label class="choice-card">
                        <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(in_array($role->id, array_map('intval', $selectedRoles), true))>
                        <span><strong>{{ $role->name }}</strong><small>{{ $role->slug }}</small></span>
                    </label>
                @endforeach
                @error('role_ids')<small class="field-error">{{ $message }}</small>@enderror
            </fieldset>

            <fieldset class="choice-group">
                <legend>{{ __('institutional.assigned_organizations') }} *</legend>
                @foreach ($organizations as $organization)
                    <label class="choice-card">
                        <input type="checkbox" name="organization_ids[]" value="{{ $organization->id }}" @checked(in_array($organization->id, array_map('intval', $selectedOrganizations), true))>
                        <span><strong>{{ $organization->display_name }}</strong><small>{{ $organization->code }}</small></span>
                    </label>
                @endforeach
                @error('organization_ids')<small class="field-error">{{ $message }}</small>@enderror
            </fieldset>
        </div>

        <label class="field primary-entity-field">
            <span>{{ __('institutional.primary_organization') }} *</span>
            <select name="primary_organization_id" required>
                <option value="">—</option>
                @foreach ($organizations as $organization)
                    <option value="{{ $organization->id }}" @selected((string) $primaryOrganization === (string) $organization->id)>{{ $organization->display_name }}</option>
                @endforeach
            </select>
            @error('primary_organization_id')<small class="field-error">{{ $message }}</small>@enderror
        </label>
    </section>

    <div class="form-footer">
        <p><span class="status-dot"></span>{{ __('institutional.security_notice') }}</p>
        <div>
            <a class="secondary-action" href="{{ route('users.index', ['locale' => app()->getLocale()]) }}">{{ __('institutional.cancel') }}</a>
            <button class="primary-action" type="submit">{{ $editing ? __('institutional.save_changes') : __('institutional.create') }}</button>
        </div>
    </div>
</form>
