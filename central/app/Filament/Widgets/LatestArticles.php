<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The current school's five most recently changed articles (the school scope
 * applies). Articles in the trash are left out.
 */
class LatestArticles extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return ArticleResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Zadnje vijesti')
            ->query(fn (): Builder => Article::query()->latest('updated_at')->latest('id')->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('title')
                    ->label('Naslov')
                    ->limit(80),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (Article $record): string => $record->statusLabel())
                    ->color(fn (Article $record): string => $record->statusColor()),
                TextColumn::make('updated_at')
                    ->label('Zadnja izmjena')
                    ->dateTime(),
            ])
            ->recordUrl(fn (Article $record): string => ArticleResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                Action::make('all')
                    ->label('Sve vijesti')
                    ->url(ArticleResource::getUrl())
                    ->link(),
            ])
            ->emptyStateHeading('Još nema vijesti.');
    }
}
