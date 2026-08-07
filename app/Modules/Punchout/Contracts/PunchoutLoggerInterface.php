<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Contracts;

use App\Modules\Punchout\Enums\PunchoutMessageType;
use App\Modules\Punchout\Models\PunchoutLog;
use App\Modules\Punchout\Models\PunchoutSession;

/**
 * "Log everything on the wire" applies to the outbound
 * PunchOutOrderMessage too, and that message is built from Storefront's
 * TransferPageController, outside this module. This is the seam: any
 * module may log a payload it is responsible for sending or receiving,
 * without depending on the concrete PunchoutLogger class.
 *
 * logInbound() returns the row it created so a caller that needs to log
 * the raw payload immediately (before parsing is even attempted, so a
 * parser crash can never lose the evidence) and only learns the real
 * outcome afterward can update that same row via updateStatus() rather
 * than inserting a second one for the same request.
 */
interface PunchoutLoggerInterface
{
    public function logInbound(
        PunchoutMessageType $type,
        string $rawPayload,
        ?PunchoutSession $session = null,
        ?int $httpStatus = null,
        ?string $error = null,
    ): PunchoutLog;

    public function logOutbound(
        PunchoutMessageType $type,
        string $rawPayload,
        ?PunchoutSession $session = null,
        ?int $httpStatus = null,
    ): PunchoutLog;

    public function updateStatus(PunchoutLog $log, ?int $httpStatus, ?string $error = null, ?PunchoutSession $session = null): void;

    /**
     * The most recent logged message of the given type/direction for a
     * session. What a retried transfer (see PunchoutSessionStatus::
     * Transferring) re-renders instead of building and sending a second,
     * distinct PunchOutOrderMessage for the same cart.
     */
    public function findLatestOutbound(PunchoutSession $session, PunchoutMessageType $type): ?PunchoutLog;
}
