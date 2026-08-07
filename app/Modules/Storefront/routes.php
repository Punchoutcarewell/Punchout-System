<?php

declare(strict_types=1);

use App\Modules\Storefront\Http\Controllers\CartPageController;
use App\Modules\Storefront\Http\Controllers\CatalogPageController;
use App\Modules\Storefront\Http\Controllers\ProductPageController;
use App\Modules\Storefront\Http\Controllers\PunchoutStateController;
use App\Modules\Storefront\Http\Controllers\TransferPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront module routes
|--------------------------------------------------------------------------
|
| Every real storefront page runs behind the full stack: "web" for
| cookies/CSRF, Punchout's session middleware so a request with no valid
| session never reaches storefront content, storefront.inertia to share
| the session rail and cart data, and frame-ancestors since this all
| renders inside Coupa's iframe.
|
| The two state pages deliberately do NOT carry punchout.require-session,
| that middleware is what redirects here in the first place, requiring it
| on its own destination would be an infinite redirect loop.
|
*/

$fullStack = ['web', 'punchout.resolve-session', 'punchout.require-session', 'storefront.inertia', 'punchout.frame-ancestors'];
$stateStack = ['web', 'punchout.resolve-session', 'storefront.inertia', 'punchout.frame-ancestors'];

Route::middleware($fullStack)->group(function (): void {
    Route::get('/storefront', [CatalogPageController::class, 'index'])->name('storefront.catalog');
    Route::get('/storefront/products/{sku}', [ProductPageController::class, 'show'])->name('storefront.products.show');
    Route::get('/storefront/cart', [CartPageController::class, 'show'])->name('storefront.cart');
    Route::post('/storefront/transfer', [TransferPageController::class, 'store'])->name('storefront.transfer');
});

Route::middleware($stateStack)->group(function (): void {
    Route::get('/storefront/no-token', [PunchoutStateController::class, 'noToken'])->name('storefront.no-token');
    Route::get('/storefront/session-expired', [PunchoutStateController::class, 'sessionExpired'])->name('storefront.session-expired');
});
