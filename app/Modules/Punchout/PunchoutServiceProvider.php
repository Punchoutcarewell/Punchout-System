<?php

declare(strict_types=1);

namespace App\Modules\Punchout;

use App\Modules\Punchout\Console\Commands\SimulateCoupaPunchout;
use App\Modules\Punchout\Contracts\PunchoutProtocolInterface;
use App\Modules\Punchout\Contracts\SessionManagerInterface;
use App\Modules\Punchout\Cxml\CxmlProtocol;
use App\Modules\Punchout\Http\Middleware\FrameAncestors;
use App\Modules\Punchout\Http\Middleware\RequirePunchoutSession;
use App\Modules\Punchout\Http\Middleware\ResolvePunchoutSession;
use App\Modules\Punchout\Services\SessionManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Punchout module into the application: container bindings,
 * migrations, routes, and middleware aliases all register from here, so
 * nothing about this module needs to be scattered across bootstrap/app.php
 * or a central kernel file. Every other module follows this same pattern.
 */
final class PunchoutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PunchoutProtocolInterface::class, CxmlProtocol::class);

        // Singleton so bind()/current() hold the same SessionManager instance
        // across the whole request, not a fresh one per resolution.
        $this->app->singleton(SessionManagerInterface::class, SessionManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        /** @var Router $router */
        $router = $this->app['router'];

        $router->aliasMiddleware('punchout.resolve-session', ResolvePunchoutSession::class);
        $router->aliasMiddleware('punchout.require-session', RequirePunchoutSession::class);
        $router->aliasMiddleware('punchout.frame-ancestors', FrameAncestors::class);

        // Both endpoints are public and accept arbitrary XML from the
        // internet; rate limiting by IP is a cheap first line of defense
        // against abuse independent of credential validation.
        RateLimiter::for('punchout-setup', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('punchout-order', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));

        if ($this->app->runningInConsole()) {
            $this->commands([SimulateCoupaPunchout::class]);
        }
    }
}
