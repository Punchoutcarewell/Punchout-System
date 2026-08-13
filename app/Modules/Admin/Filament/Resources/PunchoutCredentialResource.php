<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources;

use App\Modules\Admin\Filament\Resources\PunchoutCredentialResource\Pages;
use App\Modules\Punchout\Enums\PunchoutEnvironment;
use App\Modules\Punchout\Models\PunchoutCredential;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

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
 *
 * No audit log of who changed a credential (or, on ContractPriceResource,
 * a contract price) or when: every admin authenticates as a real User,
 * but there is currently no separate record of the edit itself. Both
 * resources sit exactly where an audit trail matters most, a wrong
 * shared_secret breaks every buyer's punchout session, a wrong contract
 * price is the precise thing the Category Manager's go-live check exists
 * to catch. Worth adding (a spatie/laravel-activitylog-style approach
 * would fit this Filament setup with minimal new code) once there is a
 * concrete need to answer "who changed this and when", not implemented
 * speculatively here.
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
                        ->suffixAction(
                            FormAction::make('generate')
                                ->label('Generate')
                                ->icon('heroicon-o-arrow-path')
                                // Same shape as SessionManager's session and
                                // preview tokens (Str::random(64)): not
                                // meaningful to Coupa, whose cXML spec
                                // treats SharedSecret as an opaque string,
                                // just a consistent "this is a generated
                                // credential, not a chosen password" look.
                                ->action(fn (Set $set) => $set('shared_secret', Str::random(64))),
                        )
                        ->helperText('Write-only: never shown again after saving. Leave blank when editing to keep the current secret unchanged. "Generate" fills in a random 64-character value, click the eye icon to reveal it before saving.'),
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
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->actions([
                // A labelled, confirmed action rather than a bare
                // ToggleColumn (compare ProductResource's is_active
                // toggle): revoking this immediately stops a buyer
                // authenticating real punchout traffic, that deserves
                // a deliberate click and a warning, not a stray misclick.
                TableAction::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (PunchoutCredential $record): bool => $record->is_active)
                    ->requiresConfirmation()
                    ->modalDescription('This credential will immediately stop authenticating punchout requests for this buyer identity.')
                    ->action(fn (PunchoutCredential $record) => $record->update(['is_active' => false])),
                TableAction::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PunchoutCredential $record): bool => ! $record->is_active)
                    ->action(fn (PunchoutCredential $record) => $record->update(['is_active' => true])),
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
