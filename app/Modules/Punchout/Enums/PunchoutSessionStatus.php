<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Enums;

/**
 * The states a PunchoutSession can be in. Active -> Transferring ->
 * Transferred is the happy path; Active -> Expired and Transferring ->
 * Transferred (after its grace window lapses, see PUNCHOUT_TRANSFER_GRACE_MINUTES)
 * are the others. All terminal except Active and Transferring, a session
 * never moves backwards.
 *
 * Transferring exists because the browser's form post to Coupa's
 * browserFormPostUrl is fire-and-forget: cXML's PunchOut protocol has no
 * callback confirming Coupa actually received it. Marking a session
 * Transferred the instant the page renders, before the browser has even
 * attempted the post, left no way back from a network blip or a blocked
 * script, the session was already unresolvable by the time the failure
 * happened.
 */
enum PunchoutSessionStatus: string
{
    case Active = 'active';
    case Transferring = 'transferring';
    case Transferred = 'transferred';
    case Expired = 'expired';
}
