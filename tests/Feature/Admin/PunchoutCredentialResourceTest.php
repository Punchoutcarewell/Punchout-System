<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Resources\PunchoutCredentialResource\Pages\CreatePunchoutCredential;
use App\Modules\Admin\Filament\Resources\PunchoutCredentialResource\Pages\EditPunchoutCredential;
use App\Modules\Admin\Filament\Resources\PunchoutCredentialResource\Pages\ListPunchoutCredentials;
use App\Modules\Punchout\Enums\PunchoutEnvironment;
use App\Modules\Punchout\Models\PunchoutCredential;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('creates a credential with the secret encrypted at rest', function () {
    actingAsAdmin();

    Livewire::test(CreatePunchoutCredential::class)
        ->fillForm([
            'environment' => 'test',
            'from_domain' => 'DUNS',
            'from_identity' => 'COUPA1',
            'to_domain' => 'DUNS',
            'to_identity' => '079928354',
            'sender_domain' => 'DUNS',
            'sender_identity' => 'COUPA1',
            'shared_secret' => 'TopSecret123',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $credential = PunchoutCredential::query()->where('to_identity', '079928354')->firstOrFail();

    expect($credential->shared_secret)->toBe('TopSecret123')
        ->and($credential->protocol)->toBe('cxml');

    $rawValue = DB::table('punchout_credentials')->where('id', $credential->id)->value('shared_secret');
    expect($rawValue)->not->toBe('TopSecret123');
});

it('requires a secret on create', function () {
    actingAsAdmin();

    Livewire::test(CreatePunchoutCredential::class)
        ->fillForm([
            'environment' => 'test',
            'from_domain' => 'DUNS',
            'from_identity' => 'COUPA1',
            'to_domain' => 'DUNS',
            'to_identity' => '079928354',
            'sender_domain' => 'DUNS',
            'sender_identity' => 'COUPA1',
        ])
        ->call('create')
        ->assertHasFormErrors(['shared_secret']);
});

it('never pre-fills the secret field when editing', function () {
    actingAsAdmin();

    $credential = PunchoutCredential::query()->create([
        'environment' => PunchoutEnvironment::Test,
        'from_domain' => 'DUNS',
        'from_identity' => 'COUPA1',
        'to_domain' => 'DUNS',
        'to_identity' => '079928354',
        'shared_secret' => 'TopSecret123',
        'sender_domain' => 'DUNS',
        'sender_identity' => 'COUPA1',
        'protocol' => 'cxml',
        'is_active' => true,
    ]);

    Livewire::test(EditPunchoutCredential::class, ['record' => $credential->getRouteKey()])
        ->assertFormSet(['shared_secret' => null]);
});

it('leaves the secret unchanged when the field is left blank on save', function () {
    actingAsAdmin();

    $credential = PunchoutCredential::query()->create([
        'environment' => PunchoutEnvironment::Test,
        'from_domain' => 'DUNS',
        'from_identity' => 'COUPA1',
        'to_domain' => 'DUNS',
        'to_identity' => '079928354',
        'shared_secret' => 'TopSecret123',
        'sender_domain' => 'DUNS',
        'sender_identity' => 'COUPA1',
        'protocol' => 'cxml',
        'is_active' => true,
    ]);

    Livewire::test(EditPunchoutCredential::class, ['record' => $credential->getRouteKey()])
        ->fillForm(['to_domain' => 'DUNS-UPDATED'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($credential->refresh()->shared_secret)->toBe('TopSecret123')
        ->and($credential->to_domain)->toBe('DUNS-UPDATED');
});

it('changes the secret when a new value is typed on save', function () {
    actingAsAdmin();

    $credential = PunchoutCredential::query()->create([
        'environment' => PunchoutEnvironment::Test,
        'from_domain' => 'DUNS',
        'from_identity' => 'COUPA1',
        'to_domain' => 'DUNS',
        'to_identity' => '079928354',
        'shared_secret' => 'TopSecret123',
        'sender_domain' => 'DUNS',
        'sender_identity' => 'COUPA1',
        'protocol' => 'cxml',
        'is_active' => true,
    ]);

    Livewire::test(EditPunchoutCredential::class, ['record' => $credential->getRouteKey()])
        ->fillForm(['shared_secret' => 'BrandNewSecret456'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($credential->refresh()->shared_secret)->toBe('BrandNewSecret456');
});

it('never shows the shared secret in the table listing', function () {
    actingAsAdmin();

    PunchoutCredential::query()->create([
        'environment' => PunchoutEnvironment::Test,
        'from_domain' => 'DUNS',
        'from_identity' => 'COUPA1',
        'to_domain' => 'DUNS',
        'to_identity' => '079928354',
        'shared_secret' => 'TopSecret123',
        'sender_domain' => 'DUNS',
        'sender_identity' => 'COUPA1',
        'protocol' => 'cxml',
        'is_active' => true,
    ]);

    Livewire::test(ListPunchoutCredentials::class)
        ->assertDontSee('TopSecret123');
});
