<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources;

use App\Modules\Admin\Filament\Resources\PunchoutCredentialResource\Pages;
use App\Modules\Punchout\Enums\PunchoutEnvironment;
use App\Modules\Punchout\Models\PunchoutCredential;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * This is the model that makes Coupa credential onboarding a config event
 * rather than a deploy: once GPCS issues test (and later production)
 * credentials, an admin fills this form in and the same code path that
 * runs against punchout:simulate starts authenticating real requests, no
 * code change required.
 *
 * shared_secret is deliberately write-only end to end: never pre-filled
 * with the real value on edit (see EditPunchoutCredential), never shown
 * in the table, and only saved at all if the admin actually types a new
 * value, see the dehydrated() callback on the form field.
 */
final class PunchoutCredentialResource extends Resource
{
    protected static ?string $model = PunchoutCredential::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Punchout';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identity')
                ->columns(2)
                ->schema([
                    Select::make('environment')
                        ->options([
                            PunchoutEnvironment::Test->value => 'Test',
                            PunchoutEnvironment::Production->value => 'Production',
                        ])
                        ->required(),
                    Toggle::make('is_active')->default(true),
                    TextInput::make('from_domain')
                        ->label('Buyer domain (From)')
                        ->required()
                        ->helperText('The buying organisation this credential authenticates, e.g. DUNS.'),
                    TextInput::make('from_identity')
                        ->label('Buyer identity (From)')
                        ->required()
                        ->helperText("Coupa's own identity for this buyer, e.g. COUPA1."),
                    TextInput::make('to_domain')
                        ->label('Buyer domain (To)')
                        ->required()
                        ->helperText('e.g. DUNS'),
                    TextInput::make('to_identity')
                        ->label('Buyer identity (To)')
                        ->required(),
                    TextInput::make('sender_domain')
                        ->label('Sender domain')
                        ->required(),
                    TextInput::make('sender_identity')
                        ->label('Sender identity')
                        ->required(),
                ]),
            Section::make('Secret')
                ->schema([
                    TextInput::make('shared_secret')
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText('Write-only: never shown again after saving. Leave blank when editing to keep the current secret unchanged.'),
                    TextInput::make('protocol')
                        ->default('cxml')
                        ->disabled()
                        ->dehydrated()
                        ->helperText("Coupa's own configuration only accepts cXML."),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('environment'),
                TextColumn::make('from_identity')->label('Buyer (From)'),
                TextColumn::make('to_domain')->label('Buyer domain'),
                TextColumn::make('to_identity')->label('Buyer identity'),
                TextColumn::make('protocol'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('environment');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPunchoutCredentials::route('/'),
            'create' => Pages\CreatePunchoutCredential::route('/create'),
            'edit' => Pages\EditPunchoutCredential::route('/{record}/edit'),
        ];
    }
}
