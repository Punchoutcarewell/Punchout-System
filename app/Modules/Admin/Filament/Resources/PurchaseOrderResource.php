<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources;

use App\Modules\Admin\Filament\Resources\PurchaseOrderResource\Pages;
use App\Modules\Orders\Models\PurchaseOrder;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only, deliberately: a purchase order is a faithful record of what
 * Coupa actually sent. There is no create, edit, or delete action here,
 * only List and View, see canCreate()/canEdit()/canDelete() below.
 */
final class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Punchout';

    protected static ?string $navigationLabel = 'Purchase Orders';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('po_number')->label('PO number')->searchable()->sortable(),
                TextColumn::make('order_date')->dateTime()->sortable(),
                TextColumn::make('total')->money(fn (PurchaseOrder $record): string => $record->currency),
                TextColumn::make('buyer_reference')->placeholder('None'),
                TextColumn::make('status')->badge(),
                IconColumn::make('has_discrepancy')
                    ->label('Discrepancy')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success'),
                TextColumn::make('received_at')->dateTime()->sortable(),
            ])
            ->defaultSort('received_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('Order')
                ->columns(3)
                ->schema([
                    TextEntry::make('po_number')->label('PO number'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('order_date')->dateTime(),
                    TextEntry::make('total')->money(fn (PurchaseOrder $record): string => $record->currency),
                    TextEntry::make('buyer_reference')->placeholder('None'),
                    TextEntry::make('received_at')->dateTime(),
                ]),
            InfolistSection::make('Catalogue reconciliation')
                ->visible(fn (PurchaseOrder $record): bool => $record->has_discrepancy)
                ->schema([
                    TextEntry::make('discrepancy_details')
                        ->label('')
                        ->color('warning')
                        ->columnSpanFull(),
                ]),
            InfolistSection::make('Lines')
                ->schema([
                    RepeatableEntry::make('lines')
                        ->label('')
                        ->columns(6)
                        ->schema([
                            TextEntry::make('line_number')->label('Line'),
                            TextEntry::make('supplier_part_id')->label('Supplier Part ID'),
                            TextEntry::make('description')->columnSpan(2),
                            TextEntry::make('quantity'),
                            TextEntry::make('unit_price')->label('Unit price'),
                        ]),
                ]),
            InfolistSection::make('Raw payload')
                ->collapsed()
                ->schema([
                    TextEntry::make('raw_payload')
                        ->label('')
                        ->fontFamily('mono')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
        ];
    }
}
