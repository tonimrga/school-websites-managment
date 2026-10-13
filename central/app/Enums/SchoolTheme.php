<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SchoolTheme: string implements HasLabel
{
    case Blue = 'blue';
    case Green = 'green';
    case Red = 'red';

    public function getLabel(): string
    {
        return match ($this) {
            self::Blue => 'Plava',
            self::Green => 'Zelena',
            self::Red => 'Crvena',
        };
    }
}
