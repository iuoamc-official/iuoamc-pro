@extends('layouts.auth')
@section('title', __('legal.documents.'.$document.'.title'))
@section('content')
<article class="legal-document">
    <header>
        <span class="section-mark"></span>
        <div>
            <h2>{{ __('legal.documents.'.$document.'.title') }}</h2>
            <p>{{ __('legal.documents.'.$document.'.intro') }}</p>
            <small><bdi dir="ltr">{{ $version }}</bdi> · {{ __('legal.effective_date') }} 12 September 2026</small>
        </div>
    </header>

    @if($document === 'membership_terms')
        <section>
            <h3>{{ __('legal.fees_title') }}</h3>
            <p>{{ __('legal.fees_intro') }}</p>
            <div class="legal-table-wrap"><table>
                <thead><tr><th>{{ __('legal.term') }}</th><th>{{ __('legal.total_fee') }}</th></tr></thead>
                <tbody>@foreach($termFees as $years => $pence)<tr><td>{{ trans_choice('legal.years', $years, ['count' => $years]) }}</td><td><bdi dir="ltr">£{{ number_format($pence / 100, 2) }}</bdi></td></tr>@endforeach</tbody>
            </table></div>
        </section>
        <section>
            <h3>{{ __('legal.allocation_title') }}</h3>
            <p>{{ __('legal.allocation_intro') }}</p>
            <div class="legal-table-wrap"><table>
                <thead><tr><th>{{ __('legal.stage') }}</th><th>{{ __('legal.percentage') }}</th></tr></thead>
                <tbody>@foreach($feeAllocation as $stage => $percentage)<tr><td>{{ __('legal.allocations.'.$stage) }}</td><td>{{ $percentage }}%</td></tr>@endforeach</tbody>
            </table></div>
        </section>
    @endif

    @foreach(__('legal.documents.'.$document.'.sections') as $section)
        <section>
            <h3>{{ $section['title'] }}</h3>
            @foreach($section['paragraphs'] ?? [] as $paragraph)<p>{{ $paragraph }}</p>@endforeach
            @if(isset($section['items']))<ul>@foreach($section['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>@endif
        </section>
    @endforeach

    <footer>
        <a class="secondary-action" href="{{ route('account.membership.create', ['locale' => app()->getLocale()]) }}">{{ __('legal.back_to_application') }}</a>
        <a class="secondary-action" href="mailto:info@iuoamc.uk">info@iuoamc.uk</a>
    </footer>
</article>
@endsection
