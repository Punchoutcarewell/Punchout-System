<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\ProductResource\Pages;

use App\Modules\Admin\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
}
