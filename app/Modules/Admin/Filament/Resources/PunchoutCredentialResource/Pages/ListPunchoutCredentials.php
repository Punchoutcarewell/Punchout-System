<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\PunchoutCredentialResource\Pages;

use App\Modules\Admin\Filament\Resources\PunchoutCredentialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPunchoutCredentials extends ListRecords
{
    protected static string $resource = PunchoutCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
