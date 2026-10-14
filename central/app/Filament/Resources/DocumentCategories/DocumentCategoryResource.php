<?php

namespace App\Filament\Resources\DocumentCategories;

use App\Filament\Resources\DocumentCategories\Pages\ManageDocumentCategories;
use App\Filament\Resources\DocumentCategories\Pages\ReorderDocumentCategories;
use App\Filament\Resources\DocumentCategories\Schemas\DocumentCategoryForm;
use App\Filament\Resources\DocumentCategories\Tables\DocumentCategoriesTable;
use App\Models\DocumentCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Editors only reorder categories; the policy hides creating, renaming and
 * deleting from everyone but the super admin.
 */
class DocumentCategoryResource extends Resource
{
    protected static ?string $model = DocumentCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static ?string $navigationLabel = 'Kategorije dokumenata';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'kategorija dokumenata';

    protected static ?string $pluralModelLabel = 'kategorije dokumenata';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DocumentCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDocumentCategories::route('/'),
            'reorder' => ReorderDocumentCategories::route('/reorder'),
        ];
    }
}
