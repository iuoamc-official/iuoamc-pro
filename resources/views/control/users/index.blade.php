@extends('layouts.control')

@section('title', __('institutional.users'))

@section('content')
    <section class="page-heading">
        <div>
            <span class="eyebrow">GOVERNANCE / ACCESS</span>
            <h1>{{ __('institutional.users') }}</h1>
            <p>{{ __('institutional.manage_users') }}</p>
        </div>
        @if (auth()->user()->canDo('core.users.manage'))
            <a class="primary-action" href="{{ route('users.create', ['locale' => app()->getLocale()]) }}">
                <span aria-hidden="true">＋</span>{{ __('institutional.new_user') }}
            </a>
        @endif
    </section>

    <form class="filter-bar" method="get">
        <label class="filter-search">
            <span class="sr-only">{{ __('institutional.search') }}</span>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('institutional.search_placeholder') }}">
        </label>
        <label>
            <span class="sr-only">{{ __('institutional.status') }}</span>
            <select name="status">
                <option value="">{{ __('institutional.all_statuses') }}</option>
                @foreach (['active', 'inactive', 'suspended'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('institutional.'.$status) }}</option>
                @endforeach
            </select>
        </label>
        <button class="secondary-action" type="submit">{{ __('institutional.filter') }}</button>
        <a class="text-action" href="{{ route('users.index', ['locale' => app()->getLocale()]) }}">{{ __('institutional.reset') }}</a>
    </form>

    <section class="data-panel">
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr>
                    <th>{{ __('institutional.user') }}</th>
                    <th>{{ __('institutional.assigned_roles') }}</th>
                    <th>{{ __('institutional.assigned_organizations') }}</th>
                    <th>{{ __('institutional.status') }}</th>
                    <th>{{ __('institutional.occurred_at') }}</th>
                    <th><span class="sr-only">{{ __('institutional.actions') }}</span></th>
                </tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td><strong class="record-title"><bdi dir="auto">{{ $user->name }}</bdi></strong><small><bdi dir="ltr">{{ $user->email }}</bdi></small></td>
                            <td><div class="tag-list">@foreach ($user->roles as $role)<span>{{ $role->name }}</span>@endforeach</div></td>
                            <td><div class="tag-list">@foreach ($user->organizations as $organization)<span>{{ $organization->display_name }}</span>@endforeach</div></td>
                            <td><span class="status-badge is-{{ $user->status }}">{{ __('institutional.'.$user->status) }}</span></td>
                            <td>{{ $user->last_login_at?->format('Y-m-d H:i') ?? __('institutional.not_available') }}</td>
                            <td class="table-actions">
                                @if (auth()->user()->canDo('core.users.manage') && (! $user->hasRole('super-admin') || auth()->user()->hasRole('super-admin')))
                                    <a href="{{ route('users.edit', ['locale' => app()->getLocale(), 'user' => $user]) }}">{{ __('institutional.edit') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">{{ __('institutional.no_results') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('control.organizations._pagination', ['paginator' => $users])
    </section>
@endsection
