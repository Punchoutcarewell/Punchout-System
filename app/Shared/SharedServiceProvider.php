<?php

declare(strict_types=1);

namespace App\Shared;

use Illuminate\Support\ServiceProvider;

/**
 * Shared has no business logic of its own, only cross-module value
 * objects, exceptions, and now site_settings, the one table no single
 * business module owns. This provider exists solely so that table's
 * migration is self-contained here rather than bolted onto Admin's or
 * Storefront's migration path, following every other module's pattern of
 * owning its own migrations.
 */
final class SharedServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
