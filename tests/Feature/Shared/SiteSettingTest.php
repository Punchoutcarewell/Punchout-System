<?php

declare(strict_types=1);

use App\Shared\Models\SiteSetting;

it('creates the single settings row on first access', function () {
    expect(SiteSetting::query()->count())->toBe(0);

    $setting = SiteSetting::current();

    expect($setting->id)->toBe(1)
        ->and(SiteSetting::query()->count())->toBe(1);
});

it('returns the same row on every subsequent call', function () {
    $first = SiteSetting::current();
    $first->update(['logo_path' => 'branding/logo.png']);

    $second = SiteSetting::current();

    expect($second->id)->toBe($first->id)
        ->and($second->logo_path)->toBe('branding/logo.png')
        ->and(SiteSetting::query()->count())->toBe(1);
});
