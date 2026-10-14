<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * The same button as below the form. Ctrl+S stays on the bottom one.
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->formId('form')
                ->keyBindings([]),
        ];
    }
}
