@extends('layouts.control')

@section('title', __('institutional.organizations'))

@section('content')
    <section class="page-heading">
        <div>
            <span class="eyebrow">GOVERNANCE / ENTITIES</span>
            <h1>{{ __('institutional.organizations') }}</h1>
            <p>{{ __('institutional.manage_organizations') }}</p>
        </div>
        @if (auth()->user()->canDo('organizations.manage'))
            <a class="primary-action" href="{{ route('organizations.create', ['locale' => app()->getLocale()]) }}">
                <span aria-hidden="true">＋</span>{{ __('institutional.new_organization') }}
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
                @foreach (['active', 'inactive', 'archived'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('institutional.'.$status) }}</option>
                @endforeach
            </select>
        </label>
        <button class="secondary-action" type="submit">{{ __('institutional.filter') }}</button>
        <a class="text-action" href="{{ route('organizations.index', ['locale' => app()->getLocale()]) }}">{{ __('institutional.reset') }}</a>
    </form>

    <section class="data-panel">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('institutional.organization') }}</th>
                        <th>{{ __('institutional.code') }}</th>
                        <th>{{ __('institutional.parent') }}</th>
                        <th>{{ __('institutional.status') }}</th>
                        <th>{{ __('institutional.members_count') }}</th>
                        <th>{{ __('institutional.children_count') }}</th>
                        <th><span class="sr-only">{{ __('institutional.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($organizations as $organization)
                        <tr>
                            <td>
                                <strong class="record-title">{{ $organization->display_name }}</strong>
                                <small>{{ $organization->legal_name }}</small>
                                {{-- IUOAMC_ENTITY_REGISTRATION_1_0_0 --}}
                                @if ($organization->registration_number)
                                    <small>{{ __('entity_registry.registration') }}: <bdi dir="ltr">{{ $organization->registration_number }}</bdi> · <bdi dir="ltr">{{ $organization->jurisdiction }}</bdi></small>
                                @endif
                                @if ($organization->is_root)
                                    <span class="mini-badge is-gold">{{ __('institutional.root_entity') }}</span>
                                @endif
                            </td>
                            <td><code class="record-code">{{ $organization->code }}</code></td>
                            <td>{{ $organization->parent?->display_name ?? __('institutional.no_parent') }}</td>
                            <td><span class="status-badge is-{{ $organization->status }}">{{ __('institutional.'.$organization->status) }}</span></td>
                            <td>{{ number_format($organization->users_count) }}</td>
                            <td>{{ number_format($organization->children_count) }}</td>
                            <td class="table-actions">
                                @if (auth()->user()->canDo('organizations.manage') && (! $organization->is_root || auth()->user()->hasRole('super-admin')))
                                    <a href="{{ route('organizations.edit', ['locale' => app()->getLocale(), 'organization' => $organization]) }}">{{ __('institutional.edit') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">{{ __('institutional.no_results') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('control.organizations._pagination', ['paginator' => $organizations])
    </section>
@endsection
