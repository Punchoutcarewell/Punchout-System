<?php

declare(strict_types=1);

namespace App\Modules\Cart\Data;

use App\Shared\ValueObjects\Money;

/**
 * One line as shown in the storefront's cart review and sticky cart bar.
 * unitPrice, description, imagePath, packSize, and unitOfMeasure are a
 * display cache, captured on the CartItem when the line was added, not
 * necessarily what CartSnapshotFactory sends to Coupa: that re-resolves
 * fresh pricing from Catalog at the moment of transfer, since a stale
 * price transferred to Coupa is worse than one extra query.
 *
 * packSize null means this line is not sold in packs: quantity is a
 * plain count of unitOfMeasure units and unitPrice is per unit. A real
 * packSize means quantity counts packs and unitPrice is per pack, see
 * Catalog\Models\Product::$pack_size.
 */
final readonly class CartLineSummary
{
    public function __construct(
        public string $sku,
        public string $description,
        public ?string $imagePath,
        public int $quantity,
        public Money $unitPrice,
        public Money $lineTotal,
        public ?int $packSize,
        public ?string $unitOfMeasure,
    ) {}

    /**
     * @return array{sku: string, description: string, imagePath: string|null, quantity: int, unitPrice: Money, lineTotal: Money, packSize: int|null, unitOfMeasure: string|null}
     */
    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'description' => $this->description,
            'imagePath' => $this->imagePath,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice,
            'lineTotal' => $this->lineTotal,
            'packSize' => $this->packSize,
            'unitOfMeasure' => $this->unitOfMeasure,
        ];
    }
}
