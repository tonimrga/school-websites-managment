<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Tables\ArticlesTable;
use App\Models\Article;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Read-only preview of an article in the dashboard, before or after
 * publishing. The record is resolved through the school-scoped query, so
 * another school's article is a 404.
 */
class PreviewArticle extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ArticleResource::class;

    protected string $view = 'filament.resources.articles.pages.preview-article';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();
    }

    public function hydrate(): void
    {
        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Pregled vijesti';
    }

    public function getBreadcrumb(): string
    {
        return 'Pregled';
    }

    public function getArticle(): Article
    {
        /** @var Article */
        return $this->getRecord();
    }

    /**
     * @return list<array{thumb: string, large: string, caption: ?string}>
     */
    public function getGallery(): array
    {
        return $this->getArticle()->getMedia('gallery')
            ->map(fn (Media $media): array => [
                'thumb' => $media->getUrl('thumb'),
                'large' => $media->getUrl('large'),
                'caption' => $media->getCustomProperty('caption'),
            ])
            ->values()
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Natrag na uređivanje')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->authorize('update')
                ->url(fn (): string => ArticleResource::getUrl('edit', ['record' => $this->getRecord()])),
            ArticlesTable::publishAction()->record($this->getRecord()),
        ];
    }
}
