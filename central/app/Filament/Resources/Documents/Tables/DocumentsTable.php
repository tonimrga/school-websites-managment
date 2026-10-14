<?php

namespace App\Filament\Resources\Documents\Tables;

use App\Models\Document;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/**
 * Documents are listed by category (in the categories' manual order), then
 * in their own manual order. Reordering happens on the ReorderDocuments page,
 * which has a separate drag-and-drop list per category; Filament's own table
 * reordering would mix all categories in one ungrouped list.
 */
class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => static::orderByCategory($query)->with(['category', 'media']))
            ->defaultGroup(
                Group::make('document_category_id')
                    ->label('Kategorija')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn (Document $record): string => $record->category->name)
                    ->orderQueryUsing(fn (Builder $query): Builder => static::orderByCategory($query)),
            )
            ->groupingSettingsHidden()
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label('Naslov')
                    ->searchable()
                    ->limit(80)
                    ->description(fn (Document $record): ?string => $record->trashed() ? 'U smeću' : null),
                TextColumn::make('file_info')
                    ->label('Datoteka')
                    ->state(function (Document $record): ?string {
                        $media = $record->file();

                        return $media === null ? null : strtoupper($media->extension).', '.Number::fileSize($media->size, precision: 1);
                    })
                    ->placeholder('—')
                    ->color('gray'),
                TextColumn::make('document_date')
                    ->label('Datum dokumenta')
                    ->date()
                    ->placeholder('—'),
                IconColumn::make('is_published')
                    ->label('Objavljeno')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('document_category_id')
                    ->label('Kategorija')
                    ->relationship('category', 'name', fn (Builder $query): Builder => $query->orderBy('sort_order')),
                TernaryFilter::make('is_published')
                    ->label('Objavljeno'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                static::downloadAction(),
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

    /**
     * Download through the download route, which also serves unpublished
     * documents to users who may view them.
     */
    public static function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Preuzmi')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->authorize('view')
            ->visible(fn (Document $record): bool => $record->file() !== null)
            ->url(fn (Document $record): string => $record->downloadUrl());
    }

    /**
     * The category id is unique, so the subquery needs no school filter; the
     * documents themselves are already school-scoped.
     *
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    protected static function orderByCategory(Builder $query): Builder
    {
        return $query->orderByRaw('(select sort_order from document_categories where document_categories.id = documents.document_category_id)');
    }
}
