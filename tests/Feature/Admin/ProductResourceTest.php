<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Modules\Admin\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Modules\Admin\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Modules\Catalog\Models\Product;
use Livewire\Livewire;

it('lists products', function () {
    actingAsAdmin();
    createTestProduct(['sku' => 'CW-4021', 'name' => 'Foam Wound Dressing']);

    Livewire::test(ListProducts::class)
        ->assertCanSeeTableRecords(Product::all());
});

it('creates a product through the form', function () {
    actingAsAdmin();

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'sku' => 'CW-4021',
            'supplier_part_id' => 'CW-4021',
            'name' => 'Foam Wound Dressing',
            'description' => 'Foam dressing pack of 10',
            'unspsc_code' => '42311505',
            'unit_of_measure' => 'BX',
            'pack_size' => 10,
            'stock_quantity' => 100,
            'lead_time_days' => 2,
            'list_price' => '25.99',
            'currency' => 'AUD',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->where('sku', 'CW-4021')->firstOrFail();
    expect($product->stock_quantity)->toBe(100);
});

it('defaults stock_quantity to zero when left unset on the create form', function () {
    actingAsAdmin();

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'sku' => 'CW-4021',
            'supplier_part_id' => 'CW-4021',
            'name' => 'Foam Wound Dressing',
            'description' => 'Foam dressing pack of 10',
            'unspsc_code' => '42311505',
            'unit_of_measure' => 'BX',
            'lead_time_days' => 2,
            'list_price' => '25.99',
            'currency' => 'AUD',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::query()->where('sku', 'CW-4021')->firstOrFail()->stock_quantity)->toBe(0);
});

it('rejects a product missing required fields', function () {
    actingAsAdmin();

    Livewire::test(CreateProduct::class)
        ->fillForm(['sku' => 'CW-4021'])
        ->call('create')
        ->assertHasFormErrors(['name', 'unspsc_code']);
});

it('deactivates a product with a single click on the list table, no need to open Edit', function () {
    actingAsAdmin();
    $product = createTestProduct(['sku' => 'CW-4021', 'is_active' => true]);

    Livewire::test(ListProducts::class)
        ->call('updateTableColumnState', 'is_active', (string) $product->getKey(), false);

    expect($product->refresh()->is_active)->toBeFalse();
});

it('reactivates a product with a single click on the list table', function () {
    actingAsAdmin();
    $product = createTestProduct(['sku' => 'CW-4021', 'is_active' => false]);

    Livewire::test(ListProducts::class)
        ->call('updateTableColumnState', 'is_active', (string) $product->getKey(), true);

    expect($product->refresh()->is_active)->toBeTrue();
});

it('edits an existing product', function () {
    actingAsAdmin();
    $product = createTestProduct(['sku' => 'CW-4021', 'name' => 'Old Name']);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->refresh()->name)->toBe('New Name');
});
