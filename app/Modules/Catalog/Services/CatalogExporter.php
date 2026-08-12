<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use League\Csv\Writer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Writes every product to a fresh temp file in either format, for the
 * Admin "Export" action to hand back as a download. Deliberately the
 * mirror image of CatalogImporter's own column layout, plus stock_quantity
 * and is_active: this is a full snapshot for a human to review, not just
 * what CatalogImporter::importRow() reads back in, an extra column here
 * is inert on a later re-import (see CatalogImporter, unrecognised
 * columns are simply not read).
 */
final class CatalogExporter
{
    private const COLUMNS = [
        'sku', 'supplier_part_id', 'supplier_part_auxiliary_id', 'manufacturer_part_id',
        'manufacturer_name', 'name', 'description', 'long_description', 'category',
        'unspsc_code', 'unit_of_measure', 'pack_size', 'stock_quantity', 'lead_time_days',
        'list_price', 'currency', 'image_path', 'is_active',
    ];

    public function exportToCsvFile(): string
    {
        $path = $this->freshTempPath('csv');

        $writer = Writer::from($path, 'w+');
        $writer->insertOne(self::COLUMNS);
        $writer->insertAll($this->rows());

        return $path;
    }

    public function exportToExcelFile(): string
    {
        $path = $this->freshTempPath('xlsx');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(self::COLUMNS, null, 'A1');
        $sheet->fromArray(iterator_to_array($this->rows()), null, 'A2');

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * @return iterable<int, array<int, string>>
     */
    private function rows(): iterable
    {
        // cursor(), not get(): a full-catalogue export has no natural
        // upper bound the way a paginated admin table does, this must
        // not hold every Product in memory at once as the catalogue
        // grows.
        foreach (Product::query()->with('category')->orderBy('name')->cursor() as $product) {
            yield [
                $product->sku,
                $product->supplier_part_id,
                (string) $product->supplier_part_auxiliary_id,
                (string) $product->manufacturer_part_id,
                (string) $product->manufacturer_name,
                $product->name,
                $product->description,
                (string) $product->long_description,
                $product->category !== null ? $product->category->name : '',
                $product->unspsc_code,
                $product->unit_of_measure,
                $product->pack_size !== null ? (string) $product->pack_size : '',
                (string) $product->stock_quantity,
                (string) $product->lead_time_days,
                $product->list_price,
                $product->currency,
                (string) $product->image_path,
                $product->is_active ? '1' : '0',
            ];
        }
    }

    private function freshTempPath(string $extension): string
    {
        $directory = storage_path('app/private/exports');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory.'/catalog-export-'.now()->format('Ymd-His-u').'.'.$extension;
    }
}
