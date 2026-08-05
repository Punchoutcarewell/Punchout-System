<?php

declare(strict_types=1);

use App\Modules\Catalog\Exceptions\ProductNotFoundException;
use App\Modules\Catalog\Models\ContractPrice;
use App\Modules\Catalog\Services\PricingService;
use App\Shared\Exceptions\DomainValidationException;

it('resolves the active contract price over list price', function () {
    $product = createTestProduct(['list_price' => '29.99', 'currency' => 'AUD']);

    ContractPrice::query()->create([
        'product_id' => $product->id,
        'contract_reference' => 'C3N-1',
        'price' => '25.99',
        'currency' => 'AUD',
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);

    $snapshot = (new PricingService)->resolveContractPrice($product->sku, 'AUD');

    expect($snapshot->contractPrice->toDecimalString())->toBe('25.99')
        ->and($snapshot->listPrice->toDecimalString())->toBe('29.99')
        ->and($snapshot->unspscCode->value())->toBe('42311505');
});

it('falls back to list price when no contract price is on file', function () {
    $product = createTestProduct(['list_price' => '29.99', 'currency' => 'AUD']);

    $snapshot = (new PricingService)->resolveContractPrice($product->sku, 'AUD');

    expect($snapshot->contractPrice->toDecimalString())->toBe('29.99');
});

it('ignores an expired contract price and falls back to list price', function () {
    $product = createTestProduct(['list_price' => '29.99', 'currency' => 'AUD']);

    ContractPrice::query()->create([
        'product_id' => $product->id,
        'contract_reference' => 'C3N-1',
        'price' => '19.99',
        'currency' => 'AUD',
        'effective_from' => now()->subMonth(),
        'effective_to' => now()->subDay(),
    ]);

    $snapshot = (new PricingService)->resolveContractPrice($product->sku, 'AUD');

    expect($snapshot->contractPrice->toDecimalString())->toBe('29.99');
});

it('throws when the SKU does not exist', function () {
    (new PricingService)->resolveContractPrice('DOES-NOT-EXIST', 'AUD');
})->throws(ProductNotFoundException::class);

it('throws when the product is not active', function () {
    $product = createTestProduct(['is_active' => false]);

    (new PricingService)->resolveContractPrice($product->sku, 'AUD');
})->throws(ProductNotFoundException::class);

it('throws on a currency mismatch rather than converting', function () {
    $product = createTestProduct(['currency' => 'AUD']);

    (new PricingService)->resolveContractPrice($product->sku, 'USD');
})->throws(DomainValidationException::class);

it('resolves a batch of SKUs at once, keyed by sku', function () {
    $a = createTestProduct(['sku' => 'CW-A', 'list_price' => '10.00', 'currency' => 'AUD']);
    $b = createTestProduct(['sku' => 'CW-B', 'list_price' => '20.00', 'currency' => 'AUD']);

    $snapshots = (new PricingService)->resolveContractPrices([$a->sku, $b->sku], 'AUD');

    expect($snapshots)->toHaveCount(2)
        ->and($snapshots[$a->sku]->contractPrice->toDecimalString())->toBe('10.00')
        ->and($snapshots[$b->sku]->contractPrice->toDecimalString())->toBe('20.00');
});

it('throws for the whole batch when one SKU in it does not exist', function () {
    $a = createTestProduct(['sku' => 'CW-A']);

    (new PricingService)->resolveContractPrices([$a->sku, 'DOES-NOT-EXIST'], 'AUD');
})->throws(ProductNotFoundException::class);

it('throws for the whole batch when one SKU in it is priced in a different currency', function () {
    $a = createTestProduct(['sku' => 'CW-A', 'currency' => 'AUD']);
    $b = createTestProduct(['sku' => 'CW-B', 'currency' => 'USD']);

    (new PricingService)->resolveContractPrices([$a->sku, $b->sku], 'AUD');
})->throws(DomainValidationException::class);

it('resolves the same price whether called singly (a fresh query) or batched (from an eager-loaded relation)', function () {
    $product = createTestProduct(['list_price' => '29.99', 'currency' => 'AUD']);

    ContractPrice::query()->create([
        'product_id' => $product->id,
        'contract_reference' => 'C3N-1-ORIGINAL',
        'price' => '25.99',
        'currency' => 'AUD',
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);

    ContractPrice::query()->create([
        'product_id' => $product->id,
        'contract_reference' => 'C3N-1-CORRECTION',
        'price' => '22.99',
        'currency' => 'AUD',
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);

    $singular = (new PricingService)->resolveContractPrice($product->sku, 'AUD');
    $batched = (new PricingService)->resolveContractPrices([$product->sku], 'AUD')[$product->sku];

    expect($singular->contractPrice->toDecimalString())->toBe($batched->contractPrice->toDecimalString())
        ->and($batched->contractPrice->toDecimalString())->toBe('22.99');
});

it('deterministically picks the most recently created contract price when two share the same effective_from', function () {
    $product = createTestProduct(['list_price' => '29.99', 'currency' => 'AUD']);

    ContractPrice::query()->create([
        'product_id' => $product->id,
        'contract_reference' => 'C3N-1-ORIGINAL',
        'price' => '25.99',
        'currency' => 'AUD',
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);

    ContractPrice::query()->create([
        'product_id' => $product->id,
        'contract_reference' => 'C3N-1-CORRECTION',
        'price' => '22.99',
        'currency' => 'AUD',
        'effective_from' => now()->subDay(),
        'effective_to' => null,
    ]);

    $snapshot = (new PricingService)->resolveContractPrice($product->sku, 'AUD');

    // Both rows are equally "active" as of effective_from; the second one
    // created is the correction and must win every time this query runs,
    // not whichever the database happens to return first.
    expect($snapshot->contractPrice->toDecimalString())->toBe('22.99');
});
