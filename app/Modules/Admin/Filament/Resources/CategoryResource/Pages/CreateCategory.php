<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\CategoryResource\Pages;

use App\Modules\Admin\Filament\Resources\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;
}
