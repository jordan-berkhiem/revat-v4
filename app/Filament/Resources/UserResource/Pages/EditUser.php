<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\AuditService;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function afterSave(): void
    {
        $user = $this->record;
        $isDeactivated = $this->data['is_deactivated'] ?? false;
        $wasDeactivated = $user->isDeactivated();

        if ($isDeactivated && ! $wasDeactivated) {
            $user->deactivate();
            AuditService::log(
                action: 'user.deactivated',
                resourceType: 'user',
                resourceId: $user->id,
            );
        } elseif (! $isDeactivated && $wasDeactivated) {
            $user->reactivate();
            AuditService::log(
                action: 'user.reactivated',
                resourceType: 'user',
                resourceId: $user->id,
            );
        }
    }
}
