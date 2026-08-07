<?php

declare(strict_types=1);

it('fails when PUNCHOUT_COUPA_FRAME_ANCESTORS is empty in a non-local, non-testing environment', function () {
    config(['punchout.coupa_frame_ancestors' => []]);
    app()->instance('env', 'production');

    try {
        $this->artisan('punchout:doctor')->assertExitCode(1);
    } finally {
        app()->instance('env', 'testing');
    }
});

it('passes when PUNCHOUT_COUPA_FRAME_ANCESTORS is set in a non-local environment', function () {
    config(['punchout.coupa_frame_ancestors' => ['https://carewell.coupahost.com']]);
    app()->instance('env', 'production');

    try {
        $this->artisan('punchout:doctor')->assertExitCode(0);
    } finally {
        app()->instance('env', 'testing');
    }
});

it('does not fail on an empty frame-ancestors list in local, since that is the expected pre-GPCS-confirmation state', function () {
    config(['punchout.coupa_frame_ancestors' => []]);
    app()->instance('env', 'local');

    try {
        $this->artisan('punchout:doctor')->assertExitCode(0);
    } finally {
        app()->instance('env', 'testing');
    }
});
