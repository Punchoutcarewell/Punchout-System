<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Widgets\AdminStatsOverview;
use App\Modules\Admin\Filament\Widgets\PurchaseOrdersChart;
use App\Modules\Admin\Filament\Widgets\RecentPurchaseOrders;
use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Orders\Services\PurchaseOrderService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('renders the dashboard with the new widgets for an authenticated admin', function () {
    actingAsAdmin();

    $this->get('/admin')
        ->assertOk()
        ->assertSeeLivewire(AdminStatsOverview::class)
        ->assertSeeLivewire(PurchaseOrdersChart::class)
        ->assertSeeLivewire(RecentPurchaseOrders::class);
});

it('counts active punchout sessions, products, and credentials on the stats widget', function () {
    actingAsAdmin();
    issueTestPunchoutSession();
    createTestProduct(['is_active' => true]);
    createTestProduct(['is_active' => false]);

    Livewire::test(AdminStatsOverview::class)
        ->assertSuccessful()
        ->assertSee('Active punchout sessions')
        ->assertSee('Active products');
});

it('flags purchase orders with a discrepancy on the stats widget', function () {
    actingAsAdmin();
    Queue::fake();
    app(PurchaseOrderService::class)->receive(sampleOrderRequestData('PO-DASH-1'), '<raw-xml/>');

    Livewire::test(AdminStatsOverview::class)
        ->assertSuccessful()
        ->assertSee('Flagged for review');
});

it('lists recent purchase orders on the table widget', function () {
    actingAsAdmin();
    Queue::fake();
    app(PurchaseOrderService::class)->receive(sampleOrderRequestData('PO-DASH-2'), '<raw-xml/>');

    Livewire::test(RecentPurchaseOrders::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(PurchaseOrder::where('po_number', 'PO-DASH-2')->get());
});

it('renders the purchase orders chart without error when there is no data', function () {
    actingAsAdmin();

    Livewire::test(PurchaseOrdersChart::class)->assertSuccessful();
});
