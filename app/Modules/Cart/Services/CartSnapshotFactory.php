<?php

declare(strict_types=1);

namespace App\Modules\Cart\Services;

use App\Modules\Cart\Exceptions\EmptyCartException;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Contracts\PricingServiceInterface;
use App\Modules\Punchout\Data\CartLineSnapshot;
use App\Modules\Punchout\Data\CartSnapshot;

/**
 * Builds Punchout's protocol-neutral CartSnapshot from this module's own
 * Cart at the moment of transfer. Deliberately re-resolves every line's
 * price, description, and classification fresh from
 * PricingServiceInterface rather than reading CartItem's cached display
 * fields: a stale price transferred to Coupa is a worse defect than one
 * extra query per line, and the Category Manager's go-live check is
 * exactly a line-by-line price comparison against the contract.
 *
 * This is the class the future Storefront TransferController calls; it
 * has no HTTP knowledge of its own.
 */
final class CartSnapshotFactory
{
    public function __construct(private readonly PricingServiceInterface $pricing) {}

    public function build(Cart $cart): CartSnapshot
    {
        $cart->loadMissing('items');

        if ($cart->items->isEmpty()) {
            throw EmptyCartException::withContext(
                "Cart [{$cart->id}] has no items to transfer.",
                ['cart_id' => $cart->id],
            );
        }

        $priceSnapshots = $this->pricing->resolveContractPrices($cart->items->pluck('sku')->all(), $cart->currency);

        $lines = $cart->items->map(function (CartItem $item) use ($priceSnapshots): CartLineSnapshot {
            $priceSnapshot = $priceSnapshots[$item->sku];

            return new CartLineSnapshot(
                supplierPartId: $priceSnapshot->supplierPartId,
                supplierPartAuxiliaryId: $priceSnapshot->supplierPartAuxiliaryId,
                quantity: $item->quantity,
                unitPrice: $priceSnapshot->contractPrice,
                description: $priceSnapshot->description,
                unitOfMeasure: $priceSnapshot->unitOfMeasure,
                unspscCode: $priceSnapshot->unspscCode,
                manufacturerPartId: $priceSnapshot->manufacturerPartId,
                manufacturerName: $priceSnapshot->manufacturerName,
                leadTimeDays: $priceSnapshot->leadTimeDays,
            );
        })->all();

        return new CartSnapshot($lines, $cart->currency);
    }
}
