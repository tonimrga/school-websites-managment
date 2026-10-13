<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case Editor = 'editor';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrator',
            self::Editor => 'Urednik',
        };
    }
}
