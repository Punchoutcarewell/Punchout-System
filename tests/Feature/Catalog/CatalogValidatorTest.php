<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\ContractPrice;
use App\Modules\Catalog\Services\CatalogValidator;

it('passes a fully valid active product', function () {
    $product = createTestProduct();

    ContractPrice::query()->create([
        'product_id' => $product->id,
        'contract_reference' => 'C3N-1',
        'price' => '25.99',
        'currency' => 'AUD',
        'effective_from' => now()->subDay(),
    ]);

    expect((new CatalogValidator)->validate())->toBe([]);
});

it('fails a product with no contract price on file', function () {
    $product = createTestProduct();

    $failures = (new CatalogValidator)->validate();

    expect($failures)->toHaveCount(1)
        ->and($failures[0]['sku'])->toBe($product->sku)
        ->and($failures[0]['issues'])->toContain('no contract price on file');
});

it('fails a product with an invalid UNSPSC code', function () {
    createTestProduct(['unspsc_code' => '123']);

    $failures = (new CatalogValidator)->validate();

    expect($failures[0]['issues'])->toContain('invalid or missing UNSPSC code');
});

it('ignores inactive products entirely', function () {
    createTestProduct(['is_active' => false]);

    expect((new CatalogValidator)->validate())->toBe([]);
});
