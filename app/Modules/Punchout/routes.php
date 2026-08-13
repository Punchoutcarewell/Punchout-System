<?php

declare(strict_types=1);

use App\Modules\Punchout\Http\Controllers\OrderRequestController;
use App\Modules\Punchout\Http\Controllers\SetupController;
use App\Modules\Punchout\Http\Controllers\StartController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Punchout module routes
|--------------------------------------------------------------------------
|
| Everything here lives under /api/punchout: GPCS's own convention for
| this deployment is that anything processing raw XML rather than serving
| HTML sits under /api, cXML is exactly that, whether or not the caller
| is a browser.
|
| Loaded via loadRoutesFrom() in PunchoutServiceProvider, which does not
| wrap these routes in Laravel's automatic "web" middleware group the way
| routes/web.php is. That is deliberate: /api/punchout/setup and
| /api/punchout/order are raw cXML endpoints Coupa's server posts to
| directly, no CSRF token, no cookie-based session, no HTML error pages,
| and the only way to guarantee that is for them to start with no
| middleware at all rather than trying to strip web-group middleware back
| off afterward.
|
| GET /api/punchout/setup/{token} is the one browser-facing route here
| and opts into the "web" group explicitly, since it needs cookies to
| bind the session. It deliberately shares a path prefix with the POST
| /api/punchout/setup above, that is a different route (disambiguated by
| HTTP method, an exact "/api/punchout/setup" match never matches the
| "/api/punchout/setup/{token}" pattern), not a naming accident: this is
| still the same PunchOutSetupRequest/StartPage handshake from cXML's
| point of view, just the two different legs of it. The route name
| stays punchout.start, that is what StartController's docblock and
| every route('punchout.start', ...) call site expect, only the URL
| shape changed.
|
| {token} also doubles as a credential's shared secret, letting Coupa
| reach the storefront with a single GET and no cXML POST first. See
| StartController.
|
*/

Route::post('/api/punchout/setup', [SetupController::class, 'handle'])
    ->middleware('punchout.throttle:punchout-setup')
    ->name('punchout.setup');

Route::post('/api/punchout/order', [OrderRequestController::class, 'handle'])
    ->middleware('punchout.throttle:punchout-order')
    ->name('punchout.order');

Route::middleware(['web'])
    ->get('/api/punchout/setup/{token}', [StartController::class, 'handle'])
    ->name('punchout.start');
