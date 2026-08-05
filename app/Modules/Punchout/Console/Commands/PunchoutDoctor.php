<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Console\Commands;

use Illuminate\Console\Command;

/**
 * Deployment sanity checks for configuration that is silently wrong
 * rather than loudly broken: nothing about a misconfigured
 * frame-ancestors value causes /punchout/setup or /punchout/order to
 * fail, Coupa's server-to-server calls work fine either way. It is only
 * the buyer's browser refusing to render the storefront inside Coupa's
 * iframe, visible as a CSP violation in the browser console, not
 * something that surfaces in any server log. Intended to run as a
 * deploy-pipeline step (`php artisan punchout:doctor || exit 1`), not
 * something anything else in the app calls.
 */
final class PunchoutDoctor extends Command
{
    protected $signature = 'punchout:doctor';

    protected $description = 'Check for punchout configuration that is silently wrong rather than causing a visible failure.';

    public function handle(): int
    {
        $failures = [];

        if (! app()->environment('local', 'testing') && array_filter((array) config('punchout.coupa_frame_ancestors', [])) === []) {
            $failures[] = 'PUNCHOUT_COUPA_FRAME_ANCESTORS is empty. FrameAncestors defaults to "frame-ancestors \'none\'" '
                .'when this is unset, which silently blocks the storefront from ever rendering inside Coupa\'s iframe, '
                .'a CSP violation only visible in the buyer\'s browser console, never in a server log.';
        }

        if ($failures === []) {
            $this->components->info('punchout:doctor found no configuration problems.');

            return self::SUCCESS;
        }

        $this->components->error(count($failures).' punchout configuration problem(s) found:');

        foreach ($failures as $failure) {
            $this->components->twoColumnDetail($failure, '');
        }

        return self::FAILURE;
    }
}
