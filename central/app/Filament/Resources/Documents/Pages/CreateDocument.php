<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Schemas\DocumentForm;
use Filament\Actions\Action;
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

    /**
     * Disabled and gray until the required fields are filled in. Used by both
     * the header and the bottom button.
     */
    protected function getCreateFormAction(): Action
    {
        $isComplete = fn (): bool => DocumentForm::missingForSaving($this->data ?? []) === [];

        return parent::getCreateFormAction()
            ->disabled(fn (): bool => ! $isComplete())
            ->color(fn (): string => $isComplete() ? 'primary' : 'gray');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
