<?php

declare(strict_types=1);

use App\Shared\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

it('falls back to text branding when no logo is configured', function () {
    actingAsAdmin();

    $this->get('/admin')->assertOk()->assertSee('Carewell PunchOut');
});

it('renders the configured logo as an image in the admin panel', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/logo.png', 'logo-content');
    SiteSetting::current()->update(['logo_path' => 'branding/logo.png']);
    actingAsAdmin();

    $this->get('/admin')->assertOk()->assertSee(Storage::disk('public')->url('branding/logo.png'), false);
});
