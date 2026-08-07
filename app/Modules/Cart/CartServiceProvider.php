<?php

declare(strict_types=1);

namespace App\Modules\Cart;

use App\Modules\Cart\Contracts\CartServiceInterface;
use App\Modules\Cart\Services\CartService;
use Illuminate\Support\ServiceProvider;

final class CartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CartServiceInterface::class, CartService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
