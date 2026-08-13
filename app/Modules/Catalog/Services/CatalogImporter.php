<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Data\CatalogImportReport;
use App\Modules\Catalog\Data\CatalogImportRowIssue;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Shared\Exceptions\DomainValidationException;
use App\Shared\ValueObjects\UnspscCode;
use League\Csv\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Imports the catalogue from a CSV or Excel (.xlsx/.xls) file into
 * products, producing a validation report rather than failing silently: a
 * bad row is recorded with a reason and the import continues, so one
 * malformed line does not block the SKUs behind it.
 *
 * Expected header row (column order does not matter): sku,
 * supplier_part_id, supplier_part_auxiliary_id, manufacturer_part_id,
 * manufacturer_name, name, description, long_description, category,
 * unspsc_code, unit_of_measure, pack_size, lead_time_days, list_price,
 * currency, image_path. pack_size is optional: leave it blank for a
 * product not sold in packs, list_price is then the price of one
 * unit_of_measure unit rather than one pack, see Product::$pack_size.
 * Same header row either way, only the container format differs.
 *
 * This column layout is this project's own working assumption. It has
 * not been confirmed against a real export from Carewell's catalogue
 * source system (see roadmap Stage 0.1, "SKU count and catalogue source
 * format") and should be checked once that format is known.
 *
 * image_path is stored exactly as given, a bare filename ("CW-9013.jpg"),
 * a full URL, or empty, no validation that anything exists at it: the
 * image itself almost never lives on this server yet at import time. See
 * ProductImageBulkUploader, which later matches an uploaded batch of
 * image files to products by comparing each file's name against this
 * column's last path segment, and replaces it with the real local path.
 */
final class CatalogImporter
{
    private const REQUIRED_COLUMNS = ['sku', 'name', 'unspsc_code', 'unit_of_measure', 'list_price', 'currency'];

    /**
     * Detects CSV vs Excel from the file's own extension and dispatches
     * to the matching reader below. The one entry point the Admin
     * import action uses, since it accepts either format from a single
     * upload field.
     *
     * @throws DomainValidationException the extension is neither
     */
    public function importFromFile(string $path): CatalogImportReport
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->importFromCsvFile($path),
            'xlsx', 'xls' => $this->importFromExcelFile($path),
            default => throw DomainValidationException::withContext(
                "Unsupported catalogue file format [.{$extension}], expected .csv, .xlsx, or .xls.",
            ),
        };
    }

    public function importFromCsvFile(string $path): CatalogImportReport
    {
        $csv = Reader::from($path, 'r');
        $csv->setHeaderOffset(0);

        return $this->processRecords($csv->getRecords());
    }

    public function importFromExcelFile(string $path): CatalogImportReport
    {
        $sheet = IOFactory::load($path)->getActiveSheet();

        // Numeric-indexed rows and columns (not cell coordinates like
        // "A1"), calculated values (a formula cell yields its result,
        // not the formula string): the shape this needs is identical to
        // a CSV row, a plain list of cell values in column order.
        $rows = $sheet->toArray(null, true, true, false);
        $headers = array_map(strval(...), array_shift($rows) ?? []);

        return $this->processRecords($this->keyRowsByHeader($headers, $rows));
    }

    /**
     * @param  iterable<array<string, string>>  $records
     */
    private function processRecords(iterable $records): CatalogImportReport
    {
        $created = 0;
        $updated = 0;
        $issues = [];
        $rowNumber = 1;

        foreach ($records as $record) {
            $rowNumber++;

            try {
                $wasCreated = $this->importRow($record);
                $wasCreated ? $created++ : $updated++;
            } catch (DomainValidationException $exception) {
                $issues[] = new CatalogImportRowIssue($rowNumber, $exception->getMessage(), $record);
            }
        }

        return new CatalogImportReport($created, $updated, $issues);
    }

    /**
     * @param  string[]  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @return iterable<array<string, string>>
     */
    private function keyRowsByHeader(array $headers, array $rows): iterable
    {
        foreach ($rows as $row) {
            $record = [];

            foreach ($headers as $index => $header) {
                // A cell PhpSpreadsheet reads as a float (e.g. a
                // list_price typed as a number rather than text) must
                // still reach importRow() as a plain string, the same
                // type every CSV cell already is.
                $record[$header] = trim((string) ($row[$index] ?? ''));
            }

            yield $record;
        }
    }

    /**
     * @param  array<string, string>  $record
     */
    private function importRow(array $record): bool
    {
        foreach (self::REQUIRED_COLUMNS as $column) {
            if (trim($record[$column] ?? '') === '') {
                throw DomainValidationException::withContext("Missing required column [{$column}].");
            }
        }

        try {
            UnspscCode::fromString($record['unspsc_code']);
        } catch (Throwable $exception) {
            throw DomainValidationException::withContext($exception->getMessage());
        }

        $categoryId = $this->resolveCategoryId($record['category'] ?? null);

        $existing = Product::query()->where('sku', $record['sku'])->exists();

        Product::query()->updateOrCreate(
            ['sku' => $record['sku']],
            [
                'supplier_part_id' => $this->nullableColumn($record, 'supplier_part_id') ?? $record['sku'],
                'supplier_part_auxiliary_id' => $this->nullableColumn($record, 'supplier_part_auxiliary_id'),
                'manufacturer_part_id' => $this->nullableColumn($record, 'manufacturer_part_id'),
                'manufacturer_name' => $this->nullableColumn($record, 'manufacturer_name'),
                'name' => $record['name'],
                'description' => $record['description'] ?? $record['name'],
                'long_description' => $this->nullableColumn($record, 'long_description'),
                'category_id' => $categoryId,
                'unspsc_code' => $record['unspsc_code'],
                'unit_of_measure' => $record['unit_of_measure'],
                'pack_size' => ($this->nullableColumn($record, 'pack_size') !== null) ? (int) $record['pack_size'] : null,
                'lead_time_days' => (int) ($record['lead_time_days'] ?? 0),
                'list_price' => $record['list_price'],
                'currency' => strtoupper($record['currency']),
                'image_path' => $this->nullableColumn($record, 'image_path'),
                'is_active' => true,
            ],
        );

        return ! $existing;
    }

    /**
     * @param  array<string, string>  $record
     */
    private function nullableColumn(array $record, string $column): ?string
    {
        $value = trim($record[$column] ?? '');

        return $value === '' ? null : $value;
    }

    private function resolveCategoryId(?string $categoryName): ?int
    {
        $categoryName = trim((string) $categoryName);

        if ($categoryName === '') {
            return null;
        }

        $slug = str($categoryName)->slug()->toString();

        return Category::query()
            ->firstOrCreate(['slug' => $slug], ['name' => $categoryName])
            ->id;
    }
}
