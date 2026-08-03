<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Contracts\PurchaseOrderServiceInterface;
use App\Modules\Orders\Data\PurchaseOrderReceipt;
use App\Modules\Orders\Enums\PurchaseOrderStatus;
use App\Modules\Orders\Jobs\SendPurchaseOrderNotification;
use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Orders\Models\PurchaseOrderLine;
use App\Modules\Punchout\Data\OrderRequestData;
use Illuminate\Support\Facades\DB;

final class PurchaseOrderService implements PurchaseOrderServiceInterface
{
    public function receive(OrderRequestData $data, string $rawPayload): PurchaseOrderReceipt
    {
        $existing = PurchaseOrder::query()->where('po_number', $data->poNumber)->first();

        if ($existing !== null) {
            return new PurchaseOrderReceipt($existing->id, $existing->po_number, $existing->status, wasAlreadyReceived: true);
        }

        $purchaseOrder = DB::transaction(function () use ($data, $rawPayload): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()->create([
                'po_number' => $data->poNumber,
                'order_date' => $data->orderDate,
                'total' => $data->total->toDecimalString(),
                'currency' => $data->total->currency(),
                'buyer_reference' => $data->buyerReference,
                'raw_payload' => $rawPayload,
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

        SendPurchaseOrderNotification::dispatch($purchaseOrder->id);

        return new PurchaseOrderReceipt($purchaseOrder->id, $purchaseOrder->po_number, $purchaseOrder->status, wasAlreadyReceived: false);
    }
}
