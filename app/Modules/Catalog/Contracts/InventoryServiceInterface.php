<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

/**
 * What Orders depends on to keep Product.stock_quantity in sync with what
 * has actually been ordered, never on the Product model directly. Orders
 * intentionally has no FK to Catalog (a purchase order line's
 * supplier_part_id can outlive the catalogue product it once matched, see
 * PurchaseOrderLine's own docblock), so this is the one place that
 * translates "a SKU string was ordered" into a stock change.
 */
interface InventoryServiceInterface
{
    /**
     * Decrements stock_quantity for the product matching $sku by $quantity.
     * Never throws and never blocks: an unknown SKU is a no-op (Orders'
     * own reconciliation flags a missing catalogue match separately, this
     * has nothing to deduct against), and a quantity larger than what is
     * on hand is allowed to take stock_quantity negative rather than being
     * capped at zero. That negative value is deliberate, see
     * Product::shortfallQuantity().
     */
    public function deduct(string $sku, int $quantity): void;
}
