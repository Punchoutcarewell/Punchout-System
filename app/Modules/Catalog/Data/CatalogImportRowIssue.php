<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

final readonly class CatalogImportRowIssue
{
    /**
     * @param  array<string, string>  $row
     */
    public function __construct(
        public int $rowNumber,
        public string $reason,
        public array $row,
    ) {}
}
