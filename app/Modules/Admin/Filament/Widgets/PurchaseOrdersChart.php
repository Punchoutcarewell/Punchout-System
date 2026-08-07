<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Widgets;

use App\Modules\Orders\Models\PurchaseOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

final class PurchaseOrdersChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Purchase orders received (last 14 days)';

    protected int|string|array $columnSpan = 'full';

    // See AdminStatsOverview for why this app's dashboard widgets opt out
    // of Filament's default lazy (post-mount follow-up request) loading.
    protected static bool $isLazy = false;

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn (int $daysAgo): Carbon => today()->subDays($daysAgo));

        $counts = PurchaseOrder::query()
            ->where('received_at', '>=', $days->first())
            ->get()
            ->groupBy(fn (PurchaseOrder $purchaseOrder): string => $purchaseOrder->received_at->format('Y-m-d'))
            ->map->count();

        return [
            'datasets' => [
                [
                    'label' => 'Purchase orders',
                    'data' => $days->map(fn (Carbon $day): int => (int) ($counts[$day->format('Y-m-d')] ?? 0))->all(),
                    'backgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => $days->map(fn (Carbon $day): string => $day->format('M j'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
