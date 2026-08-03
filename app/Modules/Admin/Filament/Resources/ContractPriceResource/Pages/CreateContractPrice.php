<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ContractPriceResource\Pages;

use App\Modules\Admin\Filament\Resources\ContractPriceResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateContractPrice extends CreateRecord
{
    protected static string $resource = ContractPriceResource::class;
}
