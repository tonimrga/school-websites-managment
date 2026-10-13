<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Enums\ArticleStatus;
use App\Exceptions\ArticleNotPublishable;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Image;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Naslov')
                    ->searchable()
                    ->limit(80)
                    ->description(fn (Article $record): ?string => $record->trashed() ? 'U smeću' : null),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (Article $record): string => $record->statusLabel())
                    ->color(fn (Article $record): string => $record->statusColor()),
                IconColumn::make('is_featured')
                    ->label('Istaknuto')
                    ->boolean()
                    ->trueIcon(Heroicon::Star)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('warning')
                    ->falseColor('gray'),
                TextColumn::make('published_at')
                    ->label('Datum objave')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Skica',
                        'published' => 'Objavljeno',
                        'scheduled' => 'Zakazano',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'draft' => $query->where('status', ArticleStatus::Draft),
                        'published' => $query->where('status', ArticleStatus::Published)->where('published_at', '<=', now()),
                        'scheduled' => $query->where('status', ArticleStatus::Published)->where('published_at', '>', now()),
                        default => $query,
                    }),
                TernaryFilter::make('is_featured')
                    ->label('Istaknuto'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                static::previewAction(),
                static::publishAction(),
                static::unpublishAction(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Pregled')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->authorize('view')
            ->url(fn (Article $record): string => ArticleResource::getUrl('preview', ['record' => $record]));
    }

    public static function publishAction(): Action
    {
        return Action::make('publish')
            ->label('Objavi')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Objaviti vijest?')
            ->modalDescription('Vijest će biti vidljiva na javnoj stranici doma od datuma objave.')
            ->visible(fn (Article $record): bool => ! $record->isPublished() && ! $record->trashed())
            ->authorize('update')
            ->action(fn (Article $record, Action $action) => static::publish($record, $action));
    }

    public static function unpublishAction(): Action
    {
        return Action::make('unpublish')
            ->label('Povuci objavu')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Povući objavu?')
            ->modalDescription('Vijest više neće biti vidljiva na javnoj stranici i vratit će se u skice.')
            ->visible(fn (Article $record): bool => $record->isPublished() && ! $record->trashed())
            ->authorize('update')
            ->action(function (Article $record, Action $action): void {
                $record->unpublish();
                $action->successNotificationTitle('Objava je povučena.')->success();
            });
    }

    /**
     * Publish and report the outcome; a missing title, body or cover is
     * shown to the editor instead of failing.
     */
    public static function publish(Article $record, Action $action): void
    {
        try {
            $record->publish();
        } catch (ArticleNotPublishable $exception) {
            $action->failureNotificationTitle($exception->getMessage())->failure();

            return;
        }

        $action->successNotificationTitle($record->isScheduled()
            ? 'Objava je zakazana za '.$record->published_at->format('d. m. Y. \u H:i').'.'
            : 'Vijest je objavljena.')->success();
    }

    /**
     * Edit the optional caption of each gallery image. Only images of this
     * article's own gallery are ever touched.
     */
    public static function captionsAction(): Action
    {
        return Action::make('captions')
            ->label('Opisi fotografija')
            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
            ->color('gray')
            ->modalHeading('Opisi fotografija u galeriji')
            ->modalDescription('Opis se prikazuje ispod fotografije i služi kao zamjenski tekst za slabovidne.')
            ->modalSubmitActionLabel('Spremi opise')
            ->visible(fn (Article $record): bool => ! $record->trashed() && $record->getMedia('gallery')->isNotEmpty())
            ->authorize('update')
            ->fillForm(fn (Article $record): array => [
                'captions' => $record->getMedia('gallery')
                    ->mapWithKeys(fn (Media $media): array => [$media->getKey() => $media->getCustomProperty('caption')])
                    ->all(),
            ])
            ->schema(fn (Article $record): array => $record->getMedia('gallery')
                ->map(fn (Media $media, int $index): Grid => Grid::make(4)->schema([
                    Image::make($media->getUrl('thumb'), 'Fotografija '.($index + 1))
                        ->imageHeight(72),
                    TextInput::make("captions.{$media->getKey()}")
                        ->label('Opis fotografije '.($index + 1))
                        ->maxLength(255)
                        ->columnSpan(3),
                ]))
                ->all())
            ->action(function (Article $record, array $data, Action $action): void {
                $captions = $data['captions'] ?? [];

                foreach ($record->getMedia('gallery') as $media) {
                    if (! array_key_exists($media->getKey(), $captions)) {
                        continue;
                    }

                    $caption = trim((string) $captions[$media->getKey()]);

                    $caption === ''
                        ? $media->forgetCustomProperty('caption')
                        : $media->setCustomProperty('caption', $caption);

                    $media->save();
                }

                $action->successNotificationTitle('Opisi su spremljeni.')->success();
            });
    }
}
