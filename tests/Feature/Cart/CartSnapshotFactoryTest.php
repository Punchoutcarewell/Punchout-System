<?php

declare(strict_types=1);

use App\Modules\Cart\Exceptions\EmptyCartException;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Services\CartService;
use App\Modules\Cart\Services\CartSnapshotFactory;
use App\Modules\Catalog\Models\ContractPrice;
use Illuminate\Support\Facades\DB;

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

it('resolves pricing for every line in a fixed number of queries, not one pair of queries per line', function () {
    $sessionId = issueTestPunchoutSession()->id;
    $service = app(CartService::class);

    $productA = createTestProduct(['sku' => 'CW-A', 'supplier_part_id' => 'CW-A', 'list_price' => '10.00', 'currency' => 'AUD']);
    $productB = createTestProduct(['sku' => 'CW-B', 'supplier_part_id' => 'CW-B', 'list_price' => '20.00', 'currency' => 'AUD']);
    $productC = createTestProduct(['sku' => 'CW-C', 'supplier_part_id' => 'CW-C', 'list_price' => '30.00', 'currency' => 'AUD']);

    ContractPrice::query()->create([
        'product_id' => $productB->id,
        'contract_reference' => 'C3N-B',
        'price' => '18.00',
        'currency' => 'AUD',
        'effective_from' => now()->subDay(),
    ]);

    $service->addItem($sessionId, $productA->sku, 1);
    $service->addItem($sessionId, $productB->sku, 1);
    $service->addItem($sessionId, $productC->sku, 1);

    $cart = Cart::query()->where('session_id', $sessionId)->firstOrFail();

    $queryCount = 0;
    DB::listen(function () use (&$queryCount): void {
        $queryCount++;
    });

    $snapshot = app(CartSnapshotFactory::class)->build($cart);

    // 1 query for cart items (loadMissing), 1 for the products, 1 for
    // their contract prices via eager load: fixed regardless of how many
    // distinct SKUs are on the cart, not 2 additional queries per line.
    expect($queryCount)->toBeLessThanOrEqual(3)
        ->and($snapshot->lines)->toHaveCount(3);

    $prices = collect($snapshot->lines)->keyBy('supplierPartId')->map(fn ($line) => $line->unitPrice->toDecimalString());
    expect($prices['CW-A'])->toBe('10.00')
        ->and($prices['CW-B'])->toBe('18.00')
        ->and($prices['CW-C'])->toBe('30.00');
});

it('refuses to build a snapshot for an empty cart', function () {
    $sessionId = issueTestPunchoutSession()->id;

    $cart = Cart::query()->create(['session_id' => $sessionId, 'total' => '0', 'currency' => 'AUD']);

    app(CartSnapshotFactory::class)->build($cart);
})->throws(EmptyCartException::class);
