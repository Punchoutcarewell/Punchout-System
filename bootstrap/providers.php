<?php

use App\Modules\Cart\CartServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Punchout\PunchoutServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    PunchoutServiceProvider::class,
    CatalogServiceProvider::class,
    CartServiceProvider::class,
];
