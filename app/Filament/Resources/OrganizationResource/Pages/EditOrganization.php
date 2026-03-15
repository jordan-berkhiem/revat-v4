<?php

namespace App\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Resources\OrganizationResource;
use Filament\Resources\Pages\EditRecord;

class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function afterSave(): void
    {
        $record = $this->record;
        $supportAccessEnabled = $this->data['support_access_enabled'] ?? false;

        if ($record->support_access_enabled !== $supportAccessEnabled) {
            $record->toggleSupportAccess($supportAccessEnabled);
        }
    }
}
