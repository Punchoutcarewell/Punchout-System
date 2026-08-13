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
| Loaded via loadRoutesFrom() in PunchoutServiceProvider, which does not
| wrap these routes in Laravel's automatic "web" middleware group the way
| routes/web.php is. That is deliberate: /punchout/setup and /punchout/order
| are raw cXML endpoints Coupa's server posts to directly, no CSRF token,
| no cookie-based session, no HTML error pages, and the only way to
| guarantee that is for them to start with no middleware at all rather
| than trying to strip web-group middleware back off afterward.
|
| GET /punchout/setup/{token} is the one browser-facing route here and
| opts into the "web" group explicitly, since it needs cookies to bind
| the session. It deliberately shares a path prefix with the POST
| /punchout/setup above, that is a different route (disambiguated by
| HTTP method, an exact "/punchout/setup" match never matches the
| "/punchout/setup/{token}" pattern), not a naming accident: this is
| still the same PunchOutSetupRequest/StartPage handshake from cXML's
| point of view, just the two different legs of it. The route name
| stays punchout.start, that is what StartController's docblock and
| every route('punchout.start', ...) call site expect, only the URL
| shape changed.
|
*/

Route::post('/punchout/setup', [SetupController::class, 'handle'])
    ->middleware('punchout.throttle:punchout-setup')
    ->name('punchout.setup');

Route::post('/punchout/order', [OrderRequestController::class, 'handle'])
    ->middleware('punchout.throttle:punchout-order')
    ->name('punchout.order');

Route::middleware(['web'])
    ->get('/punchout/setup/{token}', [StartController::class, 'handle'])
    ->name('punchout.start');
