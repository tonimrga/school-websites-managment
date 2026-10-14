<?php

namespace Database\Factories;

use App\Models\DocumentCategory;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentCategory>
 */
class DocumentCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Kategorija '.fake()->unique()->word();

        return [
            'school_id' => School::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
