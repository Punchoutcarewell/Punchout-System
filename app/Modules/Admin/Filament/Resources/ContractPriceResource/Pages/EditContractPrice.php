<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ContractPriceResource\Pages;

use App\Modules\Admin\Filament\Resources\ContractPriceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditContractPrice extends EditRecord
{
    protected static string $resource = ContractPriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
