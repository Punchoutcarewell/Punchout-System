<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

enum PurchaseOrderStatus: string
{
    case Received = 'received';
    case Acknowledged = 'acknowledged';
    case Failed = 'failed';
}
