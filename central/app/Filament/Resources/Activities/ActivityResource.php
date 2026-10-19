<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Activities\Schemas\ActivityInfolist;
use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\School;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only activity log for the super admin, across all schools.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Administracija';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Dnevnik aktivnosti';

    protected static ?string $modelLabel = 'aktivnost';

    protected static ?string $pluralModelLabel = 'dnevnik aktivnosti';

    protected static ?string $slug = 'activity-log';

    /**
     * @var array<string, string>
     */
    public const EVENT_LABELS = [
        'created' => 'Stvoreno',
        'updated' => 'Izmijenjeno',
        'deleted' => 'Obrisano',
        'restored' => 'Vraćeno iz smeća',
        'published' => 'Objavljeno',
        'unpublished' => 'Povučeno iz objave',
        'login' => 'Prijava',
        'login_failed' => 'Neuspjela prijava',
    ];

    /**
     * @var array<string, string>
     */
    public const SUBJECT_LABELS = [
        Article::class => 'Vijest',
        Document::class => 'Dokument',
        DocumentCategory::class => 'Kategorija dokumenata',
        School::class => 'Dom',
        User::class => 'Korisnik',
    ];

    public static function infolist(Schema $schema): Schema
    {
        return ActivityInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }
}
