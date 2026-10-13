<?php

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(5), '.');

        return [
            'school_id' => School::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'body' => '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>',
            'status' => ArticleStatus::Draft,
            'published_at' => null,
            'is_featured' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ArticleStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ArticleStatus::Published,
            'published_at' => now()->addWeek(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Attach a generated cover image (needs a faked or real media disk).
     */
    public function withCover(): static
    {
        return $this->afterCreating(function (Article $article): void {
            $article->addMedia(UploadedFile::fake()->image('naslovna.jpg', 800, 600))
                ->toMediaCollection('cover');
        });
    }

    /**
     * Attach generated gallery images (needs a faked or real media disk).
     */
    public function withGallery(int $count = 2): static
    {
        return $this->afterCreating(function (Article $article) use ($count): void {
            foreach (range(1, $count) as $number) {
                $article->addMedia(UploadedFile::fake()->image("galerija-{$number}.jpg", 800, 600))
                    ->toMediaCollection('gallery');
            }
        });
    }
}
