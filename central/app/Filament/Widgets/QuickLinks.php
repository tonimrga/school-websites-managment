<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Support\CurrentSchool;
use Filament\Widgets\Widget;

/**
 * Home screen shortcuts for the current school: new article, new document
 * and its public site.
 */
class QuickLinks extends Widget
{
    protected static ?int $sort = 1;

    // Shortcuts are cheap to render and should be there immediately.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.quick-links';

    public static function canView(): bool
    {
        return app(CurrentSchool::class)->id() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'school' => app(CurrentSchool::class)->get(),
            'newArticleUrl' => ArticleResource::canCreate() ? ArticleResource::getUrl('create') : null,
            'newDocumentUrl' => DocumentResource::canCreate() ? DocumentResource::getUrl('create') : null,
        ];
    }
}
