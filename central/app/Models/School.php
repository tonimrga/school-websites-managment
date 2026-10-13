<?php

namespace App\Models;

use App\Enums\SchoolTheme;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tenant. Every content row belongs to exactly one school.
 */
#[Fillable([
    'name', 'slug', 'site_url', 'theme',
    'address', 'postal_code', 'city', 'phone', 'email',
    'office_hours', 'map_embed_url', 'contact_note', 'social_links',
    'old_site_url', 'is_active',
])]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    protected $attributes = [
        'theme' => 'blue',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'theme' => SchoolTheme::class,
            'social_links' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
