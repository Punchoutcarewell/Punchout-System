<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ContractPriceResource\Pages;

use App\Modules\Admin\Filament\Resources\ContractPriceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListContractPrices extends ListRecords
{
    protected static string $resource = ContractPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
