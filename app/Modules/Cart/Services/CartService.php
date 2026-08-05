<?php

declare(strict_types=1);

namespace App\Modules\Cart\Services;

use App\Modules\Cart\Contracts\CartServiceInterface;
use App\Modules\Cart\Data\CartLineSummary;
use App\Modules\Cart\Data\CartSummary;
use App\Modules\Cart\Exceptions\CartItemNotFoundException;
use App\Modules\Cart\Exceptions\CartNotFoundException;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Contracts\PricingServiceInterface;
use App\Modules\Punchout\Data\CartSnapshot;
use App\Shared\Exceptions\DomainValidationException;
use App\Shared\ValueObjects\Money;
use Illuminate\Database\UniqueConstraintViolationException;

final class CartService implements CartServiceInterface
{
    public function __construct(
        private readonly PricingServiceInterface $pricing,
        private readonly CartSnapshotFactory $snapshots,
    ) {}

    public function addItem(int $sessionId, string $sku, int $quantity): CartSummary
    {
        $this->assertValidQuantity($quantity);

        $currency = $this->defaultCurrency();
        $priceSnapshot = $this->pricing->resolveContractPrice($sku, $currency);

        $cart = Cart::query()->firstOrCreate(
            ['session_id' => $sessionId],
            ['total' => '0', 'currency' => $currency],
        );

        $item = CartItem::query()->where('cart_id', $cart->id)->where('sku', $sku)->first();

        if ($item !== null) {
            // An atomic UPDATE ... SET quantity = quantity + ?, not a
            // read-modify-write in PHP: two concurrent adds of the same
            // SKU would otherwise both compute quantity+delta from the
            // same stale read and one increment would be lost.
            $item->increment('quantity', $quantity);
        } else {
            try {
                CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'sku' => $priceSnapshot->sku,
                    'description' => $priceSnapshot->description,
                    'image_path' => $priceSnapshot->imagePath,
                    'unit_price' => $priceSnapshot->contractPrice->toDecimalString(),
                    'currency' => $priceSnapshot->contractPrice->currency(),
                    'quantity' => $quantity,
                    'pack_size' => $priceSnapshot->packSize,
                    'unit_of_measure' => $priceSnapshot->unitOfMeasure,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Lost a race against another request adding the same
                // SKU to the same cart between the check above and this
                // insert (cart_items has a unique(cart_id, sku) index):
                // that request's row now exists, increment it instead of
                // silently dropping this add.
                CartItem::query()->where('cart_id', $cart->id)->where('sku', $sku)->firstOrFail()->increment('quantity', $quantity);
            }
        }

        return $this->recalculate($cart);
    }

    public function updateQuantity(int $sessionId, string $sku, int $quantity): CartSummary
    {
        $this->assertValidQuantity($quantity);

        $cart = $this->requireCart($sessionId);
        $this->requireItem($cart, $sku)->update(['quantity' => $quantity]);

        return $this->recalculate($cart);
    }

    public function removeItem(int $sessionId, string $sku): CartSummary
    {
        $cart = $this->requireCart($sessionId);
        $this->requireItem($cart, $sku)->delete();

        return $this->recalculate($cart);
    }

    public function summary(int $sessionId): CartSummary
    {
        $cart = Cart::query()->where('session_id', $sessionId)->first();

        if ($cart === null) {
            return new CartSummary([], Money::zero($this->defaultCurrency()), 0);
        }

        return $this->buildSummary($cart);
    }

    public function buildTransferSnapshot(int $sessionId): CartSnapshot
    {
        return $this->snapshots->build($this->requireCart($sessionId));
    }

    private function assertValidQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw DomainValidationException::withContext(
                'Quantity must be at least 1.',
                ['quantity' => $quantity],
            );
        }

        $maxQuantity = (int) config('cart.max_quantity', 9999);

        if ($quantity > $maxQuantity) {
            throw DomainValidationException::withContext(
                "Quantity must not exceed {$maxQuantity}.",
                ['quantity' => $quantity, 'max_quantity' => $maxQuantity],
            );
        }
    }

    private function requireCart(int $sessionId): Cart
    {
        $cart = Cart::query()->where('session_id', $sessionId)->first();

        if ($cart === null) {
            throw CartNotFoundException::withContext(
                "No cart exists for session [{$sessionId}].",
                ['session_id' => $sessionId],
            );
        }

        return $cart;
    }

    private function requireItem(Cart $cart, string $sku): CartItem
    {
        $item = CartItem::query()->where('cart_id', $cart->id)->where('sku', $sku)->first();

        if ($item === null) {
            throw CartItemNotFoundException::withContext(
                "SKU [{$sku}] is not in this cart.",
                ['cart_id' => $cart->id, 'sku' => $sku],
            );
        }

        return $item;
    }

    private function recalculate(Cart $cart): CartSummary
    {
        $summary = $this->buildSummary($cart->load('items'));

        $cart->update([
            'total' => $summary->total->toDecimalString(),
            'currency' => $summary->total->currency(),
        ]);

        return $summary;
    }

    private function buildSummary(Cart $cart): CartSummary
    {
        $lines = $cart->items->map(function (CartItem $item): CartLineSummary {
            $unitPrice = Money::fromDecimal($item->unit_price, $item->currency);

            return new CartLineSummary(
                sku: $item->sku,
                description: $item->description,
                imagePath: $item->image_path,
                quantity: $item->quantity,
                unitPrice: $unitPrice,
                lineTotal: $unitPrice->multiply($item->quantity),
                packSize: $item->pack_size,
                unitOfMeasure: $item->unit_of_measure,
            );
        })->all();

        $total = array_reduce(
            $lines,
            fn (Money $carry, CartLineSummary $line): Money => $carry->add($line->lineTotal),
            Money::zero($cart->currency),
        );

        return new CartSummary($lines, $total, (int) $cart->items->sum('quantity'));
    }

    private function defaultCurrency(): string
    {
        return (string) config('cart.default_currency', 'AUD');
    }
}
