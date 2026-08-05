<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Shared\ValueObjects\Money;
use App\Shared\ValueObjects\UnspscCode;

/**
 * The full product detail page's descriptive data. Deliberately still
 * carries list price only, not contract price: PricingServiceInterface
 * is the source for that, kept separate for the same reason as
 * ProductSummary.
 */
final readonly class ProductDetail
{
    public function __construct(
        public string $sku,
        public string $name,
        public string $description,
        public ?string $longDescription,
        public ?string $categoryName,
        public UnspscCode $unspscCode,
        public string $unitOfMeasure,
        /** Null means not sold in packs, see Product::$pack_size. */
        public ?int $packSize,
        public int $leadTimeDays,
        public Money $listPrice,
        public ?string $imagePath,
        public ?string $manufacturerName,
        public ?string $manufacturerPartId,
    ) {}
}
