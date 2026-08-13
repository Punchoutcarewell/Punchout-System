<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Data\ProductImageBulkUploadReport;
use App\Modules\Catalog\Models\Product;

/**
 * Closes the loop left open by CatalogImporter: a CSV/Excel import can set
 * image_path to a filename (or a URL whose last path segment is one) for
 * an image that does not exist on this server yet. This matches a batch
 * of just-uploaded files, already stored under the "public" disk's
 * products/ directory with their original filename preserved (see
 * ListProducts' bulkUploadImages action, FileUpload::preserveFilenames()),
 * against every product whose image_path resolves to that same filename,
 * and points image_path at the real, now-local file.
 *
 * Matching is by exact filename, case included, the same value
 * FileUpload::preserveFilenames() stores it as. Two products that both
 * claim the same filename both get the same uploaded file, this does not
 * attempt to detect or resolve that as an error, since the CSV column is
 * the source of truth this trusts.
 */
final class ProductImageBulkUploader
{
    /**
     * @param  string[]  $storedRelativePaths  paths already on the "public" disk, e.g. "products/CW-9013.jpg"
     */
    public function applyUploadedImages(array $storedRelativePaths): ProductImageBulkUploadReport
    {
        $productsByFilename = Product::query()
            ->whereNotNull('image_path')
            ->get(['id', 'image_path'])
            ->groupBy(fn (Product $product): string => $this->filenameFor((string) $product->image_path));

        $matched = 0;
        $unmatched = [];

        foreach ($storedRelativePaths as $path) {
            $filename = basename($path);
            $products = $productsByFilename->get($filename);

            if ($products === null) {
                $unmatched[] = $filename;

                continue;
            }

            foreach ($products as $product) {
                $product->update(['image_path' => $path]);
                $matched++;
            }
        }

        return new ProductImageBulkUploadReport($matched, $unmatched);
    }

    /**
     * image_path may already be a real local path ("products/x.jpg"), or,
     * for a row imported before its image existed here, a bare filename
     * or a full external URL, e.g. "https://vendor.com/img/CW-9013.jpg".
     * Either way, the filename is the last path segment.
     */
    private function filenameFor(string $imagePath): string
    {
        return basename(parse_url($imagePath, PHP_URL_PATH) ?: $imagePath);
    }
}
