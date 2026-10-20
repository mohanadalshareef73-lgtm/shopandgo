<?php

return [
    // Match the provider account's settings.storefronts.amazon; never select a
    // storefront from the customer's URL or send a region override to the API.
    'storefronts' => [
        'amazon' => env('CART_AMAZON_STOREFRONT', 'AMZ-AE'),
    ],
];
