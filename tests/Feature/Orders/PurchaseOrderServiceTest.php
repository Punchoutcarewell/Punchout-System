<?php

declare(strict_types=1);

use App\Modules\Orders\Enums\PurchaseOrderStatus;
use App\Modules\Orders\Jobs\SendPurchaseOrderNotification;
use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Orders\Services\PurchaseOrderService;
use Illuminate\Support\Facades\Queue;

it('creates a PurchaseOrder with its lines and queues a notification', function () {
    Queue::fake();

    $receipt = (new PurchaseOrderService)->receive(sampleOrderRequestData('PO-1'), '<raw-xml/>');

    expect($receipt->poNumber)->toBe('PO-1')
        ->and($receipt->status)->toBe(PurchaseOrderStatus::Received)
        ->and($receipt->wasAlreadyReceived)->toBeFalse();

    $purchaseOrder = PurchaseOrder::query()->where('po_number', 'PO-1')->firstOrFail();

    expect($purchaseOrder->total)->toBe('25.99')
        ->and($purchaseOrder->currency)->toBe('AUD')
        ->and($purchaseOrder->buyer_reference)->toBe('REQ-123')
        ->and($purchaseOrder->raw_payload)->toBe('<raw-xml/>')
        ->and($purchaseOrder->status)->toBe(PurchaseOrderStatus::Received)
        ->and($purchaseOrder->lines)->toHaveCount(1);

    $line = $purchaseOrder->lines->first();
    expect($line->supplier_part_id)->toBe('CW-4021')
        ->and($line->quantity)->toBe(1)
        ->and($line->unit_price)->toBe('25.99')
        ->and($line->unit_of_measure)->toBe('BX');

    Queue::assertPushed(SendPurchaseOrderNotification::class, fn ($job) => $job->purchaseOrderId === $purchaseOrder->id);
});

it('is idempotent on a repeated po_number: no duplicate order, no second notification', function () {
    Queue::fake();

    $service = new PurchaseOrderService;
    $service->receive(sampleOrderRequestData('PO-DUPLICATE'), '<raw-xml/>');
    $receipt = $service->receive(sampleOrderRequestData('PO-DUPLICATE'), '<raw-xml/>');

    expect($receipt->wasAlreadyReceived)->toBeTrue()
        ->and(PurchaseOrder::query()->where('po_number', 'PO-DUPLICATE')->count())->toBe(1);

    Queue::assertPushed(SendPurchaseOrderNotification::class, 1);
});
