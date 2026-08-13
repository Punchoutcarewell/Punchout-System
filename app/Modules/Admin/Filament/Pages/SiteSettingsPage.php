<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Pages;

use App\Shared\Models\SiteSetting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

/**
 * A single-record settings form, not a Resource: there is exactly one
 * SiteSetting row (see SiteSetting::current()), a list/create/delete UI
 * would offer actions that make no sense for it.
 *
 * @property-read Form $form InteractsWithForms resolves this as a Livewire computed property, PHPStan cannot see that without this annotation
 */
final class SiteSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Site Settings';

    protected static ?string $navigationGroup = 'Settings';

    protected static string $view = 'filament.pages.site-settings-page';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SiteSetting::current()->only(['logo_path']));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('logo_path')
                    ->label('Site logo')
                    ->image()
                    ->disk('public')
                    ->directory('branding')
                    ->visibility('public')
                    ->maxSize(2048)
                    ->imageEditor()
                    ->preserveFilenames()
                    ->helperText('Shown in the Admin panel sidebar and the storefront header. SVG or PNG with a transparent background works best. Leave empty to use the default text branding.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $setting = SiteSetting::current();

        // The FileUpload component already moved the new file into place
        // under storage/app/public/branding by the time getState() runs;
        // this only removes the file the setting no longer points to, so
        // uploads never accumulate as orphans on every logo change.
        $previousPath = $setting->logo_path;

        if ($previousPath !== null && $previousPath !== $state['logo_path'] && Storage::disk('public')->exists($previousPath)) {
            Storage::disk('public')->delete($previousPath);
        }

        $setting->update($state);

        Notification::make()
            ->title('Site settings saved')
            ->success()
            ->send();
    }
}
