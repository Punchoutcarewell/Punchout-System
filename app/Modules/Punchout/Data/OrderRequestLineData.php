<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Data;

use App\Shared\ValueObjects\Money;

/**
 * One line of a purchase order received from Coupa.
 */
final readonly class OrderRequestLineData
{
    public function __construct(
        public int $lineNumber,
        public string $supplierPartId,
        public int $quantity,
        public Money $unitPrice,
        public string $unitOfMeasure,
        public string $description,
    ) {}
}
