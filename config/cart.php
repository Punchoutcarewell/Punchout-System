<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default currency
    |--------------------------------------------------------------------------
    |
    | Every price lookup Cart makes against Catalog requests this currency.
    | PricingService refuses to convert currency, so this must match the
    | currency Carewell's contract prices are actually stored in. Whether
    | pricing varies by business unit or country is still an open question
    | for GPCS (see the roadmap's blocking questions), single-currency is
    | this project's working assumption until that is answered.
    |
    */

    'default_currency' => env('CART_DEFAULT_CURRENCY', 'AUD'),

    /*
    |--------------------------------------------------------------------------
    | Maximum line quantity
    |--------------------------------------------------------------------------
    |
    | cart_items.quantity is an unsigned integer with no cap of its own, a
    | typo or a scripted abuse attempt could otherwise set a line to
    | billions of units. No real Carewell order needs anywhere near this
    | many units of anything on one line; this is a sanity ceiling, not a
    | business rule GPCS needs to confirm.
    |
    */

    'max_quantity' => env('CART_MAX_QUANTITY', 9999),

];
