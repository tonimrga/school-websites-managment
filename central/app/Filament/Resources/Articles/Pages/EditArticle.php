<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Tables\ArticlesTable;
use App\Models\Article;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Same submit as the button below the form, so long articles
            // can be saved without scrolling. Ctrl+S stays on the bottom one.
            $this->getSaveFormAction()
                ->formId('form')
                ->keyBindings([]),
            // Publishing from the form saves pending changes first.
            ArticlesTable::publishAction()
                ->action(function (Article $record, Action $action): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

                    ArticlesTable::publish($record, $action);

                    $this->refreshFormData(['published_at']);
                }),
            ArticlesTable::unpublishAction(),
            // Less frequent actions stay out of the way in a menu.
            ActionGroup::make([
                ArticlesTable::previewAction(),
                ArticlesTable::captionsAction(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
                ->label('Više')
                ->icon(Heroicon::EllipsisVertical)
                ->color('gray')
                ->button(),
        ];
    }
}
