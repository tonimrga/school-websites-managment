<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Activity;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['school', 'causer', 'subject']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Vrijeme')
                    ->dateTime(),
                TextColumn::make('school.name')
                    ->label('Dom')
                    ->placeholder('—'),
                TextColumn::make('causer.name')
                    ->label('Korisnik')
                    ->placeholder('Sustav'),
                TextColumn::make('event')
                    ->label('Radnja')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::eventLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        'created', 'published' => 'success',
                        'deleted' => 'danger',
                        'unpublished' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('subject_type')
                    ->label('Sadržaj')
                    ->formatStateUsing(fn (Activity $record): string => static::subjectLabel($record))
                    ->wrap(),
                TextColumn::make('changed_fields')
                    ->label('Promijenjeno')
                    ->state(fn (Activity $record): string => implode(', ', array_keys(static::changes($record, 'attributes'))))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('school')
                    ->label('Dom')
                    ->relationship('school', 'name'),
                SelectFilter::make('causer')
                    ->label('Korisnik')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', $data['value'])),
                SelectFilter::make('event')
                    ->label('Radnja')
                    ->options(ActivityResource::EVENT_LABELS),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function eventLabel(?string $event): string
    {
        return ActivityResource::EVENT_LABELS[$event] ?? (string) $event;
    }

    public static function subjectLabel(Activity $activity): string
    {
        $type = ActivityResource::SUBJECT_LABELS[$activity->subject_type] ?? class_basename((string) $activity->subject_type);
        // Schools and categories have a name instead of a title.
        $title = $activity->subject?->getAttribute('title')
            ?? $activity->subject?->getAttribute('name')
            ?? static::changes($activity, 'attributes')['title']
            ?? static::changes($activity, 'old')['title']
            ?? "#{$activity->subject_id}";

        return "{$type}: {$title}";
    }

    /**
     * Logged values as display strings.
     *
     * @param  'attributes'|'old'  $key
     * @return array<string, string>
     */
    public static function changes(Activity $activity, string $key): array
    {
        return collect($activity->properties->get($key, []))
            ->map(fn (mixed $value): string => match (true) {
                is_bool($value) => $value ? 'da' : 'ne',
                $value === null => '—',
                is_scalar($value) => (string) $value,
                default => json_encode($value, JSON_UNESCAPED_UNICODE),
            })
            ->all();
    }
}
