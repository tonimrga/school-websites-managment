<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The current school's five most recently changed documents (the school scope
 * applies). Documents in the trash are left out.
 */
class LatestDocuments extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return DocumentResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Zadnji dokumenti')
            ->query(fn (): Builder => Document::query()->with('category')->latest('updated_at')->latest('id')->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('title')
                    ->label('Naslov')
                    ->limit(80),
                TextColumn::make('category.name')
                    ->label('Kategorija'),
                TextColumn::make('is_published')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Objavljeno' : 'Nije objavljeno')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('updated_at')
                    ->label('Zadnja izmjena')
                    ->dateTime(),
            ])
            ->recordUrl(fn (Document $record): string => DocumentResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                Action::make('all')
                    ->label('Svi dokumenti')
                    ->url(DocumentResource::getUrl())
                    ->link(),
            ])
            ->emptyStateHeading('Još nema dokumenata.');
    }
}
