<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Models\Article;
use App\Rules\AllowedImageContent;
use App\Support\CurrentSchool;
use App\Support\ImageProcessor;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class ArticleForm
{
    public const GDPR_REMINDER = 'Objavljujete li fotografije učenika, provjerite imate li suglasnost roditelja.';

    /**
     * Matches the HTML sanitizer's allow-list; no inline images in version 1.
     */
    public const TOOLBAR_BUTTONS = [
        ['bold', 'italic', 'underline', 'strike', 'link'],
        ['h2', 'h3', 'h4'],
        ['blockquote', 'bulletList', 'orderedList'],
        ['table'],
        ['undo', 'redo'],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    static::contentSection(),
                    static::photosSection(),
                ])->columnSpan(['lg' => 2]),
                static::publishingSection()->columnSpan(['lg' => 1]),
            ]);
    }

    protected static function contentSection(): Section
    {
        return Section::make('Sadržaj')
            ->schema([
                TextInput::make('title')
                    ->label('Naslov')
                    ->required()
                    ->maxLength(200)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                        // Follow the title until the slug is edited by hand.
                        if (blank($get('slug')) || $get('slug') === Str::slug((string) $old)) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Slug (adresa vijesti)')
                    ->required()
                    ->maxLength(200)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->validationMessages([
                        'regex' => 'Dopuštena su samo mala slova, brojke i crtice.',
                        'unique' => 'Vijest s ovom adresom već postoji (provjerite i obrisane vijesti).',
                    ])
                    // Unique per school, including articles in the trash.
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('school_id', app(CurrentSchool::class)->id()),
                    ),
                Textarea::make('excerpt')
                    ->label('Sažetak')
                    ->helperText('Prikazuje se u popisu vijesti i kod dijeljenja na društvenim mrežama. Ako ostane prazan, popunit će se iz početka teksta.')
                    ->maxLength(Article::EXCERPT_LENGTH)
                    ->rows(3),
                RichEditor::make('body')
                    ->label('Tekst')
                    ->toolbarButtons(self::TOOLBAR_BUTTONS),
            ]);
    }

    protected static function photosSection(): Section
    {
        return Section::make('Fotografije')
            ->schema([
                Callout::make('Privola roditelja')
                    ->description(self::GDPR_REMINDER)
                    ->warning(),
                static::imageUpload('cover')
                    ->label('Naslovna fotografija')
                    ->helperText('Potrebna za objavu. Prikazuje se u popisu vijesti i kod dijeljenja na društvenim mrežama.')
                    ->imageEditor()
                    ->imageEditorAspectRatioOptions([null, '16:9', '4:3'])
                    // Fixed height, so the box doesn't grow while the image loads.
                    ->imagePreviewHeight('320'),
                static::imageUpload('gallery')
                    ->label('Galerija')
                    ->helperText('Do '.config('cms.articles.gallery_max').' fotografija. Redoslijed mijenjate povlačenjem; opise dodajete gumbom „Opisi fotografija” nakon spremanja.')
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->panelLayout('grid')
                    // Equal 4:3 tiles that show a loading spinner until their image arrives.
                    ->itemPanelAspectRatio(0.75)
                    ->maxFiles((int) config('cms.articles.gallery_max')),
            ]);
    }

    protected static function publishingSection(): Section
    {
        return Section::make('Objava')
            ->schema([
                Text::make(fn (?Article $record): string => $record?->statusLabel() ?? 'Skica')
                    ->badge()
                    ->color(fn (?Article $record): string => $record?->statusColor() ?? 'gray'),
                DateTimePicker::make('published_at')
                    ->label('Datum objave')
                    ->seconds(false)
                    ->helperText('Prazno = trenutak objave. Budući datum zakazuje objavu.'),
                Toggle::make('is_featured')
                    ->label('Istaknuta vijest')
                    ->helperText('Prikazuje se na naslovnici i na vrhu popisa vijesti.'),
            ]);
    }

    /**
     * Images are checked by real MIME type and by extension; the media
     * library additionally resizes them and strips metadata before storing.
     */
    protected static function imageUpload(string $collection): SpatieMediaLibraryFileUpload
    {
        return SpatieMediaLibraryFileUpload::make($collection)
            ->collection($collection)
            ->acceptedFileTypes(array_keys(ImageProcessor::ALLOWED_TYPES))
            ->rule('extensions:'.implode(',', ['jpg', 'jpeg', 'png', 'webp']))
            ->rule(new AllowedImageContent)
            ->maxSize((int) config('cms.images.max_upload_kb'))
            // Preview the uncropped 1600 px version: the 400×300 thumb looks
            // blurry when the upload field stretches it to full width.
            ->conversion('large')
            ->openable();
    }
}
