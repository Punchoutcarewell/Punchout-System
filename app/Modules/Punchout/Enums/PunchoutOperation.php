<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Enums;

/**
 * The operation attribute cXML carries on PunchOutSetupRequest. Only
 * "create" is confirmed in scope for this project; "edit" and "inspect"
 * are open questions for GPCS (see the roadmap's blocking questions list).
 * Both are modeled here so parsing never fails on a value it should
 * simply record.
 */
enum PunchoutOperation: string
{
    case Create = 'create';
    case Edit = 'edit';
    case Inspect = 'inspect';
}
