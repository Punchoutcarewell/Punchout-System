<?php

declare(strict_types=1);

use App\Modules\Catalog\Services\ProductImageBulkUploader;

it('matches an uploaded file to a product by its image_path filename and applies it', function () {
    $product = createTestProduct(['sku' => 'CW-4021', 'image_path' => 'CW-4021.jpg']);

    $report = (new ProductImageBulkUploader)->applyUploadedImages(['products/CW-4021.jpg']);

    expect($report->matched)->toBe(1)
        ->and($report->hasUnmatched())->toBeFalse()
        ->and($product->refresh()->image_path)->toBe('products/CW-4021.jpg');
});

it('matches by the last path segment when image_path is a full URL', function () {
    $product = createTestProduct(['sku' => 'CW-4021', 'image_path' => 'https://vendor.example.com/img/CW-4021.jpg']);

    $report = (new ProductImageBulkUploader)->applyUploadedImages(['products/CW-4021.jpg']);

    expect($report->matched)->toBe(1)
        ->and($product->refresh()->image_path)->toBe('products/CW-4021.jpg');
});

it('reports an uploaded file with no matching product as unmatched, and does not touch any product', function () {
    createTestProduct(['sku' => 'CW-4021', 'image_path' => 'CW-4021.jpg']);

    $report = (new ProductImageBulkUploader)->applyUploadedImages(['products/does-not-exist.jpg']);

    expect($report->matched)->toBe(0)
        ->and($report->unmatchedFilenames)->toBe(['does-not-exist.jpg']);
});

it('matches filenames case-sensitively', function () {
    createTestProduct(['sku' => 'CW-4021', 'image_path' => 'CW-4021.jpg']);

    $report = (new ProductImageBulkUploader)->applyUploadedImages(['products/cw-4021.jpg']);

    expect($report->matched)->toBe(0)
        ->and($report->unmatchedFilenames)->toBe(['cw-4021.jpg']);
});

it('applies one uploaded file to every product that shares the same image_path filename', function () {
    $first = createTestProduct(['sku' => 'CW-4021', 'image_path' => 'shared.jpg']);
    $second = createTestProduct(['sku' => 'CW-4022', 'image_path' => 'shared.jpg']);

    $report = (new ProductImageBulkUploader)->applyUploadedImages(['products/shared.jpg']);

    expect($report->matched)->toBe(2)
        ->and($first->refresh()->image_path)->toBe('products/shared.jpg')
        ->and($second->refresh()->image_path)->toBe('products/shared.jpg');
});

it('ignores products with no image_path set', function () {
    createTestProduct(['sku' => 'CW-4021', 'image_path' => null]);

    $report = (new ProductImageBulkUploader)->applyUploadedImages(['products/CW-4021.jpg']);

    expect($report->matched)->toBe(0)
        ->and($report->unmatchedFilenames)->toBe(['CW-4021.jpg']);
});

it('processes a batch of multiple uploaded files independently', function () {
    createTestProduct(['sku' => 'CW-4021', 'image_path' => 'CW-4021.jpg']);
    createTestProduct(['sku' => 'CW-4022', 'image_path' => 'CW-4022.jpg']);

    $report = (new ProductImageBulkUploader)->applyUploadedImages([
        'products/CW-4021.jpg',
        'products/CW-4022.jpg',
        'products/orphan.jpg',
    ]);

    expect($report->matched)->toBe(2)
        ->and($report->unmatchedFilenames)->toBe(['orphan.jpg']);
});
