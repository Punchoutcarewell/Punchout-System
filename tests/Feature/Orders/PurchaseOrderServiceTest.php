<?php

declare(strict_types=1);

use App\Modules\Orders\Enums\PurchaseOrderStatus;
use App\Modules\Orders\Jobs\ReconcilePurchaseOrder;
use App\Modules\Orders\Jobs\SendPurchaseOrderNotification;
use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Orders\Services\PurchaseOrderService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('creates a PurchaseOrder with its lines and queues a notification', function () {
    Queue::fake();

    $receipt = (app(PurchaseOrderService::class))->receive(sampleOrderRequestData('PO-1'), '<raw-xml/>');

    expect($receipt->poNumber)->toBe('PO-1')
        ->and($receipt->status)->toBe(PurchaseOrderStatus::Received)
        ->and($receipt->wasAlreadyReceived)->toBeFalse();

    $purchaseOrder = PurchaseOrder::query()->where('po_number', 'PO-1')->firstOrFail();

    expect($purchaseOrder->total)->toBe('25.99')
        ->and($purchaseOrder->currency)->toBe('AUD')
        ->and($purchaseOrder->buyer_reference)->toBe('REQ-123')
        ->and($purchaseOrder->raw_payload)->toContain('<raw-xml/>')
        ->and($purchaseOrder->status)->toBe(PurchaseOrderStatus::Received)
        ->and($purchaseOrder->lines)->toHaveCount(1);

    $line = $purchaseOrder->lines->first();
    expect($line->supplier_part_id)->toBe('CW-4021')
        ->and($line->quantity)->toBe(1)
        ->and($line->unit_price)->toBe('25.99')
        ->and($line->unit_of_measure)->toBe('BX');

    Queue::assertPushed(SendPurchaseOrderNotification::class, fn ($job) => $job->purchaseOrderId === $purchaseOrder->id);
    Queue::assertPushed(ReconcilePurchaseOrder::class, fn ($job) => $job->purchaseOrderId === $purchaseOrder->id);
});

it('stores the punchout_session_id when the caller resolved one', function () {
    Queue::fake();
    $session = issueTestPunchoutSession();

    app(PurchaseOrderService::class)->receive(sampleOrderRequestData('PO-LINKED'), '<raw-xml/>', $session->id);

    expect(PurchaseOrder::query()->where('po_number', 'PO-LINKED')->firstOrFail()->punchout_session_id)->toBe($session->id);
});

it('leaves punchout_session_id null when the caller could not resolve one, which is the expected default case', function () {
    Queue::fake();

    app(PurchaseOrderService::class)->receive(sampleOrderRequestData('PO-UNLINKED'), '<raw-xml/>');

    expect(PurchaseOrder::query()->where('po_number', 'PO-UNLINKED')->firstOrFail()->punchout_session_id)->toBeNull();
});

it('is idempotent on a repeated po_number: no duplicate order, no second notification', function () {
    Queue::fake();

    $service = app(PurchaseOrderService::class);
    $service->receive(sampleOrderRequestData('PO-DUPLICATE'), '<raw-xml/>');
    $receipt = $service->receive(sampleOrderRequestData('PO-DUPLICATE'), '<raw-xml/>');

    expect($receipt->wasAlreadyReceived)->toBeTrue()
        ->and(PurchaseOrder::query()->where('po_number', 'PO-DUPLICATE')->count())->toBe(1);

    Queue::assertPushed(SendPurchaseOrderNotification::class, 1);
});

it('never stores the shared secret in purchase_orders.raw_payload', function () {
    Queue::fake();

    $rawPayload = <<<'XML'
    <cXML><Header><Sender><Credential domain="DUNS"><Identity>COUPA1</Identity><SharedSecret>ALD</SharedSecret></Credential></Sender></Header></cXML>
    XML;

    app(PurchaseOrderService::class)->receive(sampleOrderRequestData('PO-SECRET'), $rawPayload);

    $purchaseOrder = PurchaseOrder::query()->where('po_number', 'PO-SECRET')->firstOrFail();

    expect($purchaseOrder->raw_payload)->toContain('[REDACTED]')
        ->and($purchaseOrder->raw_payload)->not->toContain('ALD');
});

it('treats a unique-constraint violation on a concurrently inserted po_number as an idempotent hit, not a crash', function () {
    Queue::fake();

    // No row exists yet when receive()'s own early check runs (the fast
    // path this test deliberately bypasses); DB::transaction() is mocked
    // to fail exactly the way a genuine race would, another process's
    // insert for the same po_number lands and commits first, so this
    // call's own insert hits the unique index. As a side effect it plants
    // that "other process's" row directly, so the catch branch's lookup
    // has something real to find, the same as it would after a real race.
    DB::shouldReceive('transaction')->once()->andReturnUsing(function () {
        // Eloquent's own connection resolution, deliberately not the DB
        // facade: DB::shouldReceive() above replaces the facade root for
        // the rest of this test, a DB::table() call in here would hit
        // the same mock instead of a real connection.
        PurchaseOrder::query()->create([
            'po_number' => 'PO-RACE',
            'order_date' => now(),
            'total' => '25.99',
            'currency' => 'AUD',
            'buyer_reference' => null,
            'raw_payload' => 'inserted by the winning process',
            'status' => PurchaseOrderStatus::Received,
            'received_at' => now(),
        ]);

        throw new UniqueConstraintViolationException('sqlite', 'insert', [], new Exception('UNIQUE constraint failed'));
    });

    $receipt = app(PurchaseOrderService::class)->receive(sampleOrderRequestData('PO-RACE'), '<raw-xml/>');

    expect($receipt->wasAlreadyReceived)->toBeTrue()
        ->and(PurchaseOrder::query()->where('po_number', 'PO-RACE')->count())->toBe(1);
});
