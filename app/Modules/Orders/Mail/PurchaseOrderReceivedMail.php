<?php

declare(strict_types=1);

namespace App\Modules\Orders\Mail;

use App\Modules\Orders\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class PurchaseOrderReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly PurchaseOrder $purchaseOrder) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Purchase Order Received: {$this->purchaseOrder->po_number}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'orders::mail.purchase-order-received');
    }
}
