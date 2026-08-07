<?php

declare(strict_types=1);

use App\Modules\Orders\Enums\PurchaseOrderStatus;
use App\Modules\Orders\Mail\PurchaseOrderReceivedMail;
use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Orders\Models\PurchaseOrderLine;
use App\Modules\Orders\Services\PurchaseOrderNotifier;
use Illuminate\Support\Facades\Mail;

it('sends the purchase order email to the configured notification address', function () {
    Mail::fake();

    config(['orders.notification_email' => 'ops@example.com']);

    $purchaseOrder = PurchaseOrder::query()->create([
        'po_number' => 'PO-1',
        'order_date' => now(),
        'total' => '25.99',
        'currency' => 'AUD',
        'buyer_reference' => 'REQ-123',
        'raw_payload' => '<raw-xml/>',
        'status' => PurchaseOrderStatus::Received,
        'received_at' => now(),
    ]);

    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_number' => 1,
        'supplier_part_id' => 'CW-4021',
        'quantity' => 1,
        'unit_price' => '25.99',
        'unit_of_measure' => 'BX',
        'description' => 'Foam Wound Dressing',
    ]);

    (new PurchaseOrderNotifier)->notify($purchaseOrder->load('lines'));

    Mail::assertSent(
        PurchaseOrderReceivedMail::class,
        fn (PurchaseOrderReceivedMail $mail): bool => $mail->hasTo('ops@example.com')
            && $mail->purchaseOrder->po_number === 'PO-1',
    );
});
