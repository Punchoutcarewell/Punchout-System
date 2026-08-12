<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Resources\ProductResource;
use App\Modules\Admin\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * UploadedFile::fake()->create() content is meaningless random bytes, no
 * good for a real importer to parse. ->createWithContent() is the
 * Laravel-native way to feed genuine bytes through a fake upload while
 * still returning the Illuminate\Http\Testing\File Livewire's own
 * testing harness expects (a bare Illuminate\Http\UploadedFile lacks
 * the ->name property it reads internally).
 */
function realUploadedFile(string $path, string $clientName): UploadedFile
{
    return UploadedFile::fake()->createWithContent($clientName, file_get_contents($path));
}

it('creates a product from an uploaded CSV file via the Import action', function () {
    Storage::fake('local');
    actingAsAdmin();

    $path = tempnam(sys_get_temp_dir(), 'admin_import_').'.csv';
    file_put_contents(
        $path,
        "sku,name,unspsc_code,unit_of_measure,list_price,currency\n"
        ."CW-4021,Foam Wound Dressing,42311505,BX,25.99,AUD\n",
    );

    Livewire::test(ListProducts::class)
        ->callAction('import', data: [
            'file' => realUploadedFile($path, 'products.csv'),
        ]);

    unlink($path);

    expect(Product::query()->where('sku', 'CW-4021')->exists())->toBeTrue();
});

it('creates a product from an uploaded Excel file via the Import action', function () {
    Storage::fake('local');
    actingAsAdmin();

    $path = tempnam(sys_get_temp_dir(), 'admin_import_').'.xlsx';
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['sku', 'name', 'unspsc_code', 'unit_of_measure', 'list_price', 'currency'],
        ['CW-8890', 'Standard Wheelchair', '42131601', 'EA', 219.90, 'AUD'],
    ], null, 'A1');
    (new Xlsx($spreadsheet))->save($path);

    Livewire::test(ListProducts::class)
        ->callAction('import', data: [
            'file' => realUploadedFile($path, 'products.xlsx'),
        ]);

    unlink($path);

    expect(Product::query()->where('sku', 'CW-8890')->exists())->toBeTrue();
});

it('does not create a product from a row missing a required column, and does not blow up the request', function () {
    Storage::fake('local');
    actingAsAdmin();

    $path = tempnam(sys_get_temp_dir(), 'admin_import_').'.csv';
    file_put_contents(
        $path,
        "sku,name,unspsc_code,unit_of_measure,list_price,currency\n"
        .",Missing SKU,42311505,BX,10.00,AUD\n",
    );

    Livewire::test(ListProducts::class)
        ->callAction('import', data: [
            'file' => realUploadedFile($path, 'products.csv'),
        ])
        ->assertHasNoActionErrors();

    unlink($path);

    expect(Product::query()->count())->toBe(0);
});

it('rejects an uploaded file whose extension is neither CSV nor Excel', function () {
    Storage::fake('local');
    actingAsAdmin();

    $path = tempnam(sys_get_temp_dir(), 'admin_import_').'.txt';
    file_put_contents($path, 'not a catalogue file');

    Livewire::test(ListProducts::class)
        ->callAction('import', data: [
            'file' => realUploadedFile($path, 'notes.txt'),
        ])
        ->assertHasNoActionErrors();

    unlink($path);

    expect(Product::query()->count())->toBe(0);
});

it('deletes the uploaded file from storage after processing it', function () {
    Storage::fake('local');
    actingAsAdmin();

    $path = tempnam(sys_get_temp_dir(), 'admin_import_').'.csv';
    file_put_contents(
        $path,
        "sku,name,unspsc_code,unit_of_measure,list_price,currency\n"
        ."CW-4021,Foam Wound Dressing,42311505,BX,25.99,AUD\n",
    );

    Livewire::test(ListProducts::class)
        ->callAction('import', data: [
            'file' => realUploadedFile($path, 'products.csv'),
        ]);

    unlink($path);

    Storage::disk('local')->assertDirectoryEmpty('catalog-imports');
});

it('exposes an Export action offering both CSV and Excel', function () {
    actingAsAdmin();

    Livewire::test(ListProducts::class)
        ->assertActionExists('exportCsv')
        ->assertActionExists('exportExcel');
});

it('requires admin authentication to reach the Products list', function () {
    $this->get(ProductResource::getUrl('index'))
        ->assertRedirect('/admin/login');
});
