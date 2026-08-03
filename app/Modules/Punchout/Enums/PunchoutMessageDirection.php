<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Enums;

enum PunchoutMessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
