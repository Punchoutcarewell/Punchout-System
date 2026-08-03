<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One cart per punchout session (session_id is unique). session_id is a
 * plain foreign key into punchout_sessions at the database level only,
 * referential integrity is a database concern, application code in this
 * module never queries the Punchout module's models directly.
 *
 * @property int $id
 * @property int $session_id
 * @property string $total
 * @property string $currency
 * @property-read Collection<int, CartItem> $items
 */
final class Cart extends Model
{
    protected $table = 'carts';

    protected $fillable = ['session_id', 'total', 'currency'];

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
