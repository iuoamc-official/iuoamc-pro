@extends('layouts.control')

@section('title', __('institutional.roles'))

@section('content')
    <section class="page-heading">
        <div>
            <span class="eyebrow">GOVERNANCE / RBAC</span>
            <h1>{{ __('institutional.roles') }}</h1>
            <p>{{ __('institutional.manage_roles') }}</p>
        </div>
        @if (auth()->user()->canDo('core.roles.manage'))
            <a class="primary-action" href="{{ route('roles.create', ['locale' => app()->getLocale()]) }}"><span aria-hidden="true">＋</span>{{ __('institutional.new_role') }}</a>
        @endif
    </section>

    <form class="filter-bar" method="get">
        <label class="filter-search"><span class="sr-only">{{ __('institutional.search') }}</span><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('institutional.search_placeholder') }}"></label>
        <button class="secondary-action" type="submit">{{ __('institutional.search') }}</button>
        <a class="text-action" href="{{ route('roles.index', ['locale' => app()->getLocale()]) }}">{{ __('institutional.reset') }}</a>
    </form>

    <section class="data-panel">
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr>
                    <th>{{ __('institutional.role') }}</th>
                    <th>{{ __('institutional.role_slug') }}</th>
                    <th>{{ __('institutional.users_count') }}</th>
                    <th>{{ __('institutional.permissions_count') }}</th>
                    <th>{{ __('institutional.status') }}</th>
                    <th><span class="sr-only">{{ __('institutional.actions') }}</span></th>
                </tr></thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td><strong class="record-title">{{ $role->name }}</strong><small>{{ $role->description ?: __('institutional.not_available') }}</small></td>
                            <td><code class="record-code">{{ $role->slug }}</code></td>
                            <td>{{ number_format($role->users_count) }}</td>
                            <td>{{ number_format($role->permissions_count) }}</td>
                            <td><span class="mini-badge {{ $role->is_system ? 'is-gold' : '' }}">{{ $role->is_system ? __('institutional.system_role') : __('institutional.custom_role') }}</span></td>
                            <td class="table-actions">
                                @if (auth()->user()->canDo('core.roles.manage'))
                                    <a href="{{ route('roles.edit', ['locale' => app()->getLocale(), 'role' => $role]) }}">{{ __('institutional.edit') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">{{ __('institutional.no_results') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('control.organizations._pagination', ['paginator' => $roles])
    </section>
@endsection
