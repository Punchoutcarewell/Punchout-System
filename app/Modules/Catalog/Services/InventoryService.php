<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Contracts\InventoryServiceInterface;
use App\Modules\Catalog\Models\Product;

final class InventoryService implements InventoryServiceInterface
{
    public function deduct(string $sku, int $quantity): void
    {
        // A single atomic UPDATE ... SET stock_quantity = stock_quantity - ?,
        // not a read-then-write: the same reason CartService increments
        // quantity this way, two purchase orders for the same SKU landing
        // at the same time must not clobber one another. Matches 0 rows
        // silently when the SKU is not (or no longer) in the catalogue,
        // deliberately not an error, see the interface docblock. Not
        // scoped to is_active: stock is a physical-inventory fact, not a
        // storefront-visibility one, a deactivated product can still have
        // real units ordered against it.
        Product::query()->where('sku', $sku)->decrement('stock_quantity', $quantity);
    }
}
