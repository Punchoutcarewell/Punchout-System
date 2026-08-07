<?php

declare(strict_types=1);

namespace App\Modules\Storefront\Http\Middleware;

use App\Modules\Cart\Contracts\CartServiceInterface;
use App\Modules\Punchout\Contracts\SessionManagerInterface;
use App\Shared\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

/**
 * Shares the session rail's data (whose session this is, how long is
 * left), the cart summary (for the sticky cart bar), and the configured
 * site logo on every Inertia response, so individual page controllers
 * never repeat this. Session and cart are null-safe: the "no session" and
 * "session expired" pages render through this same middleware with
 * nothing to share for either.
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
            // A plain closure, not eagerly evaluated: still runs on every
            // full page visit (StorefrontLayout hydrates the cart store
            // from this prop on load, same as before), but Inertia skips
            // invoking it on a partial reload that does not request
            // "cart", instead of running CartService::summary() on every
            // partial regardless of relevance.
            'cart' => fn () => $session === null ? null : $this->cart->summary($session->id)->toArray(),
            'siteLogoUrl' => fn (): ?string => SiteSetting::current()->logo_path !== null
                ? Storage::disk('public')->url(SiteSetting::current()->logo_path)
                : null,
        ];
    }
}
