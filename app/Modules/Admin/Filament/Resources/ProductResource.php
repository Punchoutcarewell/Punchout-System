<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources;

use App\Modules\Admin\Filament\Resources\ProductResource\Pages;
use App\Modules\Catalog\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Catalogue';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identity')
                ->columns(2)
                ->schema([
                    TextInput::make('sku')->required()->unique(ignoreRecord: true),
                    TextInput::make('supplier_part_id')
                        ->required()
                        ->helperText('The exact value that goes on the cXML SupplierPartID element.'),
                    TextInput::make('supplier_part_auxiliary_id'),
                    TextInput::make('manufacturer_part_id'),
                    TextInput::make('manufacturer_name'),
                ]),
            Section::make('Description')
                ->columns(1)
                ->schema([
                    TextInput::make('name')->required(),
                    Textarea::make('description')->required()->rows(2),
                    Textarea::make('long_description')->rows(4),
                    Select::make('category_id')
                        ->label('Category')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload(),
                ]),
            Section::make('Classification and units')
                ->columns(3)
                ->schema([
                    TextInput::make('unspsc_code')
                        ->label('UNSPSC code')
                        ->required()
                        ->minLength(8)
                        ->maxLength(8)
                        ->helperText('Exactly 8 digits.'),
                    TextInput::make('unit_of_measure')
                        ->label('Unit of measure')
                        ->required()
                        ->maxLength(10)
                        ->helperText('UN/CEFACT code, e.g. EA, BX, CS.'),
                    TextInput::make('pack_size')
                        ->numeric()
                        ->minValue(1)
                        ->nullable()
                        ->helperText('Leave empty if this product is not sold in packs. When set, list price and contract price are the price of one pack, not one unit.'),
                    TextInput::make('lead_time_days')->numeric()->default(0)->required(),
                ]),
            Section::make('Inventory')
                ->columns(1)
                ->schema([
                    TextInput::make('stock_quantity')
                        ->label('Stock on hand')
                        ->numeric()
                        ->integer()
                        ->default(0)
                        ->required()
                        ->helperText('Deducted automatically as purchase orders come in. A negative value means orders have outsold stock, that many extra units still need to be brought in.'),
                ]),
            Section::make('Pricing')
                ->columns(2)
                ->schema([
                    TextInput::make('list_price')->numeric()->required()->prefix('$'),
                    TextInput::make('currency')->required()->maxLength(3)->default('AUD'),
                ]),
            Section::make('Media and status')
                ->columns(2)
                ->schema([
                    FileUpload::make('image_path')
                        ->label('Image')
                        ->image()
                        ->disk('public')
                        ->directory('products')
                        ->visibility('public')
                        ->maxSize(5120)
                        ->imageEditor(),
                    Toggle::make('is_active')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')->label('')->disk('public'),
                TextColumn::make('sku')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->limit(40),
                TextColumn::make('category.name')->label('Category')->placeholder('None'),
                TextColumn::make('unspsc_code')->label('UNSPSC')->fontFamily('mono'),
                TextColumn::make('list_price')->money(fn (Product $record): string => $record->currency),
                TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->sortable()
                    ->color(fn (Product $record): string => match (true) {
                        $record->hasShortfall() => 'danger',
                        $record->stock_quantity === 0 => 'warning',
                        default => 'success',
                    })
                    ->description(fn (Product $record): ?string => $record->hasShortfall()
                        ? "{$record->shortfallQuantity()} needed"
                        : null),
                ToggleColumn::make('is_active'),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('Category')->relationship('category', 'name'),
                TernaryFilter::make('is_active'),
                Filter::make('needs_restock')
                    ->label('Needs restock')
                    ->query(fn (Builder $query): Builder => $query->where('stock_quantity', '<', 0)),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
