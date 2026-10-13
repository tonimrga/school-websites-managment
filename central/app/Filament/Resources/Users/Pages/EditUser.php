<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Tables\UsersTable;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            UsersTable::sendPasswordResetAction(),
            UsersTable::deactivateAction(),
            UsersTable::activateAction(),
        ];
    }

    /**
     * @param  User  $record
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (filled($data['password'] ?? null)) {
            $record->password = $data['password'];
        }

        if ($record->isEditor() && isset($data['school_id'])) {
            $record->school_id = (int) $data['school_id'];
        }

        $record->save();

        return $record;
    }
}
