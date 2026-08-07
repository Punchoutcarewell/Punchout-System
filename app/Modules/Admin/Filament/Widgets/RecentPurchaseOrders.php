<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Widgets;

use App\Modules\Admin\Filament\Resources\PurchaseOrderResource;
use App\Modules\Orders\Models\PurchaseOrder;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

final class RecentPurchaseOrders extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent purchase orders';

    // See AdminStatsOverview for why this app's dashboard widgets opt out
    // of Filament's default lazy (post-mount follow-up request) loading.
    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(PurchaseOrder::query()->latest('received_at'))
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('po_number')->label('PO number'),
                TextColumn::make('total')->money(fn (PurchaseOrder $record): string => $record->currency),
                TextColumn::make('status')->badge(),
                IconColumn::make('has_discrepancy')
                    ->label('Discrepancy')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success'),
                TextColumn::make('received_at')->dateTime()->since(),
            ])
            ->recordUrl(fn (PurchaseOrder $record): string => PurchaseOrderResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('viewAll')
                    ->label('View all')
                    ->url(fn (): string => PurchaseOrderResource::getUrl('index'))
                    ->icon('heroicon-o-arrow-right'),
            ]);
    }
}
