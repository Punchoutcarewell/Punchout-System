<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Pages\PreviewTokenGenerator;
use App\Modules\Punchout\Contracts\SessionManagerInterface;
use App\Modules\Punchout\Enums\PunchoutSessionStatus;
use App\Modules\Punchout\Models\PunchoutSession;
use Livewire\Livewire;

it('requires admin authentication', function () {
    $this->get(PreviewTokenGenerator::getUrl())->assertRedirect('/admin/login');
});

it('generates a preview session and shows its storefront URL', function () {
    actingAsAdmin();
    $credential = createTestPunchoutCredential('ALD');

    $component = Livewire::test(PreviewTokenGenerator::class)
        ->fillForm(['credential_id' => $credential->id, 'label' => 'Demo for the team'])
        ->call('generate');

    $component->assertHasNoFormErrors();

    $session = PunchoutSession::query()->where('is_preview', true)->firstOrFail();

    expect($component->get('generatedUrl'))->toBe(route('punchout.start', ['token' => $session->token]))
        ->and($session->buyer_unique_name)->toBe('Demo for the team')
        ->and($session->from_identity)->toBe($credential->from_identity);
});

it('lists active preview sessions in the table', function () {
    actingAsAdmin();
    $credential = createTestPunchoutCredential('ALD');
    $session = app(SessionManagerInterface::class)->startPreview($credential, 'Listed preview');

    Livewire::test(PreviewTokenGenerator::class)
        ->assertCanSeeTableRecords([$session]);
});

it('does not list an expired preview session', function () {
    actingAsAdmin();
    $credential = createTestPunchoutCredential('ALD');
    $session = app(SessionManagerInterface::class)->startPreview($credential, 'Expired preview');
    $session->update(['status' => PunchoutSessionStatus::Expired]);

    Livewire::test(PreviewTokenGenerator::class)
        ->assertCanNotSeeTableRecords([$session]);
});

it('revokes a preview session by marking it expired', function () {
    actingAsAdmin();
    $credential = createTestPunchoutCredential('ALD');
    $session = app(SessionManagerInterface::class)->startPreview($credential, 'Revoke me');

    Livewire::test(PreviewTokenGenerator::class)
        ->callTableAction('revoke', $session);

    expect($session->fresh()->status)->toBe(PunchoutSessionStatus::Expired);
});

it('lets an admin actually browse the storefront through a generated preview link', function () {
    actingAsAdmin();
    $credential = createTestPunchoutCredential('ALD');
    createTestProduct(['sku' => 'CW-4021']);
    $session = app(SessionManagerInterface::class)->startPreview($credential, 'End to end');

    $this->get(route('punchout.start', ['token' => $session->token]))
        ->assertRedirect(route('storefront.catalog', ['token' => $session->token]));

    $this->get(route('storefront.catalog', ['token' => $session->token]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Catalog/Index'));
});
