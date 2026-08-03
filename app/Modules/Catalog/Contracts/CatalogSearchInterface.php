<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\ProductSummary;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Category browse and search. Returns ProductSummary DTOs, never Product
 * models, so a Storefront controller consuming this never touches
 * Eloquent.
 */
interface CatalogSearchInterface
{
    /**
     * @return LengthAwarePaginator<int, ProductSummary>
     */
    public function search(?string $query, ?int $categoryId = null, int $perPage = 24): LengthAwarePaginator;
}
