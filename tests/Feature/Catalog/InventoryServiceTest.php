<?php

declare(strict_types=1);

use App\Modules\Catalog\Services\InventoryService;

it('decrements stock_quantity by the deducted amount', function () {
    $product = createTestProduct(['sku' => 'CW-4021', 'stock_quantity' => 50]);

    (new InventoryService)->deduct('CW-4021', 20);

    expect($product->refresh()->stock_quantity)->toBe(30);
});

it('allows stock_quantity to go negative when a deduction oversells what is on hand', function () {
    $product = createTestProduct(['sku' => 'CW-4021', 'stock_quantity' => 5]);

    (new InventoryService)->deduct('CW-4021', 8);

    expect($product->refresh()->stock_quantity)->toBe(-3)
        ->and($product->hasShortfall())->toBeTrue()
        ->and($product->shortfallQuantity())->toBe(3);
});

it('does nothing, without throwing, when the SKU does not match any product', function () {
    $product = createTestProduct(['sku' => 'CW-4021', 'stock_quantity' => 10]);

    (new InventoryService)->deduct('DOES-NOT-EXIST', 5);

    expect($product->refresh()->stock_quantity)->toBe(10);
});

it('only affects the product matching the deducted SKU, not other products', function () {
    $target = createTestProduct(['sku' => 'CW-A', 'stock_quantity' => 10]);
    $other = createTestProduct(['sku' => 'CW-B', 'stock_quantity' => 10]);

    (new InventoryService)->deduct('CW-A', 4);

    expect($target->refresh()->stock_quantity)->toBe(6)
        ->and($other->refresh()->stock_quantity)->toBe(10);
});

it('deducts against a deactivated product too, stock is a physical fact, not a storefront-visibility one', function () {
    $product = createTestProduct(['sku' => 'CW-4021', 'stock_quantity' => 10, 'is_active' => false]);

    (new InventoryService)->deduct('CW-4021', 3);

    expect($product->refresh()->stock_quantity)->toBe(7);
});

it('new products default to zero stock', function () {
    $product = createTestProduct(['sku' => 'CW-4021']);

    expect($product->refresh()->stock_quantity)->toBe(0);
});
