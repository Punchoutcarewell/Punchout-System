<?php

declare(strict_types=1);

namespace App\Modules\Punchout;

use App\Modules\Punchout\Console\Commands\PunchoutDoctor;
use App\Modules\Punchout\Console\Commands\SimulateCoupaPunchout;
use App\Modules\Punchout\Contracts\PunchoutLoggerInterface;
use App\Modules\Punchout\Contracts\PunchoutProtocolInterface;
use App\Modules\Punchout\Contracts\SessionManagerInterface;
use App\Modules\Punchout\Cxml\CxmlProtocol;
use App\Modules\Punchout\Http\Middleware\FrameAncestors;
use App\Modules\Punchout\Http\Middleware\PunchoutThrottle;
use App\Modules\Punchout\Http\Middleware\RequirePunchoutSession;
use App\Modules\Punchout\Http\Middleware\ResolvePunchoutSession;
use App\Modules\Punchout\Http\Middleware\RouteMisdirectedCxml;
use App\Modules\Punchout\Services\PunchoutLogger;
use App\Modules\Punchout\Services\SessionManager;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
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
        $this->app->bind(PunchoutLoggerInterface::class, PunchoutLogger::class);

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
        $router->aliasMiddleware('punchout.throttle', PunchoutThrottle::class);

        // Global and first in line, so a cXML body sent to the wrong URL is
        // answered with cXML before any web/admin middleware (login
        // redirects, CSRF) can turn it into an HTML page.
        $this->app->make(HttpKernel::class)->prependMiddleware(RouteMisdirectedCxml::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SimulateCoupaPunchout::class, PunchoutDoctor::class]);
        }
    }
}
