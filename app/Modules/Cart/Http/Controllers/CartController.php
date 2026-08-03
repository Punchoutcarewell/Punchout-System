<?php

declare(strict_types=1);

namespace App\Modules\Cart\Http\Controllers;

use App\Modules\Cart\Contracts\CartServiceInterface;
use App\Modules\Punchout\Contracts\SessionManagerInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The small same-origin JSON API behind the storefront's quantity
 * stepper and sticky cart bar: instant feedback on add/update/remove
 * without a full Inertia page visit. Full page navigation (category to
 * product to cart review) stays on real Inertia visits once the
 * Storefront module exists; this controller is only for the interactive
 * bits.
 *
 * Runs behind punchout.resolve-session and punchout.require-session (see
 * routes.php), so a bound session is guaranteed by the time any action
 * here runs.
 */
final class CartController
{
    public function __construct(
        private readonly CartServiceInterface $cart,
        private readonly SessionManagerInterface $sessions,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json($this->cart->summary($this->currentSessionId())->toArray());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $summary = $this->cart->addItem($this->currentSessionId(), $data['sku'], $data['quantity']);

        return response()->json($summary->toArray());
    }

    public function update(Request $request, string $sku): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $summary = $this->cart->updateQuantity($this->currentSessionId(), $sku, $data['quantity']);

        return response()->json($summary->toArray());
    }

    public function destroy(string $sku): JsonResponse
    {
        $summary = $this->cart->removeItem($this->currentSessionId(), $sku);

        return response()->json($summary->toArray());
    }

    private function currentSessionId(): int
    {
        $session = $this->sessions->current();

        if ($session === null) {
            // Should be unreachable: punchout.require-session already
            // rejects the request before this controller runs. Guarded
            // anyway rather than trusting route wiring never changes.
            throw new RuntimeException('CartController reached with no bound punchout session.');
        }

        return $session->id;
    }
}
