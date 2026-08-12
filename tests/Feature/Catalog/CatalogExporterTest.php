<?php

declare(strict_types=1);

use App\Modules\Catalog\Services\CatalogExporter;
use League\Csv\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('exports every product to a CSV file with the full column layout', function () {
    createTestProduct([
        'sku' => 'CW-4021',
        'name' => 'Foam Wound Dressing',
        'category_id' => createTestCategory('Wound Care')->id,
        'unspsc_code' => '42311505',
        'unit_of_measure' => 'BX',
        'pack_size' => 10,
        'stock_quantity' => 42,
        'list_price' => '25.99',
        'currency' => 'AUD',
    ]);

    $path = (new CatalogExporter)->exportToCsvFile();

    $csv = Reader::from($path, 'r');
    $csv->setHeaderOffset(0);
    $rows = array_values(iterator_to_array($csv->getRecords()));

    expect($rows)->toHaveCount(1);

    $row = $rows[0];
    expect($row['sku'])->toBe('CW-4021')
        ->and($row['name'])->toBe('Foam Wound Dressing')
        ->and($row['category'])->toBe('Wound Care')
        ->and($row['pack_size'])->toBe('10')
        ->and($row['stock_quantity'])->toBe('42')
        ->and($row['list_price'])->toBe('25.99')
        ->and($row['currency'])->toBe('AUD')
        ->and($row['is_active'])->toBe('1');

    unlink($path);
});

it('leaves category blank in the export for a product with no category', function () {
    createTestProduct(['sku' => 'CW-4021', 'category_id' => null]);

    $path = (new CatalogExporter)->exportToCsvFile();

    $csv = Reader::from($path, 'r');
    $csv->setHeaderOffset(0);
    $row = array_values(iterator_to_array($csv->getRecords()))[0];

    expect($row['category'])->toBe('');

    unlink($path);
});

it('exports every product to an Excel (.xlsx) file with the full column layout', function () {
    createTestProduct([
        'sku' => 'CW-4021',
        'name' => 'Foam Wound Dressing',
        'unspsc_code' => '42311505',
        'unit_of_measure' => 'BX',
        'stock_quantity' => 7,
        'list_price' => '25.99',
        'currency' => 'AUD',
    ]);

    $path = (new CatalogExporter)->exportToExcelFile();

    $sheet = IOFactory::load($path)->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, false);

    expect($rows)->toHaveCount(2); // header + one product

    $headers = $rows[0];
    $skuIndex = array_search('sku', $headers, true);
    $stockIndex = array_search('stock_quantity', $headers, true);

    expect($rows[1][$skuIndex])->toBe('CW-4021')
        ->and((string) $rows[1][$stockIndex])->toBe('7');

    unlink($path);
});

it('exports nothing but the header row when the catalogue is empty', function () {
    $path = (new CatalogExporter)->exportToCsvFile();

    $csv = Reader::from($path, 'r');
    $csv->setHeaderOffset(0);

    expect(iterator_to_array($csv->getRecords()))->toBeEmpty();

    unlink($path);
});
