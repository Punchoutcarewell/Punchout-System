<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Data;

use App\Shared\ValueObjects\Money;
use App\Shared\ValueObjects\UnspscCode;

/**
 * One cart line, in the protocol-neutral shape OrderMessageBuilder needs
 * to render an ItemIn element. This is the contract the future Cart
 * module's CartSnapshotFactory must produce: Cart owns quantities and
 * totals, Catalog owns pricing and classification, but neither of them
 * needs to know cXML exists. Only this module does.
 */
final readonly class CartLineSnapshot
{
    public function __construct(
        public string $supplierPartId,
        public ?string $supplierPartAuxiliaryId,
        public int $quantity,
        public Money $unitPrice,
        public string $description,
        public string $unitOfMeasure,
        public UnspscCode $unspscCode,
        public ?string $manufacturerPartId,
        public ?string $manufacturerName,
        public int $leadTimeDays,
    ) {}

    public function lineTotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
