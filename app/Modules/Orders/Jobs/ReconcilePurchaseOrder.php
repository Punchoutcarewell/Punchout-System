<?php

declare(strict_types=1);

namespace App\Modules\Orders\Jobs;

use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Orders\Services\PurchaseOrderReconciler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued for the same reason SendPurchaseOrderNotification is: reconciling
 * against the catalogue means calling into Catalog's PricingService, and
 * none of that should be able to slow down or fail the cXML Response
 * Coupa is waiting on for /punchout/order.
 */
final class ReconcilePurchaseOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $tries = 3;

    public function __construct(public readonly int $purchaseOrderId) {}

    public function handle(PurchaseOrderReconciler $reconciler): void
    {
        $purchaseOrder = PurchaseOrder::query()->with('lines')->findOrFail($this->purchaseOrderId);

        $reconciler->reconcile($purchaseOrder);
    }
}
