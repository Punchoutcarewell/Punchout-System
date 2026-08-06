<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Console\Commands\ImportCatalog;
use App\Modules\Catalog\Console\Commands\ValidateCatalog;
use App\Modules\Catalog\Contracts\CatalogSearchInterface;
use App\Modules\Catalog\Contracts\InventoryServiceInterface;
use App\Modules\Catalog\Contracts\PricingServiceInterface;
use App\Modules\Catalog\Services\CatalogSearchService;
use App\Modules\Catalog\Services\InventoryService;
use App\Modules\Catalog\Services\PricingService;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Catalog module: container bindings, migrations, and console
 * commands. No routes, this module has no HTTP surface of its own, it is
 * a service layer other modules (and eventually Storefront's controllers)
 * consume through its Contracts.
 */
final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PricingServiceInterface::class, PricingService::class);
        $this->app->bind(CatalogSearchInterface::class, CatalogSearchService::class);
        $this->app->bind(InventoryServiceInterface::class, InventoryService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([ImportCatalog::class, ValidateCatalog::class]);
        }
    }
}
