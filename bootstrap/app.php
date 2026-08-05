<?php

use App\Shared\Exceptions\DomainValidationException;
use App\Shared\Exceptions\NotFoundException;
use App\Shared\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // This app is never reached directly: every real deployment sits
        // behind a fronting proxy (a Cloudflare Tunnel today, an Azure
        // load balancer or reverse proxy later), so there is no fixed
        // CIDR to trust instead of "all" here, unlike a traditional
        // on-prem setup with a known edge. Without this, X-Forwarded-Proto
        // is ignored: the StartPage URL handed back to Coupa in
        // PunchOutSetupResponse would be generated as http:// even though
        // the buyer only ever reaches this app over https://, and
        // $request->ip() (used to key the punchout-setup/punchout-order
        // rate limiters) would return the proxy's IP for every request
        // rather than the real caller's, collapsing all traffic into one
        // shared bucket.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        // Appended globally, not just to the "web" group: /punchout/setup
        // and /punchout/order deliberately run outside "web" (see
        // Punchout\routes.php) and should still get baseline headers.
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every module's exceptions map to a JSON error shape in one
        // place, rather than each module's HTTP layer wiring this up on
        // its own. Punchout's cXML endpoints render their own Status
        // responses directly in their controllers and never reach here.
        $exceptions->render(function (NotFoundException $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 404)
                : null;
        });

        $exceptions->render(function (DomainValidationException $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : null;
        });
    })->create();
