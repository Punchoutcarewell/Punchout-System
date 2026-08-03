<?php

declare(strict_types=1);

namespace App\Modules\Storefront\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The cart review page itself needs no props of its own: the cart
 * summary is already shared globally by HandleInertiaRequests for the
 * sticky cart bar, and the review page reads the exact same shared prop.
 */
final class CartPageController
{
    public function show(): Response
    {
        return Inertia::render('Cart/Review');
    }
}
