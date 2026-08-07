<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\PurchaseOrderResource\Pages;

use App\Modules\Admin\Filament\Resources\PurchaseOrderResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
