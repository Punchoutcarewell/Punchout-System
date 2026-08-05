<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Services;

use App\Modules\Punchout\Contracts\PunchoutLoggerInterface;
use App\Modules\Punchout\Cxml\CxmlSecretRedactor;
use App\Modules\Punchout\Enums\PunchoutMessageDirection;
use App\Modules\Punchout\Enums\PunchoutMessageType;
use App\Modules\Punchout\Models\PunchoutLog;
use App\Modules\Punchout\Models\PunchoutSession;
use Illuminate\Support\Facades\Log;

/**
 * Writes every cXML payload, in either direction, to both the
 * punchout_logs table and the "punchout" log channel, so a database
 * outage never takes out the wire-level audit trail. The shared secret is
 * redacted before either write, not after: it must never reach storage in
 * plain text even transiently.
 *
 * This lives in the Punchout module rather than Shared, even though it is
 * named generically: it depends on the PunchoutLog model, which this
 * module owns, and Shared must never depend on another module's models.
 */
final class PunchoutLogger implements PunchoutLoggerInterface
{
    public function __construct(private readonly CxmlSecretRedactor $redactor) {}

    public function logInbound(
        PunchoutMessageType $type,
        string $rawPayload,
        ?PunchoutSession $session = null,
        ?int $httpStatus = null,
        ?string $error = null,
    ): PunchoutLog {
        return $this->write(PunchoutMessageDirection::Inbound, $type, $rawPayload, $session, $httpStatus, $error);
    }

    public function logOutbound(
        PunchoutMessageType $type,
        string $rawPayload,
        ?PunchoutSession $session = null,
        ?int $httpStatus = null,
    ): PunchoutLog {
        return $this->write(PunchoutMessageDirection::Outbound, $type, $rawPayload, $session, $httpStatus);
    }

    /**
     * Updates the row an earlier logInbound()/logOutbound() call created,
     * once the real outcome is known, instead of writing a second row for
     * the same request. The raw_payload was already redacted and does not
     * need touching again.
     */
    public function updateStatus(PunchoutLog $log, ?int $httpStatus, ?string $error = null, ?PunchoutSession $session = null): void
    {
        $log->update([
            'http_status' => $httpStatus,
            'error' => $error,
            'session_id' => $session !== null ? $session->id : $log->session_id,
        ]);

        Log::channel('punchout')->info("{$log->direction->value}:{$log->message_type->value}", [
            'session_id' => $log->session_id,
            'http_status' => $httpStatus,
            'error' => $error,
        ]);
    }

    private function write(
        PunchoutMessageDirection $direction,
        PunchoutMessageType $type,
        string $rawPayload,
        ?PunchoutSession $session,
        ?int $httpStatus,
        ?string $error = null,
    ): PunchoutLog {
        $redacted = $this->redactor->redact($rawPayload);

        $log = PunchoutLog::query()->create([
            'session_id' => $session?->id,
            'direction' => $direction,
            'message_type' => $type,
            'http_status' => $httpStatus,
            'raw_payload' => $redacted,
            'error' => $error,
            'created_at' => now(),
        ]);

        Log::channel('punchout')->info("{$direction->value}:{$type->value}", [
            'session_id' => $session?->id,
            'http_status' => $httpStatus,
            'error' => $error,
        ]);

        return $log;
    }
}
