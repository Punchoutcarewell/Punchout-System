<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Purchase order notification recipient
    |--------------------------------------------------------------------------
    |
    | Where the "a PO was received" email goes. The real Carewell operations
    | address has not been confirmed, this default is a placeholder, set
    | ORDERS_NOTIFICATION_EMAIL before this matters in any real environment.
    |
    */

    'notification_email' => env('ORDERS_NOTIFICATION_EMAIL', 'operations@carewellgroup.com.au'),

];
