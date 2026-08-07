<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Pages;

use App\Modules\Punchout\Contracts\SessionManagerInterface;
use App\Modules\Punchout\Enums\PunchoutSessionStatus;
use App\Modules\Punchout\Models\PunchoutCredential;
use App\Modules\Punchout\Models\PunchoutSession;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Generates a PunchoutSession the same way a real PunchOutSetupRequest
 * would, without needing Coupa, a shared secret, or the cXML round trip:
 * see SessionManager::startPreview(). Existing for exactly one reason,
 * previewing the storefront (and, if an admin clicks all the way through,
 * the transfer flow) is otherwise only possible via punchout:simulate on
 * the command line or by waiting for a real Coupa session.
 *
 * @property-read Form $form InteractsWithForms resolves this as a Livewire computed property, PHPStan cannot see that without this annotation
 */
final class PreviewTokenGenerator extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Test Token Generator';

    protected static ?string $navigationGroup = 'Punchout';

    protected static string $view = 'filament.pages.preview-token-generator';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public ?string $generatedUrl = null;

    public function mount(): void
    {
        $this->form->fill([
            'credential_id' => PunchoutCredential::query()->where('is_active', true)->value('id'),
            'label' => 'Storefront preview',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('credential_id')
                    ->label('Preview as')
                    ->options(fn (): array => PunchoutCredential::query()
                        ->where('is_active', true)
                        ->get()
                        ->mapWithKeys(fn (PunchoutCredential $credential): array => [
                            $credential->id => "{$credential->environment->value}: {$credential->from_identity} \u{2192} {$credential->to_identity}",
                        ])
                        ->all())
                    ->required()
                    ->helperText('The buyer identity the generated session will carry, matching one of your configured punchout credentials.'),
                TextInput::make('label')
                    ->label('Label')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Shown in the session rail and the list below, purely for your own reference.'),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
        $state = $this->form->getState();

        $credential = PunchoutCredential::query()->find($state['credential_id']);

        if ($credential === null) {
            Notification::make()->title('That credential no longer exists')->danger()->send();

            return;
        }

        $session = app(SessionManagerInterface::class)->startPreview($credential, $state['label']);

        $this->generatedUrl = route('punchout.start', ['token' => $session->token]);

        Notification::make()->title('Preview link generated')->success()->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PunchoutSession::query()
                    ->where('is_preview', true)
                    ->where('status', PunchoutSessionStatus::Active)
                    ->where('expires_at', '>', now())
                    ->latest('id'),
            )
            ->heading('Active preview links')
            ->columns([
                TextColumn::make('buyer_unique_name')->label('Label'),
                TextColumn::make('from_identity')->label('Buyer identity'),
                TextColumn::make('created_at')->label('Generated')->dateTime()->since(),
                TextColumn::make('expires_at')->label('Expires')->dateTime()->since(),
            ])
            ->actions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (PunchoutSession $record): string => route('punchout.start', ['token' => $record->token]))
                    ->openUrlInNewTab(),
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (PunchoutSession $record): void {
                        $record->update(['status' => PunchoutSessionStatus::Expired]);
                    }),
            ]);
    }
}
