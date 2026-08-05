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
 * @property int $pack_size
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

    public function activeContractPrice(?Carbon $asOf = null): ?ContractPrice
    {
        $date = $asOf ?? now();

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
            // win ties deterministically.
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}
