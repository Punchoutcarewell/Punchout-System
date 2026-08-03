<?php

declare(strict_types=1);

use App\Modules\Cart\Exceptions\EmptyCartException;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Services\CartService;
use App\Modules\Cart\Services\CartSnapshotFactory;
use App\Modules\Catalog\Models\ContractPrice;

it('builds a Punchout CartSnapshot from a real cart, re-resolving pricing fresh', function () {
    $sessionId = issueTestPunchoutSession()->id;
    $product = createTestProduct([
        'sku' => 'CW-4021',
        'supplier_part_id' => 'CW-4021',
        'name' => 'Foam Wound Dressing',
        'unspsc_code' => '42311505',
        'unit_of_measure' => 'BX',
        'list_price' => '25.99',
        'currency' => 'AUD',
        'lead_time_days' => 2,
    ]);

    app(CartService::class)->addItem($sessionId, $product->sku, 3);

    $cart = Cart::query()->where('session_id', $sessionId)->firstOrFail();
    $snapshot = app(CartSnapshotFactory::class)->build($cart);

    expect($snapshot->lines)->toHaveCount(1)
        ->and($snapshot->lines[0]->supplierPartId)->toBe('CW-4021')
        ->and($snapshot->lines[0]->quantity)->toBe(3)
        ->and($snapshot->lines[0]->unitPrice->toDecimalString())->toBe('25.99')
        ->and($snapshot->lines[0]->unspscCode->value())->toBe('42311505')
        ->and($snapshot->lines[0]->unitOfMeasure)->toBe('BX')
        ->and($snapshot->lines[0]->leadTimeDays)->toBe(2)
        ->and($snapshot->total()->toDecimalString())->toBe('77.97');
});

it('reflects a contract price change made after the item was added', function () {
    $sessionId = issueTestPunchoutSession()->id;
    $product = createTestProduct(['sku' => 'CW-4021', 'list_price' => '25.99', 'currency' => 'AUD']);

    app(CartService::class)->addItem($sessionId, $product->sku, 1);

    ContractPrice::query()->create([
        'product_id' => $product->id,
        'contract_reference' => 'C3N-1',
        'price' => '19.99',
        'currency' => 'AUD',
        'effective_from' => now()->subDay(),
    ]);

    $cart = Cart::query()->where('session_id', $sessionId)->firstOrFail();
    $snapshot = app(CartSnapshotFactory::class)->build($cart);

    // Built fresh from Catalog at transfer time, not from the display
    // cache captured when the item was added.
    expect($snapshot->lines[0]->unitPrice->toDecimalString())->toBe('19.99');
});

it('refuses to build a snapshot for an empty cart', function () {
    $sessionId = issueTestPunchoutSession()->id;

    $cart = Cart::query()->create(['session_id' => $sessionId, 'total' => '0', 'currency' => 'AUD']);

    app(CartSnapshotFactory::class)->build($cart);
})->throws(EmptyCartException::class);
