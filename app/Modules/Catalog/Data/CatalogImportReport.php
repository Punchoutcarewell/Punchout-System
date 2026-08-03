<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

/**
 * The outcome of a catalogue import: what succeeded and what didn't, with
 * a reason for every skipped row. An importer that fails silently on bad
 * data is exactly what this exists to avoid.
 */
final readonly class CatalogImportReport
{
    /**
     * @param  CatalogImportRowIssue[]  $issues
     */
    public function __construct(
        public int $created,
        public int $updated,
        public array $issues,
    ) {}

    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }

    public function totalProcessed(): int
    {
        return $this->created + $this->updated + count($this->issues);
    }
}
