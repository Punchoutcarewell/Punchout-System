<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use App\Modules\Orders\Contracts\PurchaseOrderServiceInterface;
use App\Modules\Orders\Services\PurchaseOrderService;
use Illuminate\Support\ServiceProvider;

/**
 * No routes: Orders has no HTTP surface of its own, it is reached only
 * through PurchaseOrderServiceInterface, called by Punchout's
 * OrderRequestController.
 */
final class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PurchaseOrderServiceInterface::class, PurchaseOrderService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'orders');
    }
}
