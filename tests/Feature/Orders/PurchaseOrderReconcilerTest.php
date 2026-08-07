<?php

declare(strict_types=1);

use App\Modules\Orders\Enums\PurchaseOrderStatus;
use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Orders\Models\PurchaseOrderLine;
use App\Modules\Orders\Services\PurchaseOrderReconciler;

function createTestPurchaseOrder(array $attributes = []): PurchaseOrder
{
    return PurchaseOrder::query()->create(array_merge([
        'po_number' => 'PO-'.uniqid(),
        'order_date' => now(),
        'total' => '25.99',
        'currency' => 'AUD',
        'buyer_reference' => null,
        'raw_payload' => '<raw-xml/>',
        'status' => PurchaseOrderStatus::Received,
        'received_at' => now(),
    ], $attributes));
}

it('finds no discrepancy when the total and every line match the catalogue', function () {
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '25.99', 'currency' => 'AUD']);
    $purchaseOrder = createTestPurchaseOrder(['total' => '25.99', 'currency' => 'AUD']);

    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_number' => 1,
        'supplier_part_id' => $product->sku,
        'quantity' => 1,
        'unit_price' => '25.99',
        'unit_of_measure' => 'BX',
        'description' => $product->name,
    ]);

    app(PurchaseOrderReconciler::class)->reconcile($purchaseOrder->fresh(['lines']));

    expect($purchaseOrder->fresh()->has_discrepancy)->toBeFalse()
        ->and($purchaseOrder->fresh()->discrepancy_details)->toBeNull();
});

it('flags a discrepancy when the PO total does not equal the sum of its lines', function () {
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '25.99', 'currency' => 'AUD']);
    $purchaseOrder = createTestPurchaseOrder(['total' => '99.99', 'currency' => 'AUD']);

    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_number' => 1,
        'supplier_part_id' => $product->sku,
        'quantity' => 1,
        'unit_price' => '25.99',
        'unit_of_measure' => 'BX',
        'description' => $product->name,
    ]);

    app(PurchaseOrderReconciler::class)->reconcile($purchaseOrder->fresh(['lines']));

    expect($purchaseOrder->fresh()->has_discrepancy)->toBeTrue()
        ->and($purchaseOrder->fresh()->discrepancy_details)->toContain('PO total');
});

it('flags a discrepancy when a line price does not match the current catalogue price', function () {
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '25.99', 'currency' => 'AUD']);
    $purchaseOrder = createTestPurchaseOrder(['total' => '19.99', 'currency' => 'AUD']);

    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_number' => 1,
        'supplier_part_id' => $product->sku,
        'quantity' => 1,
        'unit_price' => '19.99',
        'unit_of_measure' => 'BX',
        'description' => $product->name,
    ]);

    app(PurchaseOrderReconciler::class)->reconcile($purchaseOrder->fresh(['lines']));

    expect($purchaseOrder->fresh()->has_discrepancy)->toBeTrue()
        ->and($purchaseOrder->fresh()->discrepancy_details)->toContain('CW-4021')
        ->and($purchaseOrder->fresh()->discrepancy_details)->toContain('does not match the current catalogue price');
});

it('flags a discrepancy when a line references a SKU no longer active in the catalogue', function () {
    $purchaseOrder = createTestPurchaseOrder(['total' => '25.99', 'currency' => 'AUD']);

    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_number' => 1,
        'supplier_part_id' => 'DOES-NOT-EXIST',
        'quantity' => 1,
        'unit_price' => '25.99',
        'unit_of_measure' => 'BX',
        'description' => 'Some product',
    ]);

    app(PurchaseOrderReconciler::class)->reconcile($purchaseOrder->fresh(['lines']));

    expect($purchaseOrder->fresh()->has_discrepancy)->toBeTrue()
        ->and($purchaseOrder->fresh()->discrepancy_details)->toContain('no active catalogue product');
});

it('re-reconciling clears a previously flagged discrepancy once it is resolved', function () {
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '25.99', 'currency' => 'AUD']);
    $purchaseOrder = createTestPurchaseOrder(['total' => '19.99', 'currency' => 'AUD']);

    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_number' => 1,
        'supplier_part_id' => $product->sku,
        'quantity' => 1,
        'unit_price' => '19.99',
        'unit_of_measure' => 'BX',
        'description' => $product->name,
    ]);

    $reconciler = app(PurchaseOrderReconciler::class);
    $reconciler->reconcile($purchaseOrder->fresh(['lines']));
    expect($purchaseOrder->fresh()->has_discrepancy)->toBeTrue();

    $purchaseOrder->update(['total' => '25.99']);
    $purchaseOrder->lines->first()->update(['unit_price' => '25.99']);

    $reconciler->reconcile($purchaseOrder->fresh(['lines']));

    expect($purchaseOrder->fresh()->has_discrepancy)->toBeFalse()
        ->and($purchaseOrder->fresh()->discrepancy_details)->toBeNull();
});
