<?php

namespace App\Filament\Resources\Schools\Schemas;

use App\Enums\SchoolTheme;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SchoolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Osnovni podaci')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Naziv')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                                // Follow the name until the slug is edited by hand.
                                if (blank($get('slug')) || $get('slug') === Str::slug((string) $old)) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            })
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->label('Slug (adresa u API-ju)')
                            ->required()
                            ->maxLength(100)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->validationMessages(['regex' => 'Dopuštena su samo mala slova, brojke i crtice.'])
                            ->unique(ignoreRecord: true),
                        Select::make('theme')
                            ->label('Tema')
                            ->options(SchoolTheme::class)
                            ->default(SchoolTheme::Blue)
                            ->required(),
                        TextInput::make('site_url')
                            ->label('Adresa web stranice')
                            ->required()
                            ->url()
                            ->maxLength(255),
                        TextInput::make('old_site_url')
                            ->label('Adresa stare web stranice')
                            ->url()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Aktivan')
                            ->default(true),
                    ]),
                Section::make('Kontakt')
                    ->columns(2)
                    ->schema([
                        TextInput::make('address')
                            ->label('Adresa')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('postal_code')
                            ->label('Poštanski broj')
                            ->required()
                            ->maxLength(20),
                        TextInput::make('city')
                            ->label('Mjesto')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('email')
                            ->label('E-pošta')
                            ->email()
                            ->maxLength(255),
                        Textarea::make('office_hours')
                            ->label('Radno vrijeme')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('map_embed_url')
                            ->label('Adresa ugrađene karte')
                            ->url()
                            ->maxLength(2048)
                            ->columnSpanFull(),
                        Textarea::make('contact_note')
                            ->label('Napomena na stranici Kontakt')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Društvene mreže')
                    ->columns(3)
                    ->schema([
                        TextInput::make('social_links.facebook')
                            ->label('Facebook')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('social_links.instagram')
                            ->label('Instagram')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('social_links.youtube')
                            ->label('YouTube')
                            ->url()
                            ->maxLength(255),
                    ]),
            ]);
    }
}
