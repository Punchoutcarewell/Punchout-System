<?php

declare(strict_types=1);

use App\Modules\Punchout\Models\PunchoutCredential;
use Illuminate\Support\Facades\Http;

it('refuses to run outside local/testing, since it plants an active credential with a known secret', function () {
    app()->instance('env', 'production');

    try {
        $this->artisan('punchout:simulate')->assertExitCode(1);
    } finally {
        app()->instance('env', 'testing');
    }

    expect(PunchoutCredential::query()->where('to_identity', 'CAREWELL-SIM')->exists())->toBeFalse();
});

it('generates a different shared secret on every run rather than a hardcoded constant', function () {
    // Faked rather than hitting a real running server: the command makes
    // genuine HTTP calls to config('app.url'), which is the right design
    // for what it is meant to do (exercise the real route pipeline), but
    // makes it unsuitable to depend on in a fast, deterministic test.
    Http::fake(['*' => Http::response('not a real cxml response', 500)]);

    $this->artisan('punchout:simulate');
    $firstSecret = PunchoutCredential::query()->where('to_identity', 'CAREWELL-SIM')->firstOrFail()->shared_secret;

    $this->artisan('punchout:simulate');
    $secondSecret = PunchoutCredential::query()->where('to_identity', 'CAREWELL-SIM')->firstOrFail()->shared_secret;

    expect($firstSecret)->not->toBe($secondSecret)
        ->and($firstSecret)->not->toBe('simulator-shared-secret');
});
