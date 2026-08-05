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
 * description, image_path, unit_price, currency, pack_size, and
 * unit_of_measure are a display cache captured when the line was added or
 * last updated, not the authoritative source at transfer time, see
 * CartSnapshotFactory. pack_size null means this line is not sold in
 * packs, quantity is a plain unit count and unit_price is per unit.
 *
 * @property int $id
 * @property int $cart_id
 * @property string $sku
 * @property string $description
 * @property string|null $image_path
 * @property int $quantity
 * @property string $unit_price
 * @property string $currency
 * @property int|null $pack_size
 * @property string|null $unit_of_measure
 */
final class CartItem extends Model
{
    protected $table = 'cart_items';

    protected $fillable = [
        'cart_id',
        'sku',
        'description',
        'image_path',
        'quantity',
        'unit_price',
        'currency',
        'pack_size',
        'unit_of_measure',
    ];

    protected function casts(): array
    {
        return [
            'cart_id' => 'integer',
            'quantity' => 'integer',
            'pack_size' => 'integer',
            // See Catalog\Models\Product::casts() for why this is explicit
            // rather than left uncast.
            'unit_price' => 'decimal:2',
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
