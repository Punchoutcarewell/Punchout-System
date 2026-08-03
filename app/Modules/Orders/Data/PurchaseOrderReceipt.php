<?php

declare(strict_types=1);

namespace App\Modules\Orders\Data;

use App\Modules\Orders\Enums\PurchaseOrderStatus;

/**
 * What PurchaseOrderServiceInterface::receive() hands back to Punchout's
 * OrderRequestController: enough to log against, never the PurchaseOrder
 * Eloquent model itself.
 */
final readonly class PurchaseOrderReceipt
{
    public function __construct(
        public int $id,
        public string $poNumber,
        public PurchaseOrderStatus $status,
        public bool $wasAlreadyReceived,
    ) {}
}
