<?php

namespace App\Rules;

use App\Support\MapEmbedUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts an https Google Maps or OpenStreetMap embed URL, or an <iframe>
 * snippet with such a URL as its src.
 */
class AllowedMapEmbedUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $url = MapEmbedUrl::normalize(is_string($value) ? $value : null);

        if ($url !== null && ! MapEmbedUrl::isAllowed($url)) {
            $fail('Unesite adresu ugrađene karte s Google Mapsa (https://www.google.com/maps/embed?…) ili OpenStreetMapa (https://www.openstreetmap.org/export/embed.html?…).');
        }
    }
}
