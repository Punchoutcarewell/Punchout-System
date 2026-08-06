<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $sku
 * @property string $supplier_part_id
 * @property string|null $supplier_part_auxiliary_id
 * @property string|null $manufacturer_part_id
 * @property string|null $manufacturer_name
 * @property string $name
 * @property string $description
 * @property string|null $long_description
 * @property int|null $category_id
 * @property string $unspsc_code
 * @property string $unit_of_measure
 * @property int|null $pack_size null means this product is not sold in packs: quantity is a plain count of unit_of_measure units, and list_price/contract price is per unit. A real value means quantity counts packs, and price is per pack.
 * @property int $stock_quantity units on hand. Never gates order receipt: a purchase order that oversells this goes negative rather than being rejected, see InventoryService::deduct(). A negative value is itself the count of extra units Admin needs to bring in.
 * @property int $lead_time_days
 * @property string $list_price
 * @property string $currency
 * @property string|null $image_path
 * @property bool $is_active
 * @property-read Category|null $category
 * @property-read Collection<int, ContractPrice> $contractPrices
 */
final class Product extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'sku',
        'supplier_part_id',
        'supplier_part_auxiliary_id',
        'manufacturer_part_id',
        'manufacturer_name',
        'name',
        'description',
        'long_description',
        'category_id',
        'unspsc_code',
        'unit_of_measure',
        'pack_size',
        'stock_quantity',
        'lead_time_days',
        'list_price',
        'currency',
        'image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'category_id' => 'integer',
            'pack_size' => 'integer',
            'stock_quantity' => 'integer',
            'lead_time_days' => 'integer',
            // Explicit decimal cast: sqlite's PDO driver returns an
            // uncast decimal column as a PHP float, MySQL/Postgres return
            // a string. This forces a consistent fixed-precision string
            // on every driver, which is what Money::fromDecimal() expects.
            'list_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ContractPrice, $this>
     */
    public function contractPrices(): HasMany
    {
        return $this->hasMany(ContractPrice::class);
    }

    public function hasShortfall(): bool
    {
        return $this->stock_quantity < 0;
    }

    /**
     * How many extra units Admin needs to bring in to bring stock back to
     * zero. 0 for any product that is not oversold, never negative.
     */
    public function shortfallQuantity(): int
    {
        return $this->hasShortfall() ? abs($this->stock_quantity) : 0;
    }

    /**
     * When contractPrices has already been eager-loaded (see
     * PricingService::resolveContractPrices(), which loads it for every
     * product in one query precisely so this can avoid one), this resolves
     * entirely in memory instead of issuing a fresh query, the same way
     * Catalog\Services\CatalogSearchService and CartSnapshotFactory rely on
     * loadMissing()'d relations elsewhere. Falls back to the query-based
     * path otherwise, so a single, unbatched lookup (e.g.
     * ProductPageController) is unaffected.
     */
    public function activeContractPrice(?Carbon $asOf = null): ?ContractPrice
    {
        $date = $asOf ?? now();

        if ($this->relationLoaded('contractPrices')) {
            return self::pickActiveContractPrice($this->contractPrices, $date);
        }

        return $this->contractPrices()
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            // Two contract prices can share the same effective_from (e.g. a
            // correction re-entered on the same date). Without a secondary
            // sort key, which one "wins" is whatever order the database
            // happens to return matching rows in, not something this app
            // controls. id descending makes the most recently created row
            // win ties deterministically. Kept identical to
            // pickActiveContractPrice()'s in-memory ordering below, see
            // ProductContractPriceParityTest for the test that guards it.
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  Collection<int, ContractPrice>  $contractPrices
     */
    private static function pickActiveContractPrice(Collection $contractPrices, Carbon $date): ?ContractPrice
    {
        return $contractPrices
            ->filter(fn (ContractPrice $contractPrice): bool => $contractPrice->effective_from->lte($date)
                && ($contractPrice->effective_to === null || $contractPrice->effective_to->gte($date)))
            // Two stable sorts, lower-priority key first: sorting by id
            // desc and then by effective_from desc leaves ties (same
            // effective_from) still ordered by id desc, since a stable
            // sort preserves relative order among equal keys from the
            // previous pass. Same net ordering as the SQL ORDER BY above.
            ->sortByDesc('id')
            ->sortByDesc('effective_from')
            ->first();
    }
}
