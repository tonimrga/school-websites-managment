<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Schools\Schemas\SchoolForm;
use App\Models\School;
use App\Support\CurrentSchool;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/**
 * "Postavke doma": settings of the school the user is working on (their own
 * school for an editor, the switcher's school for the super admin).
 *
 * The school always comes from CurrentSchool, never from the request. Editors
 * may only change School::EDITOR_SETTINGS and the logo; anything else in the
 * submitted state is dropped on save, not just hidden in the form.
 *
 * @property-read Schema $form
 */
class SchoolSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Postavke doma';

    protected static ?string $title = 'Postavke doma';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'school-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * The school the form was loaded for. Saving is refused when the super
     * admin has since switched to another school (e.g. in another tab).
     */
    #[Locked]
    public ?int $schoolId = null;

    protected ?School $school = null;

    public static function canAccess(): bool
    {
        $school = app(CurrentSchool::class)->get();

        return $school !== null && Gate::allows('updateSettings', $school);
    }

    public function mount(): void
    {
        $school = app(CurrentSchool::class)->get();

        abort_unless($school !== null && Gate::allows('updateSettings', $school), 403);

        $this->schoolId = $school->getKey();
        $this->school = $school;

        $this->form->fill($school->attributesToArray());
    }

    public function hydrate(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    protected function getSchool(): School
    {
        return $this->school ??= School::findOrFail($this->schoolId);
    }

    protected function canEditAdminSettings(): bool
    {
        return Gate::allows('updateAdminSettings', $this->getSchool());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->operation('edit')
            ->model($this->getSchool())
            ->statePath('data')
            ->columns(1)
            ->components([
                // Editors see what they are editing, but cannot change it.
                Section::make('Dom')
                    ->schema([
                        Text::make(fn (): string => $this->getSchool()->name)->weight(FontWeight::Bold),
                        Text::make(fn (): string => $this->getSchool()->site_url)->color('gray'),
                    ])
                    ->hidden(fn (): bool => $this->canEditAdminSettings()),
                SchoolForm::basicSection()
                    ->visible(fn (): bool => $this->canEditAdminSettings()),
                SchoolForm::logoSection(),
                SchoolForm::contactSection(),
                SchoolForm::socialSection(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([$this->getSaveFormAction()])->key('form-actions'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Same submit as the button below the form. Ctrl+S stays on the bottom one.
            $this->getSaveFormAction()
                ->formId('form')
                ->keyBindings([]),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label('Spremi')
            ->submit('save')
            ->keyBindings(['mod+s']);
    }

    public function save(): void
    {
        if (app(CurrentSchool::class)->id() !== $this->schoolId) {
            Notification::make()
                ->danger()
                ->title('Odabran je drugi dom.')
                ->body('U međuvremenu ste odabrali drugi dom. Učitajte stranicu ponovno; ništa nije spremljeno.')
                ->send();

            return;
        }

        $school = $this->getSchool();

        $this->authorize('updateSettings', $school);

        $allowed = $this->canEditAdminSettings()
            ? [...School::ADMIN_SETTINGS, ...School::EDITOR_SETTINGS]
            : School::EDITOR_SETTINGS;

        DB::transaction(function () use ($school, $allowed): void {
            // Validates, and saves the logo to this school.
            $data = $this->form->getState();

            $school->fill(Arr::only($data, $allowed))->save();
        });

        Notification::make()
            ->success()
            ->title('Postavke su spremljene.')
            ->send();
    }
}
