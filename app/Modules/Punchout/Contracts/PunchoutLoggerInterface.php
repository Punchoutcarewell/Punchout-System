<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Contracts;

use App\Modules\Punchout\Enums\PunchoutMessageType;
use App\Modules\Punchout\Models\PunchoutSession;

/**
 * "Log everything on the wire" applies to the outbound
 * PunchOutOrderMessage too, and that message is built from Storefront's
 * TransferPageController, outside this module. This is the seam: any
 * module may log a payload it is responsible for sending or receiving,
 * without depending on the concrete PunchoutLogger class.
 */
interface PunchoutLoggerInterface
{
    public function logInbound(
        PunchoutMessageType $type,
        string $rawPayload,
        ?PunchoutSession $session = null,
        ?int $httpStatus = null,
        ?string $error = null,
    ): void;

    public function logOutbound(
        PunchoutMessageType $type,
        string $rawPayload,
        ?PunchoutSession $session = null,
        ?int $httpStatus = null,
    ): void;
}
