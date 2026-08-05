<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A negotiated price for a product over a date range, e.g. "$25.99 AUD
 * from 2026-01-01".
 *
 * Scoped only to product_id and a date range, there is no buyer or
 * customer column. This assumes a single contract catalogue serving every
 * Amazon Business buyer that reaches this storefront, mirroring
 * config/cart.php's single-currency assumption. Whether Carewell's
 * contract with Amazon actually varies pricing by buyer, business unit,
 * or country is an open question for GPCS; if it does, this table needs a
 * buyer-scoping column and activeContractPrice() needs to filter on it.
 * Until then this is the deliberate working assumption, not an oversight.
 *
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
            'product_id' => 'integer',
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
