@php($money = fn (?int $amount) => $amount === null ? __('notifications.receipt_unconfirmed') : \App\Support\MoneyInput::fromMinor($amount, $order->currency).' '.$order->currency->value)
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ __('notifications.receipt_subject') }} {{ $order->number }}</title>
    <style>
        @page { margin: 38pt 40pt 48pt; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.4; color: #111111; }
        h1 { font-size: 21pt; margin: 6pt 0 10pt; }
        h2 { font-size: 12pt; margin: 0 0 8pt; }
        p { margin: 5pt 0; }
        .brand { font-size: 15pt; font-weight: bold; border-bottom: 3pt solid #ffb511; padding-bottom: 9pt; }
        .meta { color: #555555; font-size: 9pt; margin-bottom: 20pt; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: 7pt 6pt; vertical-align: top; word-wrap: break-word; }
        thead { display: table-header-group; }
        thead th { background: #111111; color: #ffb511; font-size: 9pt; text-align: left; }
        tr { page-break-inside: avoid; }
        .items td { border-bottom: 1pt solid #dddddd; }
        .item { width: 49%; }
        .quantity { width: 9%; }
        .amount { width: 21%; text-align: right; }
        .small { color: #555555; font-size: 8pt; }
        .right { text-align: right; }
        .totals { width: 74%; margin: 18pt 0 0 26%; page-break-inside: avoid; }
        .totals th { width: 65%; text-align: left; font-weight: normal; }
        .totals td { width: 35%; text-align: right; }
        .grand-total th, .grand-total td { background: #faf8f3; font-weight: bold; border-top: 2pt solid #ffb511; }
        .handover { margin-top: 18pt; padding: 12pt; background: #faf8f3; page-break-inside: avoid; }
        .notes { margin-top: 16pt; font-size: 8pt; color: #555555; page-break-inside: avoid; }
        a { color: #111111; }
    </style>
</head>
<body>
    <div class="brand">{{ __('shop.brand') }}</div>
    <h1>{{ __('notifications.receipt_title') }}</h1>
    <p><strong>{{ __('notifications.receipt_order') }} {{ $order->number }}</strong></p>
    <p class="meta">{{ __('notifications.receipt_date') }} {{ $issuedAt->format('d/m/Y H:i') }} UTC</p>
    <table class="items">
        <thead>
            <tr>
                <th class="item">{{ __('notifications.receipt_item') }}</th>
                <th class="quantity right">{{ __('notifications.receipt_quantity') }}</th>
                <th class="amount">{{ __('notifications.receipt_unit_price') }}</th>
                <th class="amount">{{ __('notifications.receipt_line_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td class="item">{{ $item->product_name }}<br><span class="small">{{ $item->variant_label }} · {{ $item->sku }}</span></td>
                    <td class="quantity right">{{ $item->quantity }}</td>
                    <td class="amount">{{ $money($item->unit_price_minor) }}</td>
                    <td class="amount">{{ $money($item->line_total_minor) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="totals">
        <tr><th>{{ __('notifications.receipt_subtotal') }}</th><td>{{ $money($order->subtotal_minor) }}</td></tr>
        <tr><th>{{ __('notifications.receipt_shipping') }}</th><td>{{ $money($order->shipping_fee_minor) }}</td></tr>
        <tr><th>{{ __('notifications.receipt_taxes') }}</th><td>{{ $money($order->tax_minor) }}</td></tr>
        <tr class="grand-total"><th>{{ __('notifications.receipt_total') }}</th><td>{{ $money($order->total_minor) }}</td></tr>
        <tr><th>{{ __('notifications.receipt_received') }}</th><td>{{ $money($order->verified_paid_minor) }}</td></tr>
        <tr><th>{{ __('notifications.receipt_refunded') }}</th><td>{{ $money($order->verified_refunded_minor) }}</td></tr>
        <tr><th>{{ __('notifications.receipt_net') }}</th><td>{{ $money($order->verified_net_minor) }}</td></tr>
    </table>
    <div class="handover">
        <h2>{{ __('notifications.receipt_handover') }}</h2>
        <p>{{ $order->fulfillment_status->getLabel() }}</p>
        @if($order->fulfillment)
            <p>{{ $order->fulfillment->handed_over_at->format('d/m/Y H:i') }} UTC</p>
            @if($order->fulfillment->carrier)
                <p>{{ __('notifications.receipt_carrier') }} {{ $order->fulfillment->carrier }}</p>
            @endif
            <p>{{ __('notifications.receipt_reference') }} {{ $order->fulfillment->reference }}</p>
        @endif
    </div>
    <div class="notes">
        <p>{{ __('notifications.receipt_payment_status') }} {{ $order->payment_status->getLabel() }}</p>
        <p>{{ __('notifications.receipt_amounts_as_of') }}</p>
        <p><a href="{{ $trackingUrl }}">{{ __('notifications.view') }}</a></p>
        <p>{{ __('notifications.private_link') }}</p>
        <p>{{ __('notifications.receipt_contact') }} {{ config('communications.contact_email') }}</p>
    </div>
</body>
</html>
