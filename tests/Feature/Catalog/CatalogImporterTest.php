<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\CatalogImporter;

function writeTestCatalogCsv(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'catalog_import_').'.csv';
    file_put_contents($path, $contents);

    return $path;
}

it('imports valid rows and creates products with their category', function () {
    $path = writeTestCatalogCsv(
        "sku,name,description,category,unspsc_code,unit_of_measure,pack_size,lead_time_days,list_price,currency\n"
        ."CW-4021,Foam Wound Dressing,Foam dressing pack of 10,Wound Care,42311505,BX,10,2,25.99,AUD\n"
        ."CW-8890,Standard Wheelchair,Folding frame wheelchair,Mobility,42131601,EA,1,5,219.90,AUD\n",
    );

    $report = (new CatalogImporter)->importFromCsvFile($path);

    expect($report->created)->toBe(2)
        ->and($report->updated)->toBe(0)
        ->and($report->hasIssues())->toBeFalse()
        ->and(Product::query()->count())->toBe(2);

    $product = Product::query()->where('sku', 'CW-4021')->firstOrFail();
    expect($product->category?->name)->toBe('Wound Care');

    unlink($path);
});

it('updates an existing product on a second import rather than duplicating it', function () {
    $csv = "sku,name,unspsc_code,unit_of_measure,list_price,currency\n"
        ."CW-4021,Foam Wound Dressing,42311505,BX,25.99,AUD\n";

    $first = writeTestCatalogCsv($csv);
    (new CatalogImporter)->importFromCsvFile($first);
    unlink($first);

    $second = writeTestCatalogCsv($csv);
    $report = (new CatalogImporter)->importFromCsvFile($second);
    unlink($second);

    expect($report->created)->toBe(0)
        ->and($report->updated)->toBe(1)
        ->and(Product::query()->count())->toBe(1);
});

it('reports a row missing a required column instead of failing the whole import', function () {
    $path = writeTestCatalogCsv(
        "sku,name,unspsc_code,unit_of_measure,list_price,currency\n"
        ."CW-4021,Foam Wound Dressing,42311505,BX,25.99,AUD\n"
        .",Missing SKU,42311505,BX,10.00,AUD\n",
    );

    $report = (new CatalogImporter)->importFromCsvFile($path);

    expect($report->created)->toBe(1)
        ->and($report->issues)->toHaveCount(1)
        ->and($report->issues[0]->reason)->toContain('sku');

    unlink($path);
});

it('reports a row with an invalid UNSPSC code instead of failing the whole import', function () {
    $path = writeTestCatalogCsv(
        "sku,name,unspsc_code,unit_of_measure,list_price,currency\n"
        ."CW-4021,Foam Wound Dressing,NOT-A-CODE,BX,25.99,AUD\n",
    );

    $report = (new CatalogImporter)->importFromCsvFile($path);

    expect($report->created)->toBe(0)
        ->and($report->issues)->toHaveCount(1);

    unlink($path);
});
