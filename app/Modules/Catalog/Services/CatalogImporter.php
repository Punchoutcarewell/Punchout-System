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
use Throwable;

/**
 * Imports the catalogue from a CSV file into products, producing a
 * validation report rather than failing silently: a bad row is recorded
 * with a reason and the import continues, so one malformed line does not
 * block the SKUs behind it.
 *
 * Expected header row (column order does not matter): sku,
 * supplier_part_id, supplier_part_auxiliary_id, manufacturer_part_id,
 * manufacturer_name, name, description, long_description, category,
 * unspsc_code, unit_of_measure, pack_size, lead_time_days, list_price,
 * currency, image_path. pack_size is optional: leave it blank for a
 * product not sold in packs, list_price is then the price of one
 * unit_of_measure unit rather than one pack, see Product::$pack_size.
 *
 * This column layout is this project's own working assumption. It has
 * not been confirmed against a real export from Carewell's catalogue
 * source system (see roadmap Stage 0.1, "SKU count and catalogue source
 * format") and should be checked once that format is known.
 */
final class CatalogImporter
{
    private const REQUIRED_COLUMNS = ['sku', 'name', 'unspsc_code', 'unit_of_measure', 'list_price', 'currency'];

    public function importFromCsvFile(string $path): CatalogImportReport
    {
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        $created = 0;
        $updated = 0;
        $issues = [];
        $rowNumber = 1;

        foreach ($csv->getRecords() as $record) {
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
