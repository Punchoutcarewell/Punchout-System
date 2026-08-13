<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Resources\ProductResource\Pages\ListProducts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('uploads a batch of images, applying each to the product whose image_path matches its filename', function () {
    Storage::fake('public');
    actingAsAdmin();
    $product = createTestProduct(['sku' => 'CW-4021', 'image_path' => 'CW-4021.jpg']);

    Livewire::test(ListProducts::class)
        ->callAction('bulkUploadImages', data: [
            'images' => [UploadedFile::fake()->image('CW-4021.jpg')],
        ]);

    expect($product->refresh()->image_path)->toBe('products/CW-4021.jpg');
    Storage::disk('public')->assertExists('products/CW-4021.jpg');
});

it('stores an unmatched upload on disk without erroring, even though no product claims it', function () {
    Storage::fake('public');
    actingAsAdmin();

    Livewire::test(ListProducts::class)
        ->callAction('bulkUploadImages', data: [
            'images' => [UploadedFile::fake()->image('orphan.jpg')],
        ]);

    Storage::disk('public')->assertExists('products/orphan.jpg');
});

it('applies multiple files in a single upload to their respective products', function () {
    Storage::fake('public');
    actingAsAdmin();
    $first = createTestProduct(['sku' => 'CW-4021', 'image_path' => 'CW-4021.jpg']);
    $second = createTestProduct(['sku' => 'CW-4022', 'image_path' => 'CW-4022.jpg']);

    Livewire::test(ListProducts::class)
        ->callAction('bulkUploadImages', data: [
            'images' => [
                UploadedFile::fake()->image('CW-4021.jpg'),
                UploadedFile::fake()->image('CW-4022.jpg'),
            ],
        ]);

    expect($first->refresh()->image_path)->toBe('products/CW-4021.jpg')
        ->and($second->refresh()->image_path)->toBe('products/CW-4022.jpg');
});
