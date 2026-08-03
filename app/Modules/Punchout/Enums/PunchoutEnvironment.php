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
}
