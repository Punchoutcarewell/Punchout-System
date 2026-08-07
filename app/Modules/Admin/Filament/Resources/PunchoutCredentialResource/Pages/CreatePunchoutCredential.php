<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\PunchoutCredentialResource\Pages;

use App\Modules\Admin\Filament\Resources\PunchoutCredentialResource;
use Filament\Resources\Pages\CreateRecord;

final class CreatePunchoutCredential extends CreateRecord
{
    protected static string $resource = PunchoutCredentialResource::class;
}
