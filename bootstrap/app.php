<?php

use App\Shared\Exceptions\DomainValidationException;
use App\Shared\Exceptions\NotFoundException;
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
        //
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
