<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $contract_reference
 * @property string $price
 * @property string $currency
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 */
final class ContractPrice extends Model
{
    protected $table = 'contract_prices';

    protected $fillable = ['product_id', 'contract_reference', 'price', 'currency', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return [
            // See Product::casts() for why this is explicit rather than left uncast.
            'price' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
