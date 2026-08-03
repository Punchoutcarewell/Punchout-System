<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Data;

use App\Shared\ValueObjects\Money;
use DateTimeImmutable;

/**
 * A parsed inbound OrderRequest, the purchase order Coupa sends back after
 * the buyer submits their requisition. This is what OrderRequestParser
 * hands to the Orders module's PurchaseOrderService, which has no cXML
 * knowledge of its own.
 */
final readonly class OrderRequestData
{
    /**
     * @param  OrderRequestLineData[]  $lines
     */
    public function __construct(
        public string $poNumber,
        public DateTimeImmutable $orderDate,
        public Money $total,
        public ?string $buyerReference,
        public array $lines,
    ) {}
}
