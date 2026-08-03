<?php

declare(strict_types=1);

use App\Modules\Catalog\Services\CatalogSearchService;

it('finds a product by a name substring', function () {
    createTestProduct(['name' => 'Foam Wound Dressing', 'description' => 'Foam Wound Dressing', 'sku' => 'CW-1001']);
    createTestProduct(['name' => 'Standard Wheelchair', 'description' => 'Folding frame wheelchair', 'sku' => 'CW-1002']);

    $results = (new CatalogSearchService)->search('Wound');

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->sku)->toBe('CW-1001');
});

it('finds a product by SKU', function () {
    createTestProduct(['sku' => 'CW-4021', 'name' => 'Foam Wound Dressing']);

    $results = (new CatalogSearchService)->search('CW-4021');

    expect($results->total())->toBe(1);
});

it('excludes inactive products', function () {
    createTestProduct(['name' => 'Retired Item', 'is_active' => false]);

    $results = (new CatalogSearchService)->search('Retired');

    expect($results->total())->toBe(0);
});

it('filters by category', function () {
    $woundCare = createTestCategory('Wound Care');
    $mobility = createTestCategory('Mobility');

    createTestProduct(['sku' => 'CW-1', 'category_id' => $woundCare->id]);
    createTestProduct(['sku' => 'CW-2', 'category_id' => $mobility->id]);

    $results = (new CatalogSearchService)->search(null, $woundCare->id);

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->sku)->toBe('CW-1');
});

it('returns every active product when no query or category is given', function () {
    createTestProduct(['sku' => 'CW-1']);
    createTestProduct(['sku' => 'CW-2']);

    $results = (new CatalogSearchService)->search(null);

    expect($results->total())->toBe(2);
});
