<?php

declare(strict_types=1);

namespace App\Modules\Punchout\Enums;

/**
 * Every distinct cXML payload shape this module speaks, in either
 * direction. Recorded on every punchout_logs row so a wire dump can be
 * filtered to, for example, "every OrderRequest received in the last day."
 */
enum PunchoutMessageType: string
{
    case SetupRequest = 'setup_request';
    case SetupResponse = 'setup_response';
    case OrderMessage = 'order_message';
    case OrderRequest = 'order_request';
    case OrderResponse = 'order_response';
}
