<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Shared\ValueObjects\Money;
use App\Shared\ValueObjects\UnspscCode;

/**
 * One row of a category browse or search result. Deliberately carries
 * list price only, not contract price: resolving contract pricing belongs
 * to PricingServiceInterface, kept separate here since accuracy there
 * matters more than saving a query on a results grid.
 */
final readonly class ProductSummary
{
    public function __construct(
        public int $id,
        public string $sku,
        public string $name,
        public ?string $categoryName,
        public UnspscCode $unspscCode,
        public Money $listPrice,
        public string $unitOfMeasure,
        public int $packSize,
        public int $leadTimeDays,
        public ?string $imagePath,
    ) {}
}
