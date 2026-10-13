<?php

namespace App\Filament\Resources\Schools\Tables;

use App\Models\School;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SchoolsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Naziv')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('city')
                    ->label('Mjesto')
                    ->sortable(),
                TextColumn::make('theme')
                    ->label('Tema')
                    ->badge(),
                TextColumn::make('users_count')
                    ->label('Korisnika')
                    ->counts('users'),
                IconColumn::make('is_active')
                    ->label('Aktivna')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Aktivna'),
            ])
            ->recordActions([
                EditAction::make(),
                static::deactivateAction(),
                static::activateAction(),
            ]);
    }

    public static function deactivateAction(): Action
    {
        return Action::make('deactivate')
            ->label('Deaktiviraj')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Javna stranica škole prikazivat će poruku da je privremeno nedostupna, a urednici škole neće se moći prijaviti.')
            ->visible(fn (School $record): bool => $record->is_active)
            ->authorize('update')
            ->action(function (School $record, Action $action): void {
                $record->update(['is_active' => false]);
                $action->successNotificationTitle('Škola je deaktivirana.')->success();
            });
    }

    public static function activateAction(): Action
    {
        return Action::make('activate')
            ->label('Aktiviraj')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (School $record): bool => ! $record->is_active)
            ->authorize('update')
            ->action(function (School $record, Action $action): void {
                $record->update(['is_active' => true]);
                $action->successNotificationTitle('Škola je aktivirana.')->success();
            });
    }
}
