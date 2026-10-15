<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Rules\AllowedDocumentContent;
use App\Support\CurrentSchool;
use App\Support\DocumentFileType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                static::documentSection()->columnSpan(['lg' => 2]),
                static::publishingSection()->columnSpan(['lg' => 1]),
            ]);
    }

    protected static function documentSection(): Section
    {
        return Section::make('Dokument')
            ->schema([
                TextInput::make('title')
                    ->label('Naslov')
                    ->required()
                    ->maxLength(200)
                    // Live, so the save button is enabled as soon as everything is filled in.
                    ->live(debounce: 500),
                Select::make('document_category_id')
                    ->label('Kategorija')
                    ->relationship(
                        'category',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('sort_order'),
                    )
                    // The options are school-scoped; the rule also rejects a
                    // category id of another school sent in the request.
                    ->rule(fn (): Exists => Rule::exists('document_categories', 'id')
                        ->where('school_id', app(CurrentSchool::class)->id()))
                    ->required()
                    ->live(),
                Textarea::make('description')
                    ->label('Opis')
                    ->maxLength(2000)
                    ->rows(3),
                static::fileUpload(),
            ]);
    }

    protected static function publishingSection(): Section
    {
        return Section::make('Objava')
            ->schema([
                // Why the save button is disabled; updates as the fields are filled in.
                Text::make(fn (Get $get): string => 'Za spremanje nedostaje: '.implode(', ', static::missingForSaving(static::requiredState($get))).'.')
                    ->color('warning')
                    ->visible(fn (Get $get): bool => static::missingForSaving(static::requiredState($get)) !== []),
                DatePicker::make('document_date')
                    ->label('Datum dokumenta')
                    ->helperText('Prikazuje se na stranici, npr. datum odluke.'),
                Toggle::make('is_published')
                    ->label('Objavljeno')
                    ->helperText('Samo objavljeni dokumenti vidljivi su na javnoj stranici doma.')
                    ->default(true),
            ]);
    }

    /**
     * Required fields the form state still lacks, as Croatian labels. Only
     * drives the save button; the fields' own validation stays authoritative.
     *
     * @param  array<string, mixed>  $data  form state with title, category and file
     * @return list<string>
     */
    public static function missingForSaving(array $data): array
    {
        return array_values(array_filter([
            blank($data['title'] ?? null) ? 'naslov' : null,
            blank($data['document_category_id'] ?? null) ? 'kategorija' : null,
            array_filter((array) ($data['file'] ?? [])) === [] ? 'datoteka' : null,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function requiredState(Get $get): array
    {
        return ['title' => $get('title'), 'document_category_id' => $get('document_category_id'), 'file' => $get('file')];
    }

    /**
     * Documents are checked by extension and by their real contents. They are
     * stored on a non-public disk and reach visitors only through the
     * download route.
     */
    protected static function fileUpload(): SpatieMediaLibraryFileUpload
    {
        $maxKb = (int) config('cms.documents.max_upload_kb');

        return SpatieMediaLibraryFileUpload::make('file')
            ->label('Datoteka')
            ->helperText('PDF, Word, Excel ili OpenDocument, najviše '.intdiv($maxKb, 1024).' MB. Zamjenom datoteke dokument zadržava mjesto i poveznicu.')
            ->collection('file')
            ->disk((string) config('cms.documents.disk'))
            ->visibility('private')
            ->required()
            ->live()
            // Some DOCX/XLSX files are only recognised as zip archives by their
            // contents; AllowedDocumentContent looks inside those.
            ->acceptedFileTypes([...DocumentFileType::mimeTypes(), 'application/zip', 'application/octet-stream'])
            ->mimeTypeMap(DocumentFileType::MIME_TYPES)
            ->rule('extensions:'.implode(',', DocumentFileType::extensions()))
            ->rule(new AllowedDocumentContent)
            ->maxSize($maxKb);
    }
}
