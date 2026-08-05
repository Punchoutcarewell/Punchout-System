<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Enums;

/**
 * Test and production credentials are separate rows, separate secrets,
 * looked up by this value, never by branching in application code.
 */
enum PunchoutEnvironment: string
{
    case Test = 'test';
    case Production = 'production';

    /**
     * Which punchout environment a credential must belong to in order to
     * authenticate a request right now. Every credential lookup in this
     * module scopes on this, so a Test credential (including the one
     * punchout:simulate plants) can never authenticate a request against
     * a real production deployment, and vice versa. Laravel's own
     * app()->environment() has more values than this enum (local,
     * testing, staging, production); everything short of "production"
     * maps to Test, since staging exercises Coupa's test environment,
     * never its production one.
     */
    public static function current(): self
    {
        return app()->environment('production') ? self::Production : self::Test;
    }
}
