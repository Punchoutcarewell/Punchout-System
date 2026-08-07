<?php

declare(strict_types=1);

use App\Modules\Orders\Enums\PurchaseOrderStatus;
use App\Modules\Orders\Jobs\ReconcilePurchaseOrder;
use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Orders\Models\PurchaseOrderLine;
use App\Modules\Orders\Services\PurchaseOrderReconciler;

it('loads the purchase order with its lines and reconciles it when run', function () {
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '25.99', 'currency' => 'AUD']);

    $purchaseOrder = PurchaseOrder::query()->create([
        'po_number' => 'PO-1',
        'order_date' => now(),
        'total' => '99.99',
        'currency' => 'AUD',
        'buyer_reference' => null,
        'raw_payload' => '<raw-xml/>',
        'status' => PurchaseOrderStatus::Received,
        'received_at' => now(),
    ]);

    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_number' => 1,
        'supplier_part_id' => $product->sku,
        'quantity' => 1,
        'unit_price' => '25.99',
        'unit_of_measure' => 'BX',
        'description' => $product->name,
    ]);

    (new ReconcilePurchaseOrder($purchaseOrder->id))->handle(app(PurchaseOrderReconciler::class));

    expect($purchaseOrder->fresh()->has_discrepancy)->toBeTrue();
});

it('retries up to 3 times with increasing backoff', function () {
    $job = new ReconcilePurchaseOrder(1);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([60, 300, 900]);
});
