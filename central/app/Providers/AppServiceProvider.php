<?php

namespace App\Providers;

use App\Livewire\SchoolSwitcher;
use App\Support\CurrentSchool;
use App\Support\HtmlSanitizer;
use App\Support\MediaLibrary\ImageProcessingFileAdder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\FileAdder;

class AppServiceProvider extends ServiceProvider
{
    public const DATE_FORMAT = 'd. m. Y.';

    public const DATE_TIME_FORMAT = 'd. m. Y. H:i';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentSchool::class);
        $this->app->singleton(HtmlSanitizer::class);

        // Every media library upload goes through the image pipeline.
        $this->app->bind(FileAdder::class, ImageProcessingFileAdder::class);

        // `php artisan serve` passes only a few environment variables to the
        // PHP server. On Windows, without TEMP/TMP, PHP falls back to the
        // unwritable C:\Windows for uploads and temporary files, so every
        // upload fails with "failed to upload".
        ServeCommand::$passthroughVariables = array_values(array_unique([
            ...ServeCommand::$passthroughVariables, 'TEMP', 'TMP',
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn (): Password => Password::min(12)->uncompromised());

        Event::listen(Login::class, function (Login $event): void {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        });

        $this->configureFilamentDateFormats();

        // No column chooser in any dashboard table.
        Table::configureUsing(fn (Table $table): Table => $table->columnManager(false));

        Livewire::component('school-switcher', SchoolSwitcher::class);

        // Placeholder content model used by the tenant isolation test suite.
        if ($this->app->runningUnitTests()) {
            $this->loadMigrationsFrom(base_path('tests/Fixtures/database/migrations'));
        }
    }

    /**
     * Show dates in the Croatian format (e.g. 12. 10. 2026.) everywhere in the dashboard.
     */
    protected function configureFilamentDateFormats(): void
    {
        Table::configureUsing(fn (Table $table): Table => $table
            ->defaultDateDisplayFormat(self::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME_FORMAT));

        Schema::configureUsing(fn (Schema $schema): Schema => $schema
            ->defaultDateDisplayFormat(self::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME_FORMAT));

        DateTimePicker::configureUsing(fn (DateTimePicker $picker): DateTimePicker => $picker
            ->defaultDateDisplayFormat(self::DATE_FORMAT)
            ->defaultDateTimeDisplayFormat(self::DATE_TIME_FORMAT));
    }
}
