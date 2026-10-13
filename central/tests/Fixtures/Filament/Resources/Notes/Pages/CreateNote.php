<?php

namespace Tests\Fixtures\Filament\Resources\Notes\Pages;

use Filament\Resources\Pages\CreateRecord;
use Tests\Fixtures\Filament\Resources\Notes\NoteResource;

class CreateNote extends CreateRecord
{
    protected static string $resource = NoteResource::class;
}
