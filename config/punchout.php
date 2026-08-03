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

];
