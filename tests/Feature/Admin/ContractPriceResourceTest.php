<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Resources\ContractPriceResource\Pages\CreateContractPrice;
use App\Modules\Admin\Filament\Resources\ContractPriceResource\Pages\ListContractPrices;
use App\Modules\Catalog\Models\ContractPrice;
use Livewire\Livewire;

it('lists contract prices', function () {
    actingAsAdmin();
    $product = createTestProduct(['sku' => 'CW-4021']);
    ContractPrice::query()->create([
        'product_id' => $product->id,
        'contract_reference' => 'C3N-1',
        'price' => '19.99',
        'currency' => 'AUD',
        'effective_from' => now()->subDay(),
    ]);

    Livewire::test(ListContractPrices::class)
        ->assertCanSeeTableRecords(ContractPrice::all());
});

it('creates a contract price for a product', function () {
    actingAsAdmin();
    $product = createTestProduct(['sku' => 'CW-4021']);

    Livewire::test(CreateContractPrice::class)
        ->fillForm([
            'product_id' => $product->id,
            'contract_reference' => 'C3N-1',
            'price' => '19.99',
            'currency' => 'AUD',
            'effective_from' => now()->subDay()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ContractPrice::query()->where('product_id', $product->id)->exists())->toBeTrue();
});
