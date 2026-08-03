<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Contracts\CatalogSearchInterface;
use App\Modules\Catalog\Data\CategorySummary;
use App\Modules\Catalog\Data\ProductDetail;
use App\Modules\Catalog\Data\ProductSummary;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Shared\ValueObjects\Money;
use App\Shared\ValueObjects\UnspscCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Product search and category browse. PostgreSQL (staging/production)
 * uses the generated tsvector column and GIN index from the
 * add_searchable_to_products_table migration; other drivers (sqlite,
 * used locally and in the test suite) fall back to a LIKE-based query.
 *
 * Contract pricing is deliberately not joined into this search: accuracy
 * there matters more than shaving a query, so it stays behind
 * PricingServiceInterface and gets resolved separately, per product, when
 * actually needed.
 */
final class CatalogSearchService implements CatalogSearchInterface
{
    public function search(?string $query, ?int $categoryId = null, int $perPage = 24): LengthAwarePaginator
    {
        $builder = Product::query()->where('is_active', true)->with('category');

        if ($categoryId !== null) {
            $builder->where('category_id', $categoryId);
        }

        if ($query !== null && trim($query) !== '') {
            $this->applySearch($builder, trim($query));
        }

        $productPage = $builder->orderBy('name')->paginate($perPage);

        // A new paginator, not setCollection() on $productPage: that would
        // leave a LengthAwarePaginator<Product> actually holding
        // ProductSummary items, correct at runtime, unsound for anything
        // typed against it afterward.
        return new LengthAwarePaginator(
            items: $productPage->getCollection()->map(fn (Product $product): ProductSummary => $this->toSummary($product)),
            total: $productPage->total(),
            perPage: $productPage->perPage(),
            currentPage: $productPage->currentPage(),
            options: ['path' => $productPage->path()],
        );
    }

    public function find(string $sku): ?ProductDetail
    {
        $product = Product::query()->where('sku', $sku)->where('is_active', true)->with('category')->first();

        if ($product === null) {
            return null;
        }

        return new ProductDetail(
            sku: $product->sku,
            name: $product->name,
            description: $product->description,
            longDescription: $product->long_description,
            categoryName: $product->category?->name,
            unspscCode: UnspscCode::fromString($product->unspsc_code),
            unitOfMeasure: $product->unit_of_measure,
            packSize: $product->pack_size,
            leadTimeDays: $product->lead_time_days,
            listPrice: Money::fromDecimal($product->list_price, $product->currency),
            imagePath: $product->image_path,
            manufacturerName: $product->manufacturer_name,
            manufacturerPartId: $product->manufacturer_part_id,
        );
    }

    public function categories(): array
    {
        return Category::query()
            ->orderBy('position')
            ->get()
            ->map(fn (Category $category): CategorySummary => new CategorySummary(
                id: $category->id,
                name: $category->name,
                slug: $category->slug,
                parentId: $category->parent_id,
            ))
            ->all();
    }

    /**
     * @param  Builder<Product>  $builder
     */
    private function applySearch(Builder $builder, string $query): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $builder->whereRaw("searchable @@ plainto_tsquery('english', ?)", [$query]);

            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';

        $builder->where(function (Builder $inner) use ($like): void {
            $inner->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    private function toSummary(Product $product): ProductSummary
    {
        return new ProductSummary(
            id: $product->id,
            sku: $product->sku,
            name: $product->name,
            categoryName: $product->category?->name,
            unspscCode: UnspscCode::fromString($product->unspsc_code),
            listPrice: Money::fromDecimal($product->list_price, $product->currency),
            unitOfMeasure: $product->unit_of_measure,
            packSize: $product->pack_size,
            leadTimeDays: $product->lead_time_days,
            imagePath: $product->image_path,
        );
    }
}
