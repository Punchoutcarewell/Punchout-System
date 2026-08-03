<?php

declare(strict_types=1);

namespace App\Modules\Cart\Data;

use App\Shared\ValueObjects\Money;

/**
 * One line as shown in the storefront's cart review and sticky cart bar.
 * unitPrice and description are a display cache, captured on the
 * CartItem when the line was added, not necessarily what CartSnapshotFactory
 * sends to Coupa: that re-resolves fresh pricing from Catalog at the
 * moment of transfer, since a stale price transferred to Coupa is worse
 * than one extra query.
 */
final readonly class CartLineSummary
{
    public function __construct(
        public string $sku,
        public string $description,
        public int $quantity,
        public Money $unitPrice,
        public Money $lineTotal,
    ) {}

    /**
     * @return array{sku: string, description: string, quantity: int, unit_price: string, line_total: string, currency: string}
     */
    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice->toDecimalString(),
            'line_total' => $this->lineTotal->toDecimalString(),
            'currency' => $this->unitPrice->currency(),
        ];
    }
}
