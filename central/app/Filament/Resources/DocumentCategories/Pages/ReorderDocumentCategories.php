<?php

namespace App\Filament\Resources\DocumentCategories\Pages;

use App\Filament\Concerns\SavesManualOrder;
use App\Filament\Resources\DocumentCategories\DocumentCategoryResource;
use App\Models\DocumentCategory;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Drag-and-drop ordering of document categories, for editors and the super
 * admin alike. Categories are loaded through the school-scoped query, so
 * another school's categories can neither be shown nor reordered.
 */
class ReorderDocumentCategories extends Page
{
    use SavesManualOrder;

    protected static string $resource = DocumentCategoryResource::class;

    protected string $view = 'filament.resources.document-categories.pages.reorder-document-categories';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function hydrate(): void
    {
        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(Gate::allows('reorder', DocumentCategory::class), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Redoslijed kategorija';
    }

    public function getBreadcrumb(): string
    {
        return 'Redoslijed';
    }

    /**
     * @return Collection<int, DocumentCategory>
     */
    public function getCategories(): Collection
    {
        return DocumentCategory::query()
            ->orderBy('sort_order')
            ->withCount('documents')
            ->get();
    }

    /**
     * @param  array<int|string>  $order
     */
    public function reorder(array $order): void
    {
        $this->saveManualOrder(DocumentCategory::query(), $order);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Natrag na kategorije')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(DocumentCategoryResource::getUrl('index')),
        ];
    }
}
