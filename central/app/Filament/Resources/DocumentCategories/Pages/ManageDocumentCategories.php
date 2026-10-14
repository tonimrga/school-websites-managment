<?php

namespace App\Filament\Resources\DocumentCategories\Pages;

use App\Filament\Resources\DocumentCategories\DocumentCategoryResource;
use App\Models\DocumentCategory;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ManageDocumentCategories extends ManageRecords
{
    protected static string $resource = DocumentCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reorder')
                ->label('Promijeni redoslijed')
                ->icon(Heroicon::OutlinedArrowsUpDown)
                ->color('gray')
                ->visible(fn (): bool => Gate::allows('reorder', DocumentCategory::class))
                ->url(DocumentCategoryResource::getUrl('reorder')),
            CreateAction::make()->label('Nova kategorija'),
        ];
    }
}
