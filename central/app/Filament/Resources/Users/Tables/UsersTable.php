<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Password;
use SensitiveParameter;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Ime i prezime')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-pošta')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Uloga')
                    ->badge(),
                TextColumn::make('school.name')
                    ->label('Škola')
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktivan')
                    ->boolean(),
                TextColumn::make('last_login_at')
                    ->label('Zadnja prijava')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('school')
                    ->label('Škola')
                    ->relationship('school', 'name'),
                SelectFilter::make('role')
                    ->label('Uloga')
                    ->options(UserRole::class),
                TernaryFilter::make('is_active')
                    ->label('Aktivan'),
            ])
            ->recordActions([
                EditAction::make(),
                static::sendPasswordResetAction(),
                static::deactivateAction(),
                static::activateAction(),
            ]);
    }

    public static function deactivateAction(): Action
    {
        return Action::make('deactivate')
            ->label('Deaktiviraj')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Korisnik se više neće moći prijaviti, a njegove otvorene sesije bit će prekinute.')
            ->visible(fn (User $record): bool => $record->is_active)
            ->authorize('deactivate')
            ->action(function (User $record, Action $action): void {
                $record->deactivate();
                $action->successNotificationTitle('Korisnik je deaktiviran.')->success();
            });
    }

    public static function activateAction(): Action
    {
        return Action::make('activate')
            ->label('Aktiviraj')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (User $record): bool => ! $record->is_active)
            ->authorize('update')
            ->action(function (User $record, Action $action): void {
                $record->forceFill(['is_active' => true])->save();
                $action->successNotificationTitle('Korisnik je aktiviran.')->success();
            });
    }

    /**
     * Send the same reset link as Filament's "forgot password" page.
     */
    public static function sendPasswordResetAction(): Action
    {
        return Action::make('sendPasswordReset')
            ->label('Pošalji poveznicu za lozinku')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => "Na adresu {$record->email} poslat ćemo poveznicu za postavljanje nove lozinke. Poveznica vrijedi 60 minuta.")
            ->visible(fn (User $record): bool => $record->is_active)
            ->authorize('update')
            ->action(function (User $record, Action $action): void {
                $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
                    ['email' => $record->email],
                    function (CanResetPassword $user, #[SensitiveParameter] string $token): void {
                        $notification = app(ResetPasswordNotification::class, ['token' => $token]);
                        $notification->url = Filament::getResetPasswordUrl($token, $user);

                        $user->notify($notification);
                    },
                );

                if ($status !== Password::RESET_LINK_SENT) {
                    $action->failureNotificationTitle(__($status))->failure();

                    return;
                }

                $action->successNotificationTitle('Poveznica za lozinku je poslana.')->success();
            });
    }
}
