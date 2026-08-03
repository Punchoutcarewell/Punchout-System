<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Contracts;

use App\Modules\Punchout\Data\SetupRequestData;
use App\Modules\Punchout\Models\PunchoutSession;

/**
 * The only class permitted to create, resolve, or transition a
 * PunchoutSession. Every other module that needs the current buyer's
 * context (name, email, business unit) depends on this interface's
 * current() method, never on the PunchoutSession model directly.
 */
interface SessionManagerInterface
{
    /**
     * Create a new session from a validated, authenticated setup request.
     */
    public function start(SetupRequestData $data): PunchoutSession;

    /**
     * Look up a session by its token. Returns null if the token is
     * unknown, expired, or already transferred, expiry is enforced here,
     * not left to the caller to check.
     */
    public function resolve(string $token): ?PunchoutSession;

    /**
     * Bind a session as the one active for the current request, so that
     * current() can return it later in the same request lifecycle without
     * a second lookup.
     */
    public function bind(PunchoutSession $session): void;

    /**
     * The session bound to the current request, if any.
     */
    public function current(): ?PunchoutSession;

    public function markTransferred(PunchoutSession $session): void;
}
