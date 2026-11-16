@php($money = fn (?int $amount) => $amount === null ? __('notifications.receipt_unconfirmed') : \App\Support\MoneyInput::fromMinor($amount, $order->currency).' '.$order->currency->value)
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="url('/')">
{{ __('shop.brand') }}
</x-mail::header>
</x-slot:header>
# {{ __('notifications.receipt_title') }}

{{ __('notifications.receipt_intro', ['shop' => __('shop.brand')]) }}

**{{ __('notifications.receipt_order') }} {{ $order->number }}**

{{ __('notifications.receipt_date') }} {{ $issuedAt->format('d/m/Y H:i') }} UTC

<table role="table" width="100%" cellpadding="6" cellspacing="0" style="border-collapse: collapse; table-layout: fixed; font-size: 13px;">
<thead>
<tr style="background-color: #111111; color: #ffb511;">
<th scope="col" align="left" style="width: 40%;">{{ __('notifications.receipt_item') }}</th>
<th scope="col" align="right" style="width: 10%;">{{ __('notifications.receipt_quantity') }}</th>
<th scope="col" align="right" style="width: 25%;">{{ __('notifications.receipt_unit_price') }}</th>
<th scope="col" align="right" style="width: 25%;">{{ __('notifications.receipt_line_total') }}</th>
</tr>
</thead>
<tbody>
@foreach($order->items as $item)
<tr style="border-bottom: 1px solid #dddddd;">
<td style="overflow-wrap: anywhere; word-break: break-word;">{{ $item->product_name }}<br><small>{{ $item->variant_label }} · {{ $item->sku }}</small></td>
<td align="right">{{ $item->quantity }}</td>
<td align="right" style="overflow-wrap: anywhere;">{{ $money($item->unit_price_minor) }}</td>
<td align="right" style="overflow-wrap: anywhere;">{{ $money($item->line_total_minor) }}</td>
</tr>
@endforeach
</tbody>
</table>

<table role="table" width="100%" cellpadding="6" cellspacing="0" style="border-collapse: collapse; font-size: 14px;">
<tbody>
<tr><th scope="row" align="left">{{ __('notifications.receipt_subtotal') }}</th><td align="right">{{ $money($order->subtotal_minor) }}</td></tr>
<tr><th scope="row" align="left">{{ __('notifications.receipt_shipping') }}</th><td align="right">{{ $money($order->shipping_fee_minor) }}</td></tr>
<tr><th scope="row" align="left">{{ __('notifications.receipt_taxes') }}</th><td align="right">{{ $money($order->tax_minor) }}</td></tr>
<tr style="background-color: #faf8f3;"><th scope="row" align="left">{{ __('notifications.receipt_total') }}</th><td align="right"><strong>{{ $money($order->total_minor) }}</strong></td></tr>
<tr><th scope="row" align="left">{{ __('notifications.receipt_received') }}</th><td align="right">{{ $money($order->verified_paid_minor) }}</td></tr>
<tr><th scope="row" align="left">{{ __('notifications.receipt_refunded') }}</th><td align="right">{{ $money($order->verified_refunded_minor) }}</td></tr>
<tr><th scope="row" align="left">{{ __('notifications.receipt_net') }}</th><td align="right">{{ $money($order->verified_net_minor) }}</td></tr>
</tbody>
</table>

{{ __('notifications.receipt_payment_status') }} {{ $order->payment_status->getLabel() }}

{{ __('notifications.receipt_amounts_as_of') }}

## {{ __('notifications.receipt_handover') }}

{{ $order->fulfillment_status->getLabel() }}
@if($order->fulfillment)

{{ $order->fulfillment->handed_over_at->format('d/m/Y H:i') }} UTC
@if($order->fulfillment->carrier)

{{ __('notifications.receipt_carrier') }} {{ $order->fulfillment->carrier }}
@endif

{{ __('notifications.receipt_reference') }} {{ $order->fulfillment->reference }}
@endif

<x-mail::button :url="$trackingUrl">
{{ __('notifications.view') }}
</x-mail::button>

{{ __('notifications.private_link') }}

{{ __('notifications.receipt_contact') }}
@if(config('communications.contact_email'))

{{ config('communications.contact_email') }}
@endif
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ __('shop.brand') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
