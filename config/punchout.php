<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Session TTL
    |--------------------------------------------------------------------------
    |
    | How long a PunchoutSession stays active after PunchOutSetupRequest is
    | accepted. Coupa's expected session timeout is one of the open
    | questions for GPCS (see the roadmap's blocking questions list); this
    | default is a conservative placeholder until that is confirmed.
    |
    */

    'session_ttl_minutes' => env('PUNCHOUT_SESSION_TTL_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Transfer grace window
    |--------------------------------------------------------------------------
    |
    | How long a session stays resolvable after the buyer clicks "Transfer
    | cart to Coupa" but before this app has any confirmation the browser's
    | form post to Coupa actually landed, there is no such confirmation in
    | the cXML PunchOut protocol. Within this window a reload or a retry
    | re-renders the same already-built PunchOutOrderMessage rather than
    | building and sending a second, distinct one. See
    | PunchoutSessionStatus::Transferring.
    |
    */

    'transfer_grace_minutes' => env('PUNCHOUT_TRANSFER_GRACE_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Coupa frame-ancestors domains
    |--------------------------------------------------------------------------
    |
    | The exact Coupa test and production domains are still an open
    | question for GPCS. Left empty, FrameAncestors defaults to denying all
    | framing, a secure default rather than leaving embedding wide open.
    | Once confirmed, set PUNCHOUT_COUPA_FRAME_ANCESTORS to a
    | space-separated list, e.g. "https://carewell.coupahost.com".
    |
    */

    'coupa_frame_ancestors' => array_filter(explode(' ', (string) env('PUNCHOUT_COUPA_FRAME_ANCESTORS', ''))),

    /*
    |--------------------------------------------------------------------------
    | Log retention
    |--------------------------------------------------------------------------
    |
    | punchout_logs holds the raw cXML payload of every setup, order, and
    | response message, one row per direction per request, with no cap.
    | PunchoutLog::prunable() (run daily by model:prune, see
    | routes/console.php) removes rows older than this many days. There is
    | no compliance requirement driving this number yet, it is a
    | reasonable default until GPCS or Carewell specifies a real retention
    | policy for cXML audit logs.
    |
    */

    'log_retention_days' => env('PUNCHOUT_LOG_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Preview session TTL
    |--------------------------------------------------------------------------
    |
    | How long a session created by SessionManager::startPreview() (the
    | Admin panel's "generate a test token" tool) stays active. Shorter
    | than session_ttl_minutes by default: a preview link is meant to be
    | used right away, not held onto.
    |
    */

    'preview_ttl_minutes' => env('PUNCHOUT_PREVIEW_TTL_MINUTES', 30),

];
