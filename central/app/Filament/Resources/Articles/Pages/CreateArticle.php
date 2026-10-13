<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Exceptions\ArticleNotPublishable;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * New articles are saved as drafts; "Objavi" saves and publishes in one step.
 */
class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    protected static bool $canCreateAnother = false;

    protected bool $publishAfterCreate = false;

    public function createAndPublish(): void
    {
        $this->publishAfterCreate = true;

        $this->create();
    }

    /**
     * The same buttons as below the form, so long articles can be saved
     * without scrolling. Ctrl+S stays on the bottom one.
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Spremi kao skicu')
                ->formId('form')
                ->keyBindings([]),
            $this->getCreateAndPublishAction(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Spremi kao skicu'),
            $this->getCreateAndPublishAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getCreateAndPublishAction(): Action
    {
        return Action::make('createAndPublish')
            ->label('Objavi')
            ->color('success')
            ->action('createAndPublish');
    }

    protected function afterCreate(): void
    {
        if (! $this->publishAfterCreate) {
            return;
        }

        /** @var Article $article */
        $article = $this->getRecord();

        try {
            $article->publish();
        } catch (ArticleNotPublishable $exception) {
            Notification::make()
                ->title('Vijest je spremljena kao skica.')
                ->body($exception->getMessage())
                ->warning()
                ->persistent()
                ->send();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        /** @var Article $article */
        $article = $this->getRecord();

        return match (true) {
            $article->isScheduled() => 'Vijest je spremljena i zakazana za objavu.',
            $article->isPublished() => 'Vijest je objavljena.',
            default => 'Vijest je spremljena kao skica.',
        };
    }
}
