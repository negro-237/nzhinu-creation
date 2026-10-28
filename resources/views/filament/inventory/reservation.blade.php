<div>
    <h3>Lignes réservées</h3>
    <ul>
        @foreach ($getRecord()->items()->with(['inventory.location', 'inventory.variant'])->get() as $item)
            <li>{{ $item->inventory->location->name }} — {{ $item->inventory->variant->sku }} : {{ $item->quantity }}</li>
        @endforeach
    </ul>
    <h3 style="margin-top:1rem">Historique</h3>
    <ul>
        @foreach ($getRecord()->histories()->orderBy('id')->get() as $history)
            <li>{{ $history->created_at->format('d/m/Y H:i') }} — {{ $history->to_status->getLabel() }} (cycle {{ $history->cycle }}) — {{ $history->reason }}</li>
        @endforeach
    </ul>
</div>
