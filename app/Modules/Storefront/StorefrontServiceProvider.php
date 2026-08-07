<?php

declare(strict_types=1);

namespace App\Modules\Storefront;

use App\Modules\Storefront\Http\Middleware\HandleInertiaRequests;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * No migrations, no models of its own: Storefront is the thin composition
 * layer over Catalog, Cart, and Punchout that the architecture calls for.
 */
final class StorefrontServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        /** @var Router $router */
        $router = $this->app['router'];

        $router->aliasMiddleware('storefront.inertia', HandleInertiaRequests::class);
    }
}
