<?php

declare(strict_types=1);

use App\Modules\Cart\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cart module routes
|--------------------------------------------------------------------------
|
| The small same-origin JSON API described on CartController, kept under
| its own /storefront/cart-api prefix so it never collides with the
| Storefront module's own page routes (the Inertia cart review page lives
| at the more natural /storefront/cart). Runs on the standard "web" group
| (cookies, CSRF) plus Punchout's session middleware: a request with no
| bound punchout session never reaches these actions.
|
*/

Route::middleware(['web', 'punchout.resolve-session', 'punchout.require-session'])
    ->prefix('storefront/cart-api')
    ->group(function (): void {
        Route::get('/', [CartController::class, 'show'])->name('cart.show');
        Route::post('/items', [CartController::class, 'store'])->name('cart.items.store');
        Route::patch('/items/{sku}', [CartController::class, 'update'])->name('cart.items.update');
        Route::delete('/items/{sku}', [CartController::class, 'destroy'])->name('cart.items.destroy');
    });
