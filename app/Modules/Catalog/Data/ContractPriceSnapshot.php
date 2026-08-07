<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Shared\ValueObjects\Money;
use App\Shared\ValueObjects\UnspscCode;

/**
 * The price and classification a punchout buyer should see for a SKU
 * right now. This is what Cart and Punchout see of Catalog's data, never
 * the Product or ContractPrice Eloquent models themselves.
 */
final readonly class ContractPriceSnapshot
{
    public function __construct(
        public string $sku,
        public string $supplierPartId,
        public Money $contractPrice,
        public Money $listPrice,
        public string $unitOfMeasure,
        public UnspscCode $unspscCode,
        public int $leadTimeDays,
        public string $description,
        public ?string $supplierPartAuxiliaryId,
        public ?string $manufacturerPartId,
        public ?string $manufacturerName,
        /** Null means not sold in packs, see Product::$pack_size. */
        public ?int $packSize,
        public ?string $imagePath,
    ) {}
}
