<x-mail::message>
# New Purchase Order Received

**PO Number:** {{ $purchaseOrder->po_number }}
**Received:** {{ $purchaseOrder->received_at->format('d M Y, g:i A') }}
**Total:** {{ $purchaseOrder->currency }} {{ $purchaseOrder->total }}
@if($purchaseOrder->buyer_reference)
**Buyer Reference:** {{ $purchaseOrder->buyer_reference }}
@endif

<x-mail::table>
| Line | Supplier Part ID | Description | Qty | Unit Price | UoM |
| :--- | :--- | :--- | ---: | ---: | :--- |
@foreach($purchaseOrder->lines as $line)
| {{ $line->line_number }} | {{ $line->supplier_part_id }} | {{ $line->description }} | {{ $line->quantity }} | {{ $line->unit_price }} | {{ $line->unit_of_measure }} |
@endforeach
</x-mail::table>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
