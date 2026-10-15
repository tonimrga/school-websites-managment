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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class SchoolsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withStorageBytes())
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
                TextColumn::make('storage_bytes')
                    ->label('Prostor')
                    ->formatStateUsing(fn (int|string|null $state): string => Number::fileSize((int) $state, maxPrecision: 1))
                    ->tooltip('Fotografije, logo i dokumenti, uključujući one u smeću; bez umanjenih verzija fotografija.')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('storage_bytes', $direction)),
                IconColumn::make('is_active')
                    ->label('Aktivan')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Aktivan'),
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
            ->modalDescription('Javna stranica doma prikazivat će poruku da je privremeno nedostupna, a urednici doma neće se moći prijaviti.')
            ->visible(fn (School $record): bool => $record->is_active)
            ->authorize('update')
            ->action(function (School $record, Action $action): void {
                $record->update(['is_active' => false]);
                $action->successNotificationTitle('Dom je deaktiviran.')->success();
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
                $action->successNotificationTitle('Dom je aktiviran.')->success();
            });
    }
}
