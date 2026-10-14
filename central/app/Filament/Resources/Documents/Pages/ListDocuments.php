<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reorder')
                ->label('Promijeni redoslijed')
                ->icon(Heroicon::OutlinedArrowsUpDown)
                ->color('gray')
                ->visible(fn (): bool => Gate::allows('reorder', Document::class))
                ->url(DocumentResource::getUrl('reorder')),
            CreateAction::make()->label('Novi dokument'),
        ];
    }
}
