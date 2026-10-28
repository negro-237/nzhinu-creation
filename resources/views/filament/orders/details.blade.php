@php($order = $getRecord())
<div class="space-y-4">
    <p><strong>{{ $order->number }}</strong> · {{ $order->currency->value }} · {{ $order->status->getLabel() }}</p>
    <p>{{ $order->customer_name }}<br>{{ $order->phone }}<br>{{ $order->email }}</p>
    @if($contact = app(\App\Services\WhatsAppContact::class)->customerLink(auth()->user(), $order))<p><a href="{{ $contact }}" target="_blank" rel="noopener noreferrer">Contacter le client sur WhatsApp</a> (message ouvert manuellement, aucun envoi automatique)</p>@endif
    <p>{{ $order->method->value === 'delivery' ? 'Livraison' : 'Retrait' }} — {{ $order->fulfillment_snapshot['name'] ?? 'Zone à confirmer' }}</p>
    @if($order->delivery_address)
        <p>{{ implode(', ', array_filter($order->delivery_address)) }}</p>
    @else
        <p>{{ $order->fulfillment_snapshot['address'] ?? '' }}</p>
    @endif
    <ul>@foreach($order->items as $item)<li>{{ $item->product_name }} · {{ $item->variant_label }} · {{ $item->sku }} · {{ $item->quantity }} × {{ \App\Support\MoneyInput::fromMinor($item->unit_price_minor, $order->currency) }} {{ $order->currency->value }} · dépôt #{{ $item->stock_location_id }}</li>@endforeach</ul>
    <p>Sous-total : {{ \App\Support\MoneyInput::fromMinor($order->subtotal_minor, $order->currency) }} {{ $order->currency->value }}</p>
    <p>Total définitif : {{ $order->total_minor === null ? 'À confirmer' : \App\Support\MoneyInput::fromMinor($order->total_minor, $order->currency).' '.$order->currency->value }}</p>
    <p>Réservation jusqu’au {{ $order->reservation->expires_at->format('d/m/Y H:i') }} UTC · {{ $order->payment_status->getLabel() }} · {{ $order->fulfillment_status->getLabel() }}</p>
    <h3>Fonds vérifiés</h3>
    <p>Reçus : {{ \App\Support\MoneyInput::fromMinor($order->verified_paid_minor, $order->currency) }} · Remboursés : {{ \App\Support\MoneyInput::fromMinor($order->verified_refunded_minor, $order->currency) }} · Net : {{ \App\Support\MoneyInput::fromMinor($order->verified_net_minor, $order->currency) }} {{ $order->currency->value }}</p>
    @if($order->financial_review_required)<p><strong>Revue financière requise : fonds reçus après clôture ou montant supérieur au total. Décider explicitement d’une réactivation ou d’un remboursement.</strong></p>@endif
    <p>Une capture client ne vaut pas vérification. Les paiements et remboursements sont contrôlés dans « Paiements et remboursements ».</p>
    <ul>@foreach($order->payments()->orderBy('id')->get() as $payment)
        <li>#{{ $payment->id }} · {{ $payment->kind->getLabel() }} · {{ \App\Support\MoneyInput::fromMinor($payment->amount_minor, $payment->currency) }} {{ $payment->currency->value }} · {{ $payment->method->getLabel() }} · {{ $payment->reference }} · {{ $payment->status->getLabel() }}<br>
            Déclarant #{{ $payment->recorded_by }} · {{ $payment->received_at }} · {{ $payment->note }}<br>
            Vérificateur #{{ $payment->verified_by }} · {{ $payment->verified_at }} · {{ $payment->verification_source }} · {{ $payment->verification_note }}
            @if($payment->rejected_at)<br>Rejet par #{{ $payment->rejected_by }} · {{ $payment->rejected_at }} · {{ $payment->rejection_reason }}@endif
        </li>
    @endforeach</ul>
    @if($order->fulfillment)<p>Remise le {{ $order->fulfillment->handed_over_at }} par #{{ $order->fulfillment->handed_over_by }} · {{ $order->fulfillment->carrier }} · {{ $order->fulfillment->reference }} · {{ $order->fulfillment->note }}</p>@endif
    <h3>Historique</h3><ul>@foreach($order->histories as $history)<li>{{ $history->created_at->format('d/m/Y H:i') }} · {{ $history->from_status ?? 'Création' }} → {{ $history->to_status }} · {{ $history->reason }} · acteur #{{ $history->actor_id }}
        @if($history->context) · Net vérifié {{ \App\Support\MoneyInput::fromMinor($history->context['net_minor'] ?? 0, $order->currency) }} {{ $order->currency->value }}@endif</li>@endforeach</ul>
</div>
