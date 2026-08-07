<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\PurchaseOrderResource\Pages;

use App\Modules\Admin\Filament\Resources\PurchaseOrderResource;
use Filament\Resources\Pages\ListRecords;

final class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
