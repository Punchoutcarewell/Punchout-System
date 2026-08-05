<?php

use App\Modules\Admin\AdminPanelProvider;
use App\Modules\Cart\CartServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Orders\OrdersServiceProvider;
use App\Modules\Punchout\PunchoutServiceProvider;
use App\Modules\Storefront\StorefrontServiceProvider;
use App\Providers\AppServiceProvider;
use App\Shared\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    PunchoutServiceProvider::class,
    CatalogServiceProvider::class,
    CartServiceProvider::class,
    OrdersServiceProvider::class,
    AdminPanelProvider::class,
    StorefrontServiceProvider::class,
];
