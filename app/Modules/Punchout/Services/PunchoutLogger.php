<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Services;

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
final class PunchoutLogger
{
    public function logInbound(
        PunchoutMessageType $type,
        string $rawPayload,
        ?PunchoutSession $session = null,
        ?int $httpStatus = null,
        ?string $error = null,
    ): void {
        $this->write(PunchoutMessageDirection::Inbound, $type, $rawPayload, $session, $httpStatus, $error);
    }

    public function logOutbound(
        PunchoutMessageType $type,
        string $rawPayload,
        ?PunchoutSession $session = null,
        ?int $httpStatus = null,
    ): void {
        $this->write(PunchoutMessageDirection::Outbound, $type, $rawPayload, $session, $httpStatus);
    }

    private function write(
        PunchoutMessageDirection $direction,
        PunchoutMessageType $type,
        string $rawPayload,
        ?PunchoutSession $session,
        ?int $httpStatus,
        ?string $error = null,
    ): void {
        $redacted = $this->redactSharedSecret($rawPayload);

        PunchoutLog::query()->create([
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
    }

    private function redactSharedSecret(string $payload): string
    {
        return preg_replace(
            '/(<SharedSecret[^>]*>)([^<]*)(<\/SharedSecret>)/i',
            '$1[REDACTED]$3',
            $payload,
        ) ?? $payload;
    }
}
