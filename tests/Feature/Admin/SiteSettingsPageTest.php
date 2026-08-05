<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Pages\SiteSettingsPage;
use App\Shared\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('lets an admin upload a logo', function () {
    Storage::fake('public');
    actingAsAdmin();

    Livewire::test(SiteSettingsPage::class)
        ->fillForm(['logo_path' => UploadedFile::fake()->image('logo.png')])
        ->call('save')
        ->assertHasNoFormErrors();

    $logoPath = SiteSetting::current()->logo_path;

    expect($logoPath)->not->toBeNull();
    Storage::disk('public')->assertExists($logoPath);
});

it('deletes the previous logo file when it is replaced', function () {
    Storage::fake('public');
    actingAsAdmin();

    $component = Livewire::test(SiteSettingsPage::class)
        ->fillForm(['logo_path' => UploadedFile::fake()->image('first.png')])
        ->call('save');

    $firstPath = SiteSetting::current()->logo_path;
    Storage::disk('public')->assertExists($firstPath);

    // Simulates the form state a second, real upload would leave behind
    // (FileUpload's internal shape is [uuid => path]) directly, rather
    // than issuing a second fillForm() with another UploadedFile fake on
    // the same component: Livewire's testing harness does not cleanly
    // support swapping an already-set FileUpload field mid-test, a
    // testing-harness limitation unrelated to save()'s own logic, which
    // is what this test is actually checking.
    Storage::disk('public')->put('branding/second-logo.png', 'second-logo-content');
    $component->set('data.logo_path', ['test-uuid' => 'branding/second-logo.png']);
    $component->call('save');

    $secondPath = SiteSetting::current()->logo_path;

    expect($secondPath)->toBe('branding/second-logo.png');
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);
});

it('pre-fills the form with the current logo on mount', function () {
    Storage::fake('public');
    actingAsAdmin();
    // FileUpload only recognises a path as a real file if it actually
    // exists on disk; without this the field silently hydrates to an
    // empty array regardless of what logo_path holds.
    Storage::disk('public')->put('branding/existing.png', 'fake-logo-content');
    SiteSetting::current()->update(['logo_path' => 'branding/existing.png']);

    $component = Livewire::test(SiteSettingsPage::class);

    // FileUpload's internal representation is [uuid => path], not the
    // raw path string, so this checks the resolved value rather than
    // comparing directly against the stored column value.
    expect(array_values($component->get('data.logo_path')))->toBe(['branding/existing.png']);
});

it('requires admin authentication', function () {
    $this->get(SiteSettingsPage::getUrl())->assertRedirect('/admin/login');
});
