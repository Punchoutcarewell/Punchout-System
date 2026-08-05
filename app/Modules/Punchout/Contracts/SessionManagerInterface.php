<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Contracts;

use App\Modules\Punchout\Data\OutboundIdentity;
use App\Modules\Punchout\Data\SetupRequestData;
use App\Modules\Punchout\Exceptions\InvalidCredentialsException;
use App\Modules\Punchout\Models\PunchoutCredential;
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
     * Create a session for an admin to preview the storefront as a buyer
     * would see it, without a real Coupa round trip. Identity fields are
     * copied from $credential so the session is fully usable, including
     * resolveOutboundIdentity() if the admin clicks all the way through to
     * "Transfer cart to Coupa". Always is_preview: true, see
     * PunchoutSession::$is_preview.
     */
    public function startPreview(PunchoutCredential $credential, string $label): PunchoutSession;

    /**
     * Look up a session by its token. Returns null if the token is
     * unknown, expired, or already transferred, expiry is enforced here,
     * not left to the caller to check. A session in the Transferring
     * state (see PunchoutSessionStatus) still resolves within its grace
     * window, so a reload or a retry of the transfer page is not treated
     * as a dead session.
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

    /**
     * The buyer has clicked "Transfer cart to Coupa" and the outbound
     * PunchOutOrderMessage has been built and logged; the browser is
     * about to auto-submit it to Coupa. Not yet Transferred: this app has
     * no way to know that post actually landed, so the session stays
     * resolvable for a grace window in case it needs retrying.
     */
    public function markTransferring(PunchoutSession $session): void;

    public function markTransferred(PunchoutSession $session): void;

    /**
     * The Header identity fields for the outbound PunchOutOrderMessage,
     * reversed from what the session captured at setup time. This is the
     * one way anything outside this module gets from "a session" to
     * "what goes in the From/To/Sender of an outbound message": the
     * caller never touches PunchoutCredential directly.
     *
     * @throws InvalidCredentialsException no active credential matches this session's identity
     */
    public function resolveOutboundIdentity(PunchoutSession $session): OutboundIdentity;
}
