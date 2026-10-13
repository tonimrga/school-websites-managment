<?php

namespace Database\Factories;

use App\Enums\SchoolTheme;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Osnovna škola '.fake()->unique()->lastName();
        $slug = Str::slug($name);

        return [
            'name' => $name,
            'slug' => $slug,
            'site_url' => "https://{$slug}.example.com",
            'theme' => fake()->randomElement(SchoolTheme::cases()),
            'address' => fake()->streetAddress(),
            'postal_code' => fake()->numerify('#####'),
            'city' => fake()->city(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
