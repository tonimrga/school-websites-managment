<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Concerns\SavesManualOrder;
use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Models\DocumentCategory;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Drag-and-drop ordering of documents, with a separate list per category so
 * a document can only move within its own category. Categories and
 * documents are loaded through the school-scoped queries, so another
 * school's records can neither be shown nor reordered.
 */
class ReorderDocuments extends Page
{
    use SavesManualOrder;

    protected static string $resource = DocumentResource::class;

    protected string $view = 'filament.resources.documents.pages.reorder-documents';

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
        abort_unless(Gate::allows('reorder', Document::class), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Redoslijed dokumenata';
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
            ->with(['documents' => fn ($query) => $query->orderBy('sort_order')->with('media')])
            ->get();
    }

    /**
     * Save the order of one category's documents. Keys that are not
     * documents of this category are ignored; documents missing from the
     * list keep their relative order after the listed ones.
     *
     * @param  array<int|string>  $order
     */
    public function reorderCategory(int|string $category, array $order): void
    {
        $category = DocumentCategory::query()->findOrFail($category);

        $this->saveManualOrder(
            Document::query()->where('document_category_id', $category->getKey()),
            $order,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Natrag na dokumente')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(DocumentResource::getUrl('index')),
        ];
    }
}
