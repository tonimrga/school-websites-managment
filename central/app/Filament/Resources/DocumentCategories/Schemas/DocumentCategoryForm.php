<?php

namespace App\Filament\Resources\DocumentCategories\Schemas;

use App\Support\CurrentSchool;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class DocumentCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label('Naziv')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                        // Follow the name until the slug is edited by hand.
                        if (blank($get('slug')) || $get('slug') === Str::slug((string) $old)) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Slug (adresa kategorije)')
                    ->helperText('Koristi se u adresi stranice s dokumentima. Promjena mijenja postojeće poveznice na kategoriju.')
                    ->required()
                    ->maxLength(100)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->validationMessages([
                        'regex' => 'Dopuštena su samo mala slova, brojke i crtice.',
                        'unique' => 'Kategorija s ovom adresom već postoji.',
                    ])
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('school_id', app(CurrentSchool::class)->id()),
                    ),
            ]);
    }
}
