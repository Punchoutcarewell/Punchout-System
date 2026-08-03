<?php

declare(strict_types=1);

namespace App\Modules\Storefront\Http\Middleware;

use App\Modules\Cart\Contracts\CartServiceInterface;
use App\Modules\Punchout\Contracts\SessionManagerInterface;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Shares the session rail's data (whose session this is, how long is
 * left) and the cart summary (for the sticky cart bar) on every Inertia
 * response, so individual page controllers never repeat this. Both are
 * null-safe: the "no session" and "session expired" pages render through
 * this same middleware with nothing to share.
 */
final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly SessionManagerInterface $sessions,
        private readonly CartServiceInterface $cart,
    ) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $session = $this->sessions->current();

        return [
            ...parent::share($request),
            'punchoutSession' => $session === null ? null : [
                'buyerName' => $session->buyer_unique_name ?? $session->buyer_user_email,
                'businessUnit' => $session->buyer_business_unit,
                'expiresAt' => $session->expires_at->toIso8601String(),
            ],
            'cart' => $session === null ? null : $this->cart->summary($session->id)->toArray(),
        ];
    }
}
