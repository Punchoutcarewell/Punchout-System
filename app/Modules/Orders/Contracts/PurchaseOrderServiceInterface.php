<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

use App\Modules\Orders\Data\PurchaseOrderReceipt;
use App\Modules\Punchout\Data\OrderRequestData;

/**
 * What Punchout\Http\Controllers\OrderRequestController depends on to turn
 * a parsed OrderRequest into a business record. This is the one specific
 * crossing point the architecture calls for: Punchout hands over its own
 * parsed DTO, Orders has no cXML knowledge of its own.
 */
interface PurchaseOrderServiceInterface
{
    /**
     * Idempotent on po_number: a resend of the same PO (a Coupa retry, a
     * repeat test during Stage 5) never creates a duplicate record or
     * fires a second notification email.
     *
     * $punchoutSessionId is opaque here, exactly like CartServiceInterface's
     * $sessionId: this module never loads a Punchout\Models\PunchoutSession,
     * it only ever stores or reads the plain int the caller resolved.
     */
    public function receive(OrderRequestData $data, string $rawPayload, ?int $punchoutSessionId = null): PurchaseOrderReceipt;
}
