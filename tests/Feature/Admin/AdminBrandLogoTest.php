<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Pages\SiteSettingsPage;
use App\Shared\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('falls back to text branding when no logo is configured', function () {
    actingAsAdmin();

    $this->get('/admin')->assertOk()->assertSee('Carewell PunchOut');
});

it('stores an uploaded logo under its original filename, not a random one', function () {
    Storage::fake('public');
    actingAsAdmin();

    Livewire::test(SiteSettingsPage::class)
        ->fillForm(['logo_path' => UploadedFile::fake()->image('carewell-logo.png')])
        ->call('save');

    expect(SiteSetting::current()->logo_path)->toBe('branding/carewell-logo.png');
    Storage::disk('public')->assertExists('branding/carewell-logo.png');
});

it('renders the configured logo as an image in the admin panel', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/logo.png', 'logo-content');
    SiteSetting::current()->update(['logo_path' => 'branding/logo.png']);
    actingAsAdmin();

    $this->get('/admin')->assertOk()->assertSee(Storage::disk('public')->url('branding/logo.png'), false);
});
