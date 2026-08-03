<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\PunchoutCredentialResource\Pages;

use App\Modules\Admin\Filament\Resources\PunchoutCredentialResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditPunchoutCredential extends EditRecord
{
    protected static string $resource = PunchoutCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * The shared secret is never pre-filled on edit, the form field
     * always starts blank. Without this, the decrypted secret would be
     * rendered into the page's HTML on every visit to the edit screen,
     * exactly what "write-only" is supposed to prevent.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['shared_secret']);

        return $data;
    }
}
