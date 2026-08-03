<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

/**
 * One row of the sidebar category tree. Returned as a flat list, the
 * frontend builds the nested tree client-side from parentId, keeping
 * this side simple.
 */
final readonly class CategorySummary
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public ?int $parentId,
    ) {}
}
