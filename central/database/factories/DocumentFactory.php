<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'document_category_id' => fn (array $attributes) => DocumentCategory::factory()
                ->state(['school_id' => $attributes['school_id']]),
            'title' => rtrim(fake()->unique()->sentence(4), '.'),
            'description' => null,
            'document_date' => null,
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }

    /**
     * Attach a small generated PDF (needs a faked or real documents disk).
     */
    public function withFile(string $name = 'dokument.pdf'): static
    {
        return $this->afterCreating(function (Document $document) use ($name): void {
            $document->addMedia(UploadedFile::fake()->createWithContent($name, static::pdf()))
                ->toMediaCollection('file');
        });
    }

    /**
     * The contents of a minimal valid PDF.
     */
    public static function pdf(string $text = 'Dokument'): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\n"
            ."% {$text}\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }
}
