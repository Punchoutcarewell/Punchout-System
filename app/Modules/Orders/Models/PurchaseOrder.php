<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The purchase order Coupa sends back once the buyer submits their
 * requisition. Created from a Punchout\Data\OrderRequestData by
 * PurchaseOrderService; this model itself has no cXML knowledge.
 *
 * @property int $id
 * @property string $po_number
 * @property Carbon $order_date
 * @property string $total
 * @property string $currency
 * @property string|null $buyer_reference
 * @property int|null $punchout_session_id opaque FK into Punchout's punchout_sessions,
 *                                         only ever set or read as a plain int: see PurchaseOrderServiceInterface::receive()
 * @property string $raw_payload
 * @property PurchaseOrderStatus $status
 * @property Carbon $received_at
 * @property-read Collection<int, PurchaseOrderLine> $lines
 */
final class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    protected $fillable = [
        'po_number',
        'order_date',
        'total',
        'currency',
        'buyer_reference',
        'punchout_session_id',
        'raw_payload',
        'status',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'datetime',
            'punchout_session_id' => 'integer',
            // Explicit decimal cast: sqlite's PDO driver returns an
            // uncast decimal column as a PHP float, MySQL/Postgres return
            // a string. This forces a consistent fixed-precision string
            // on every driver, which is what Money::fromDecimal() expects.
            'total' => 'decimal:2',
            'status' => PurchaseOrderStatus::class,
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }
}
