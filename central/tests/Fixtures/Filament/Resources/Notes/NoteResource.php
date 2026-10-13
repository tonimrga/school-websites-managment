<?php

namespace Tests\Fixtures\Filament\Resources\Notes;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Tests\Fixtures\Filament\Resources\Notes\Pages\CreateNote;
use Tests\Fixtures\Filament\Resources\Notes\Pages\EditNote;
use Tests\Fixtures\Filament\Resources\Notes\Pages\ListNotes;
use Tests\Fixtures\Models\Note;

/**
 * Placeholder content resource, registered only while running tests. It uses
 * the same building blocks real content resources will: a school-scoped
 * model, a SchoolContentPolicy, edit/delete actions and drag-and-drop reordering.
 */
class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('title'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotes::route('/'),
            'create' => CreateNote::route('/create'),
            'edit' => EditNote::route('/{record}/edit'),
        ];
    }
}
