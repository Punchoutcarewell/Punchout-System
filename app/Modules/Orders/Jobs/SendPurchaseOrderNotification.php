<?php

declare(strict_types=1);

namespace App\Modules\Orders\Jobs;

use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Orders\Services\PurchaseOrderNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued so a slow or briefly-down mail provider never holds up the
 * cXML Response Coupa is waiting on. Retries three times with increasing
 * backoff before giving up, per the architecture's "PO notification email
 * ... with a retry queue on failure".
 */
final class SendPurchaseOrderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $tries = 3;

    public function __construct(public readonly int $purchaseOrderId) {}

    public function handle(PurchaseOrderNotifier $notifier): void
    {
        $purchaseOrder = PurchaseOrder::query()->with('lines')->findOrFail($this->purchaseOrderId);

        $notifier->notify($purchaseOrder);
    }
}
