<?php

return [
    // Keep the two-country data model; only these markets may accept new public orders.
    'markets' => array_values(array_unique(array_filter(array_map('trim', explode(',', env('STOREFRONT_MARKETS', 'CM')))))),
    'boutique' => [
        'name' => 'Nzhinu Création — Bonaberi',
        'address' => 'Douala, Bonaberi Rail, Marché ChaCha 223',
        'country' => 'Cameroun',
        'street' => 'Bonaberi Rail, Marché ChaCha 223', 'city' => 'Douala', 'country_code' => 'CM',
    ],
];
