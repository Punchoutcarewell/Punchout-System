<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Resources\PurchaseOrderResource;
use App\Modules\Admin\Filament\Resources\PurchaseOrderResource\Pages\ListPurchaseOrders;
use App\Modules\Admin\Filament\Resources\PurchaseOrderResource\Pages\ViewPurchaseOrder;
use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Orders\Services\PurchaseOrderService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('lists received purchase orders', function () {
    actingAsAdmin();
    Queue::fake();
    (app(PurchaseOrderService::class))->receive(sampleOrderRequestData('PO-1'), '<raw-xml/>');

    Livewire::test(ListPurchaseOrders::class)
        ->assertCanSeeTableRecords(PurchaseOrder::all());
});

it('has no create action: purchase orders only arrive from Coupa', function () {
    actingAsAdmin();

    expect(PurchaseOrderResource::canCreate())->toBeFalse();
});

it('shows the order header and lines on the view page', function () {
    actingAsAdmin();
    Queue::fake();
    (app(PurchaseOrderService::class))->receive(sampleOrderRequestData('PO-1'), '<raw-xml/>');

    $purchaseOrder = PurchaseOrder::query()->where('po_number', 'PO-1')->firstOrFail();

    Livewire::test(ViewPurchaseOrder::class, ['record' => $purchaseOrder->getRouteKey()])
        ->assertSee('PO-1')
        ->assertSee('CW-4021')
        ->assertSee('Foam Wound Dressing');
});
