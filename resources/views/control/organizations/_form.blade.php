@php($editing = isset($organization) && $organization->exists)

@if ($errors->any())
    <div class="alert alert-error" role="alert">
        <strong>{{ $errors->first() }}</strong>
    </div>
@endif

<form class="institutional-form" method="post" action="{{ $editing
    ? route('organizations.update', ['locale' => app()->getLocale(), 'organization' => $organization])
    : route('organizations.store', ['locale' => app()->getLocale()]) }}">
    @csrf
    @if ($editing) @method('put') @endif

    <section class="form-card">
        <header>
            <span class="form-step">01</span>
            <div>
                <h2>{{ __('institutional.organization_identity') }}</h2>
                <p>{{ __('institutional.required_fields') }}</p>
            </div>
        </header>

        <div class="form-grid two-columns">
            <label class="field">
                <span>{{ __('institutional.display_name') }} *</span>
                <input name="display_name" maxlength="255" required value="{{ old('display_name', $organization->display_name ?? '') }}">
                @error('display_name')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>{{ __('institutional.legal_name') }} *</span>
                <input name="legal_name" maxlength="255" required value="{{ old('legal_name', $organization->legal_name ?? '') }}">
                @error('legal_name')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>{{ __('institutional.code') }} *</span>
                <input class="ltr-input" name="code" maxlength="80" required pattern="[A-Za-z0-9][A-Za-z0-9._-]*" value="{{ old('code', $organization->code ?? '') }}">
                @error('code')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>{{ __('institutional.registration_number') }}</span>
                <input class="ltr-input" name="registration_number" maxlength="100" value="{{ old('registration_number', $organization->registration_number ?? '') }}">
                @error('registration_number')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>{{ __('institutional.jurisdiction') }}</span>
                <input class="ltr-input" name="jurisdiction" maxlength="2" pattern="[A-Za-z]{2}" placeholder="GB" value="{{ old('jurisdiction', $organization->jurisdiction ?? '') }}">
                @error('jurisdiction')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="field">
                <span>{{ __('institutional.status') }} *</span>
                <select name="status" required>
                    @foreach (['active', 'inactive', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $organization->status ?? 'active') === $status)>{{ __('institutional.'.$status) }}</option>
                    @endforeach
                </select>
                @error('status')<small class="field-error">{{ $message }}</small>@enderror
            </label>
        </div>
    </section>

    <section class="form-card">
        <header>
            <span class="form-step">02</span>
            <div><h2>{{ __('institutional.organization_hierarchy') }}</h2></div>
        </header>
        <label class="field">
            <span>{{ __('institutional.parent') }}</span>
            <select name="parent_id" @disabled(($organization->is_root ?? false))>
                <option value="">{{ __('institutional.no_parent') }}</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected((string) old('parent_id', $organization->parent_id ?? '') === (string) $parent->id)>
                        {{ $parent->display_name }} — {{ $parent->code }}
                    </option>
                @endforeach
            </select>
            @error('parent_id')<small class="field-error">{{ $message }}</small>@enderror
        </label>
    </section>

    {{-- IUOAMC_ENTITY_TAX_FORM_1_0_0 --}}
    @include('control.organizations._tax')

    <div class="form-footer">
        <p><span class="status-dot"></span>{{ __('institutional.security_notice') }}</p>
        <div>
            <a class="secondary-action" href="{{ route('organizations.index', ['locale' => app()->getLocale()]) }}">{{ __('institutional.cancel') }}</a>
            <button class="primary-action" type="submit">{{ $editing ? __('institutional.save_changes') : __('institutional.create') }}</button>
        </div>
    </div>
</form>
