<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Contracts\PurchaseOrderServiceInterface;
use App\Modules\Orders\Data\PurchaseOrderReceipt;
use App\Modules\Orders\Enums\PurchaseOrderStatus;
use App\Modules\Orders\Jobs\ReconcilePurchaseOrder;
use App\Modules\Orders\Jobs\SendPurchaseOrderNotification;
use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Orders\Models\PurchaseOrderLine;
use App\Modules\Punchout\Cxml\CxmlSecretRedactor;
use App\Modules\Punchout\Data\OrderRequestData;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class PurchaseOrderService implements PurchaseOrderServiceInterface
{
    public function __construct(private readonly CxmlSecretRedactor $redactor) {}

    public function receive(OrderRequestData $data, string $rawPayload, ?int $punchoutSessionId = null): PurchaseOrderReceipt
    {
        $existing = PurchaseOrder::query()->where('po_number', $data->poNumber)->first();

        if ($existing !== null) {
            return new PurchaseOrderReceipt($existing->id, $existing->po_number, $existing->status, wasAlreadyReceived: true);
        }

        // purchase_orders.raw_payload now that OrderRequest carries a
        // SharedSecret too (see CredentialValidator/C4): the same
        // never-store-a-secret guarantee punchout_logs already has.
        $redactedPayload = $this->redactor->redact($rawPayload);

        try {
            $purchaseOrder = DB::transaction(function () use ($data, $redactedPayload, $punchoutSessionId): PurchaseOrder {
                $purchaseOrder = PurchaseOrder::query()->create([
                    'po_number' => $data->poNumber,
                    'order_date' => $data->orderDate,
                    'total' => $data->total->toDecimalString(),
                    'currency' => $data->total->currency(),
                    'buyer_reference' => $data->buyerReference,
                    'punchout_session_id' => $punchoutSessionId,
                    'raw_payload' => $redactedPayload,
                    'status' => PurchaseOrderStatus::Received,
                    'received_at' => now(),
                ]);

                foreach ($data->lines as $line) {
                    PurchaseOrderLine::query()->create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'line_number' => $line->lineNumber,
                        'supplier_part_id' => $line->supplierPartId,
                        'quantity' => $line->quantity,
                        'unit_price' => $line->unitPrice->toDecimalString(),
                        'unit_of_measure' => $line->unitOfMeasure,
                        'description' => $line->description,
                    ]);
                }

                return $purchaseOrder;
            });
        } catch (UniqueConstraintViolationException) {
            // The early existence check above is the fast path; this is
            // the safety net for the race it cannot close on its own, two
            // requests for the same po_number reaching the check at
            // effectively the same time. Whichever loses the insert
            // treats the winner's row as the real one, the same idempotent
            // outcome as if it had arrived a moment later.
            $existing = PurchaseOrder::query()->where('po_number', $data->poNumber)->firstOrFail();

            return new PurchaseOrderReceipt($existing->id, $existing->po_number, $existing->status, wasAlreadyReceived: true);
        }

        SendPurchaseOrderNotification::dispatch($purchaseOrder->id);
        ReconcilePurchaseOrder::dispatch($purchaseOrder->id);

        return new PurchaseOrderReceipt($purchaseOrder->id, $purchaseOrder->po_number, $purchaseOrder->status, wasAlreadyReceived: false);
    }
}
