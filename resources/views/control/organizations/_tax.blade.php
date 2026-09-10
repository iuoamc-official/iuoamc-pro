@if (isset($organization) && $organization->exists && app(\App\Services\EntityTaxRegistry::class)->canView())
    @php
        $taxUnavailable = false;
        try {
            $taxIdentifiers = app(\App\Services\EntityTaxRegistry::class)->forDisplay($organization);
        } catch (\Throwable $taxError) {
            $taxIdentifiers = [];
            $taxUnavailable = true;
        }
    @endphp
    <section class="form-card" data-entity-tax-registry="1.0.0">
        <header>
            <span class="form-step">03</span>
            <div>
                <h2>{{ __('entity_registry.tax_title') }}</h2>
                <p>{{ __('entity_registry.private_notice') }}</p>
            </div>
        </header>
        @if ($taxUnavailable)
            <p class="field-error" role="alert">{{ __('entity_registry.unavailable') }}</p>
        @elseif (count($taxIdentifiers))
            <dl class="form-grid two-columns">
                @foreach ($taxIdentifiers as $taxIdentifier)
                    <div class="field">
                        <dt>{{ __('entity_registry.types.'.$taxIdentifier['type']) }}</dt>
                        <dd><strong class="record-code"><bdi dir="ltr">{{ $taxIdentifier['value'] }}</bdi></strong></dd>
                    </div>
                @endforeach
            </dl>
        @else
            <p>{{ __('entity_registry.pending') }}</p>
        @endif
        <p>{{ __('entity_registry.vat_notice') }}</p>
    </section>
@endif
