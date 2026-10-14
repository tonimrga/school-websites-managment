<?php

namespace App\Filament\Resources\DocumentCategories\Tables;

use App\Exceptions\CategoryHasDocuments;
use App\Models\DocumentCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Reordering happens on the ReorderDocumentCategories page.
            ->defaultSort('sort_order')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Naziv'),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->color('gray'),
                TextColumn::make('documents_count')
                    ->label('Dokumenata')
                    ->counts('documents'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Preimenuj')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->modalHeading('Preimenuj kategoriju'),
                static::deleteAction(),
            ]);
    }

    /**
     * A category with documents is not deleted; its trashed documents are
     * permanently deleted with it (see DocumentCategory).
     */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription('Kategorija se trajno briše. Dokumenti ove kategorije koji su u smeću trajno se brišu zajedno s njom.')
            ->before(function (DocumentCategory $record, DeleteAction $action): void {
                if (! $record->documents()->exists()) {
                    return;
                }

                Notification::make()
                    ->title('Kategorija nije obrisana.')
                    ->body(CategoryHasDocuments::make()->getMessage())
                    ->danger()
                    ->send();

                $action->cancel();
            });
    }
}
