@extends('layouts.control')

@section('title', __('institutional.audit_log'))

@section('content')
    <section class="page-heading">
        <div><span class="eyebrow">SECURITY / AUDIT</span><h1>{{ __('institutional.audit_log') }}</h1><p>{{ __('institutional.audit_intro') }}</p></div>
        <span class="immutable-seal"><span aria-hidden="true">◆</span> IMMUTABLE LOG</span>
    </section>

    <form class="filter-bar audit-filters" method="get">
        <label class="filter-search"><span class="sr-only">{{ __('institutional.search') }}</span><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('institutional.search_placeholder') }}"></label>
        <label><span class="sr-only">{{ __('institutional.event') }}</span><select name="event"><option value="">{{ __('institutional.all_events') }}</option>@foreach ($events as $event)<option value="{{ $event }}" @selected(request('event') === $event)>{{ $event }}</option>@endforeach</select></label>
        <label class="date-filter"><span>{{ __('institutional.date_from') }}</span><input type="date" name="date_from" value="{{ request('date_from') }}"></label>
        <label class="date-filter"><span>{{ __('institutional.date_to') }}</span><input type="date" name="date_to" value="{{ request('date_to') }}"></label>
        <button class="secondary-action" type="submit">{{ __('institutional.filter') }}</button>
        <a class="text-action" href="{{ route('audit.index', ['locale' => app()->getLocale()]) }}">{{ __('institutional.reset') }}</a>
    </form>

    <section class="data-panel">
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>{{ __('institutional.event') }}</th><th>{{ __('institutional.actor') }}</th><th>{{ __('institutional.subject') }}</th><th>{{ __('institutional.ip_address') }}</th><th>{{ __('institutional.occurred_at') }}</th><th><span class="sr-only">{{ __('institutional.actions') }}</span></th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td><code class="event-code">{{ $log->event }}</code></td>
                            <td><strong class="record-title">{{ $log->actor?->name ?? __('institutional.unknown_actor') }}</strong><small>{{ $log->actor?->email }}</small></td>
                            <td><span class="subject-ref">{{ class_basename((string) $log->auditable_type) }}{{ $log->auditable_id ? ' #'.$log->auditable_id : '' }}</span></td>
                            <td><code class="record-code">{{ $log->ip_address ?? '—' }}</code></td>
                            <td><time datetime="{{ $log->occurred_at?->toIso8601String() }}">{{ $log->occurred_at?->format('Y-m-d H:i:s') }}</time></td>
                            <td class="table-actions"><a href="{{ route('audit.show', ['locale' => app()->getLocale(), 'auditLog' => $log]) }}">{{ __('institutional.view') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-state">{{ __('institutional.no_results') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('control.organizations._pagination', ['paginator' => $logs])
    </section>
@endsection
