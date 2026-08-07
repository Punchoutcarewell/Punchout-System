<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a received purchase order. supplier_part_id is kept as a
 * plain string, not a foreign key into products: by the time a PO
 * arrives, the SKU it references might have changed or been deactivated
 * in the catalogue, the PO must still be a faithful, permanent record of
 * what Coupa actually sent.
 *
 * unit_price carries no separate currency column: a purchase order is
 * single-currency, callers use the parent PurchaseOrder's currency.
 *
 * @property int $id
 * @property int $purchase_order_id
 * @property int $line_number
 * @property string $supplier_part_id
 * @property int $quantity
 * @property string $unit_price
 * @property string $unit_of_measure
 * @property string $description
 */
final class PurchaseOrderLine extends Model
{
    public $timestamps = false;

    protected $table = 'purchase_order_lines';

    protected $fillable = [
        'purchase_order_id',
        'line_number',
        'supplier_part_id',
        'quantity',
        'unit_price',
        'unit_of_measure',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'purchase_order_id' => 'integer',
            'line_number' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
