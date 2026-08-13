<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

/**
 * The outcome of a bulk image upload: which files were matched to a
 * product by filename and applied, and which had no product to match.
 */
final readonly class ProductImageBulkUploadReport
{
    /**
     * @param  string[]  $unmatchedFilenames
     */
    public function __construct(
        public int $matched,
        public array $unmatchedFilenames,
    ) {}

    public function hasUnmatched(): bool
    {
        return $this->unmatchedFilenames !== [];
    }
}
