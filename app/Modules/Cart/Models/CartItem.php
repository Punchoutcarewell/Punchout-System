<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in a cart, keyed by sku rather than a Catalog product_id
 * foreign key: sku is a plain indexed string here, deliberately not a
 * database foreign key into products, the same reasoning as
 * products.unspsc_code not being a foreign key into unspsc_references.
 * This module never queries the products table directly; when it needs
 * authoritative product data it goes through
 * Catalog\Contracts\PricingServiceInterface.
 *
 * description, unit_price, and currency are a display cache captured
 * when the line was added or last updated, not the authoritative source
 * at transfer time, see CartSnapshotFactory.
 *
 * @property int $id
 * @property int $cart_id
 * @property string $sku
 * @property string $description
 * @property int $quantity
 * @property string $unit_price
 * @property string $currency
 */
final class CartItem extends Model
{
    protected $table = 'cart_items';

    protected $fillable = ['cart_id', 'sku', 'description', 'quantity', 'unit_price', 'currency'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }
}
