<?php

use App\Modules\Admin\AdminPanelProvider;
use App\Modules\Cart\CartServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Orders\OrdersServiceProvider;
use App\Modules\Punchout\PunchoutServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    PunchoutServiceProvider::class,
    CatalogServiceProvider::class,
    CartServiceProvider::class,
    OrdersServiceProvider::class,
    AdminPanelProvider::class,
];
