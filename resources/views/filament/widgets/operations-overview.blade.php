<x-filament-widgets::widget>
    <x-filament::section heading="Suivi des opérations">
        <div class="space-y-6">
            <p>Situation actuelle de toutes les commandes. Les fonds nets vérifiés incluent les commandes annulées ou expirées ; ils ne représentent pas un chiffre d’affaires comptable.</p>
            @forelse($report['markets'] as $row)
                <section class="space-y-2 border-b pb-4">
                    <h3 class="font-semibold">{{ $row['market'] }} · {{ $row['currency']->value }}</h3>
                    <p>Commandes : {{ $row['total'] }} · À appeler : {{ $row['awaiting_call'] }} · Confirmées en attente de remise : {{ $row['awaiting_handover'] }} · Remises : {{ $row['completed'] }} · Revue financière : {{ $row['review'] }}</p>
                    <p>Reçus vérifiés : {{ \App\Support\MoneyInput::fromMinor($row['paid'], $row['currency']) }} {{ $row['currency']->value }} · Remboursés : {{ \App\Support\MoneyInput::fromMinor($row['refunded'], $row['currency']) }} {{ $row['currency']->value }} · Net : {{ \App\Support\MoneyInput::fromMinor($row['net'], $row['currency']) }} {{ $row['currency']->value }}</p>
                </section>
            @empty<p>Aucune commande enregistrée.</p>@endforelse
            <div><h3 class="font-semibold">Stocks faibles (20 premiers)</h3>
                <ul>@forelse($report['low_stocks'] as $stock)<li>{{ $stock->location->name }} · {{ $stock->variant->sku }} · {{ $stock->available }} disponible(s)</li>@empty<li>Aucun stock faible dans les dépôts actifs.</li>@endforelse</ul>
            </div>
            <p>Événements à préparer : {{ $report['pending_events'] }}. Notifications : {{ $report['pending_notifications'] }} en attente · {{ $report['failed_notifications'] }} en échec définitif.</p>
            <p><a href="{{ url('/admin/orders') }}" class="underline">Gérer les commandes</a> · <a href="{{ url('/admin/inventories') }}" class="underline">Gérer les stocks</a></p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
