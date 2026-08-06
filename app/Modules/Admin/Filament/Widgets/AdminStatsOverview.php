<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Widgets;

use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\PurchaseOrder;
use App\Modules\Punchout\Enums\PunchoutSessionStatus;
use App\Modules\Punchout\Models\PunchoutCredential;
use App\Modules\Punchout\Models\PunchoutSession;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class AdminStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    // These are a handful of count() queries, not worth the empty
    // first-paint every Filament widget defaults to (a follow-up request
    // fired after mount): the whole point of this widget is a dashboard
    // that is informative immediately, matching AccountWidget and
    // FilamentInfoWidget's own $isLazy = false.
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $activeSessions = PunchoutSession::query()
            ->where('status', PunchoutSessionStatus::Active)
            ->where('expires_at', '>', now())
            ->count();

        $ordersToday = PurchaseOrder::query()->whereDate('received_at', today())->count();

        $discrepancyCount = PurchaseOrder::query()->where('has_discrepancy', true)->count();

        $activeProducts = Product::query()->where('is_active', true)->count();

        $needsRestockCount = Product::query()->where('stock_quantity', '<', 0)->count();

        $activeCredentials = PunchoutCredential::query()->where('is_active', true)->count();

        return [
            Stat::make('Active punchout sessions', $activeSessions)
                ->description('Buyers currently shopping the storefront')
                ->icon('heroicon-o-users')
                ->color('success'),

            Stat::make('Purchase orders today', $ordersToday)
                ->description('Received since midnight')
                ->icon('heroicon-o-document-text')
                ->color('info'),

            Stat::make('Flagged for review', $discrepancyCount)
                ->description($discrepancyCount === 1 ? 'Purchase order with a discrepancy' : 'Purchase orders with a discrepancy')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($discrepancyCount > 0 ? 'warning' : 'success')
                ->url($discrepancyCount > 0 ? route('filament.admin.resources.purchase-orders.index') : null),

            Stat::make('Active products', $activeProducts)
                ->description('Live in the catalogue')
                ->icon('heroicon-o-cube')
                ->color('gray'),

            Stat::make('Needs restock', $needsRestockCount)
                ->description($needsRestockCount > 0 ? 'Products oversold, more stock needed' : 'No product is oversold')
                ->icon('heroicon-o-archive-box-x-mark')
                ->color($needsRestockCount > 0 ? 'danger' : 'success')
                ->url($needsRestockCount > 0 ? route('filament.admin.resources.products.index') : null),

            Stat::make('Active credentials', $activeCredentials)
                ->description($activeCredentials > 0 ? 'Buyer identities that can authenticate' : 'No credential configured yet')
                ->icon('heroicon-o-key')
                ->color($activeCredentials > 0 ? 'gray' : 'danger'),
        ];
    }
}
