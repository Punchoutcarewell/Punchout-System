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
| /punchout/start is the one browser-facing route here and opts into the
| "web" group explicitly, since it needs cookies to bind the session.
|
*/

Route::post('/punchout/setup', [SetupController::class, 'handle'])
    ->middleware('throttle:punchout-setup')
    ->name('punchout.setup');

Route::post('/punchout/order', [OrderRequestController::class, 'handle'])
    ->middleware('throttle:punchout-order')
    ->name('punchout.order');

Route::middleware(['web'])
    ->get('/punchout/start', [StartController::class, 'handle'])
    ->name('punchout.start');
