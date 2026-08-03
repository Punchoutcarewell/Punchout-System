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
