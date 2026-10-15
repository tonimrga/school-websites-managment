<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Schemas\DocumentForm;
use App\Filament\Resources\Documents\Tables\DocumentsTable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Same submit as the button below the form. Ctrl+S stays on the bottom one.
            $this->getSaveFormAction()
                ->formId('form')
                ->keyBindings([]),
            // Less frequent actions stay out of the way in a menu.
            ActionGroup::make([
                DocumentsTable::downloadAction(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
                ->label('Više')
                ->icon(Heroicon::EllipsisVertical)
                ->color('gray')
                ->button(),
        ];
    }

    /**
     * Disabled and gray while a required field is empty. Used by both the
     * header and the bottom button.
     */
    protected function getSaveFormAction(): Action
    {
        $isComplete = fn (): bool => DocumentForm::missingForSaving($this->data ?? []) === [];

        return parent::getSaveFormAction()
            ->disabled(fn (): bool => ! $isComplete())
            ->color(fn (): string => $isComplete() ? 'primary' : 'gray');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
