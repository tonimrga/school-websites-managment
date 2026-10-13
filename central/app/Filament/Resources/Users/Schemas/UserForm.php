<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\School;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

/**
 * Role, school and active flag are not fillable on User; the create and edit
 * pages assign them explicitly from the validated form state.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Ime i prezime')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('E-pošta')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('school_id')
                    ->label('Škola')
                    ->options(fn (): array => School::orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->visible(fn (?User $record): bool => $record === null || $record->isEditor()),
                TextInput::make('password')
                    ->label(fn (string $operation): string => $operation === 'create' ? 'Lozinka' : 'Nova lozinka')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Ostavite prazno da lozinka ostane ista.' : null)
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->rule(Password::default())
                    ->confirmed()
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                TextInput::make('password_confirmation')
                    ->label('Ponovite lozinku')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(false),
            ]);
    }
}
