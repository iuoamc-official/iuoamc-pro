@php
    $editing = isset($role) && $role->exists;
    $selectedPermissions = old('permission_ids', $editing ? $role->permissions->pluck('id')->all() : []);
@endphp

@if ($errors->any())
    <div class="alert alert-error" role="alert"><strong>{{ $errors->first() }}</strong></div>
@endif

<form class="institutional-form" method="post" action="{{ $editing
    ? route('roles.update', ['locale' => app()->getLocale(), 'role' => $role])
    : route('roles.store', ['locale' => app()->getLocale()]) }}">
    @csrf
    @if ($editing) @method('put') @endif

    <section class="form-card">
        <header><span class="form-step">01</span><div><h2>{{ __('institutional.role_identity') }}</h2></div></header>
        <div class="form-grid two-columns">
            <label class="field">
                <span>{{ __('institutional.role_name') }} *</span>
                <input name="name" maxlength="120" required value="{{ old('name', $role->name ?? '') }}">
                @error('name')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>{{ __('institutional.role_slug') }} *</span>
                <input class="ltr-input" name="slug" maxlength="80" required pattern="[a-z0-9][a-z0-9-]*" @readonly($editing) value="{{ old('slug', $role->slug ?? '') }}">
                <small>{{ __('institutional.role_slug_note') }}</small>
                @error('slug')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="field full-span">
                <span>{{ __('institutional.description') }}</span>
                <textarea name="description" rows="3" maxlength="1000">{{ old('description', $role->description ?? '') }}</textarea>
                @error('description')<small class="field-error">{{ $message }}</small>@enderror
            </label>
        </div>
    </section>

    <section class="form-card">
        <header><span class="form-step">02</span><div><h2>{{ __('institutional.permissions') }}</h2><p>{{ __('institutional.security_notice') }}</p></div></header>
        <div class="permission-groups">
            @foreach ($permissionGroups as $module => $permissions)
                <fieldset class="permission-group">
                    <legend>{{ str($module)->replace('_', ' ')->title() }}</legend>
                    @foreach ($permissions as $permission)
                        <label class="permission-choice">
                            <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}"
                                   @checked(($role->slug ?? null) === 'super-admin' || in_array($permission->id, array_map('intval', $selectedPermissions), true))
                                   @disabled(($role->slug ?? null) === 'super-admin')>
                            <span><strong>{{ $permission->name }}</strong><code>{{ $permission->code }}</code></span>
                        </label>
                    @endforeach
                </fieldset>
            @endforeach
        </div>
        @error('permission_ids')<small class="field-error">{{ $message }}</small>@enderror
    </section>

    <div class="form-footer">
        <p><span class="status-dot"></span>{{ __('institutional.security_notice') }}</p>
        <div>
            <a class="secondary-action" href="{{ route('roles.index', ['locale' => app()->getLocale()]) }}">{{ __('institutional.cancel') }}</a>
            <button class="primary-action" type="submit">{{ $editing ? __('institutional.save_changes') : __('institutional.create') }}</button>
        </div>
    </div>
</form>
