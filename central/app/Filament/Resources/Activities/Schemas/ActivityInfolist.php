<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use App\Models\Activity;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('created_at')
                    ->label('Vrijeme')
                    ->dateTime(),
                TextEntry::make('school.name')
                    ->label('Dom')
                    ->placeholder('—'),
                TextEntry::make('causer.name')
                    ->label('Korisnik')
                    ->placeholder('Sustav'),
                TextEntry::make('event')
                    ->label('Radnja')
                    ->formatStateUsing(fn (?string $state): string => ActivitiesTable::eventLabel($state)),
                TextEntry::make('subject_type')
                    ->label('Sadržaj')
                    ->formatStateUsing(fn (Activity $record): string => ActivitiesTable::subjectLabel($record))
                    ->columnSpanFull(),
                KeyValueEntry::make('old_values')
                    ->label('Prije')
                    ->state(fn (Activity $record): array => ActivitiesTable::changes($record, 'old'))
                    ->keyLabel('Polje')
                    ->valueLabel('Vrijednost')
                    ->placeholder('—'),
                KeyValueEntry::make('new_values')
                    ->label('Poslije')
                    ->state(fn (Activity $record): array => ActivitiesTable::changes($record, 'attributes'))
                    ->keyLabel('Polje')
                    ->valueLabel('Vrijednost')
                    ->placeholder('—'),
            ]);
    }
}
