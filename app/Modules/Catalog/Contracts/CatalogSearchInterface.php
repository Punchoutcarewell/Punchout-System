<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\CategorySummary;
use App\Modules\Catalog\Data\ProductDetail;
use App\Modules\Catalog\Data\ProductSummary;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Category browse and search. Returns DTOs, never Product or Category
 * models, so a Storefront controller consuming this never touches
 * Eloquent.
 */
interface CatalogSearchInterface
{
    /**
     * @return LengthAwarePaginator<int, ProductSummary>
     */
    public function search(?string $query, ?int $categoryId = null, int $perPage = 24): LengthAwarePaginator;

    public function find(string $sku): ?ProductDetail;

    /**
     * @return CategorySummary[]
     */
    public function categories(): array;
}
