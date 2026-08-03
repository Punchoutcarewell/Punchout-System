<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Mail\PurchaseOrderReceivedMail;
use App\Modules\Orders\Models\PurchaseOrder;
use Illuminate\Support\Facades\Mail;

/**
 * How Carewell operations gets told a PO arrived. Separated from
 * SendPurchaseOrderNotification so "what the notification does" is
 * synchronously unit-testable, independent of "how it gets queued and
 * retried".
 */
final class PurchaseOrderNotifier
{
    public function notify(PurchaseOrder $purchaseOrder): void
    {
        Mail::to((string) config('orders.notification_email'))
            ->send(new PurchaseOrderReceivedMail($purchaseOrder));
    }
}
