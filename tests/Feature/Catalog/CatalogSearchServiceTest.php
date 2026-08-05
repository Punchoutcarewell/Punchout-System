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

it('finds a product whose name contains a literal percent character', function () {
    createTestProduct(['sku' => 'CW-1001', 'name' => '50% Cotton Gauze', 'description' => '50% Cotton Gauze']);
    createTestProduct(['sku' => 'CW-1002', 'name' => 'Standard Wheelchair', 'description' => 'Folding frame wheelchair']);

    $results = (new CatalogSearchService)->search('50%');

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->sku)->toBe('CW-1001');
});

it('finds a product whose SKU contains a literal underscore character', function () {
    createTestProduct(['sku' => 'CW_1001', 'name' => 'Foam Wound Dressing']);
    createTestProduct(['sku' => 'CW-1002', 'name' => 'Standard Wheelchair']);

    $results = (new CatalogSearchService)->search('CW_1001');

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->sku)->toBe('CW_1001');
});

it('does not let an underscore in the search query act as a wildcard matching any character', function () {
    createTestProduct(['sku' => 'CWA1001', 'name' => 'Foam Wound Dressing']);
    createTestProduct(['sku' => 'CW_1001', 'name' => 'Standard Wheelchair']);

    // "_" is a single-character SQL wildcard; typed literally by a buyer
    // searching for a real underscore-containing SKU, it must not also
    // match "CWA1001" (where "_" would stand in for "A").
    $results = (new CatalogSearchService)->search('CW_1001');

    expect($results->total())->toBe(1)
        ->and($results->items()[0]->sku)->toBe('CW_1001');
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

it('turns a stored image path into a browsable public disk URL', function () {
    createTestProduct(['sku' => 'CW-4021', 'image_path' => 'products/dressing.jpg']);

    $results = (new CatalogSearchService)->search(null);
    $detail = (new CatalogSearchService)->find('CW-4021');

    expect($results->items()[0]->imagePath)->toEndWith('/storage/products/dressing.jpg')
        ->and($detail->imagePath)->toEndWith('/storage/products/dressing.jpg');
});

it('leaves imagePath null when no image was uploaded', function () {
    createTestProduct(['sku' => 'CW-4021', 'image_path' => null]);

    $detail = (new CatalogSearchService)->find('CW-4021');

    expect($detail->imagePath)->toBeNull();
});
