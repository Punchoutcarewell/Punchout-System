<?php

declare(strict_types=1);

use App\Modules\Orders\Enums\PurchaseOrderStatus;
use App\Modules\Orders\Jobs\SendPurchaseOrderNotification;
use App\Modules\Orders\Mail\PurchaseOrderReceivedMail;
use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Orders\Services\PurchaseOrderNotifier;
use Illuminate\Support\Facades\Mail;

it('loads the purchase order and sends the notification when run', function () {
    Mail::fake();

    $purchaseOrder = PurchaseOrder::query()->create([
        'po_number' => 'PO-1',
        'order_date' => now(),
        'total' => '25.99',
        'currency' => 'AUD',
        'buyer_reference' => null,
        'raw_payload' => '<raw-xml/>',
        'status' => PurchaseOrderStatus::Received,
        'received_at' => now(),
    ]);

    (new SendPurchaseOrderNotification($purchaseOrder->id))->handle(app(PurchaseOrderNotifier::class));

    Mail::assertSent(PurchaseOrderReceivedMail::class);
});

it('retries up to 3 times with increasing backoff', function () {
    $job = new SendPurchaseOrderNotification(1);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([60, 300, 900]);
});
