<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources;

use App\Modules\Admin\Filament\Resources\ContractPriceResource\Pages;
use App\Modules\Catalog\Models\ContractPrice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ContractPriceResource extends Resource
{
    protected static ?string $model = ContractPrice::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Contract Prices';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('product_id')
                ->label('Product')
                ->relationship('product', 'sku')
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('contract_reference')
                ->label('Contract reference (C3N)')
                ->required(),
            TextInput::make('price')->numeric()->required()->prefix('$'),
            TextInput::make('currency')->required()->maxLength(3)->default('AUD'),
            DatePicker::make('effective_from')->required(),
            DatePicker::make('effective_to')->helperText('Leave blank for no expiry.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.sku')->label('SKU')->searchable()->sortable(),
                TextColumn::make('product.name')->label('Product')->limit(40),
                TextColumn::make('contract_reference')->label('C3N')->searchable(),
                TextColumn::make('price')->money(fn (ContractPrice $record): string => $record->currency),
                TextColumn::make('effective_from')->date()->sortable(),
                TextColumn::make('effective_to')->date()->placeholder('No expiry'),
            ])
            ->defaultSort('effective_from', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContractPrices::route('/'),
            'create' => Pages\CreateContractPrice::route('/create'),
            'edit' => Pages\EditContractPrice::route('/{record}/edit'),
        ];
    }
}
