<?php

declare(strict_types=1);

namespace App\Modules\Storefront\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The two "you shouldn't be here" pages: a direct visit that never
 * carried a token at all, and a token that was presented but didn't
 * resolve (expired, or already transferred). Deliberately two different
 * pages with two different messages, not one generic error, see
 * RequirePunchoutSession for how the distinction is made.
 */
final class PunchoutStateController
{
    public function noToken(): Response
    {
        return Inertia::render('Punchout/NoToken');
    }

    public function sessionExpired(): Response
    {
        return Inertia::render('Punchout/SessionExpired');
    }
}
