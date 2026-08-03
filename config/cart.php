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

];
