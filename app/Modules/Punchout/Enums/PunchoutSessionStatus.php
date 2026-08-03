<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Enums;

/**
 * The three states a PunchoutSession can be in. All terminal except
 * Active. A session never moves backwards.
 */
enum PunchoutSessionStatus: string
{
    case Active = 'active';
    case Transferred = 'transferred';
    case Expired = 'expired';
}
