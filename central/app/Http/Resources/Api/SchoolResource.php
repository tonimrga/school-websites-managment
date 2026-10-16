<?php

namespace App\Http\Resources\Api;

use App\Models\School;
use App\Support\ConversionSize;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public settings of a school, shown in every page's layout.
 *
 * @mixin School
 */
class SchoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'site_url' => $this->site_url,
            'theme' => $this->theme->value,
            'logo' => $this->logo(),
            'address' => $this->address,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'phone' => $this->phone,
            'email' => $this->email,
            'office_hours' => $this->office_hours,
            'map_embed_url' => $this->map_embed_url,
            'contact_note' => $this->contact_note,
            'social_links' => $this->socialLinks(),
            'old_site_url' => $this->old_site_url,
        ];
    }

    /**
     * @return array{small: string, large: string, width: ?int, height: ?int}|null
     */
    protected function logo(): ?array
    {
        $media = $this->getFirstMedia('logo');

        if ($media === null) {
            return null;
        }

        $size = ConversionSize::for($media, 'large');

        return [
            'small' => $media->getUrl('small'),
            'large' => $media->getUrl('large'),
            'width' => $size['width'] ?? null,
            'height' => $size['height'] ?? null,
        ];
    }

    /**
     * Every network is always present, null when not set.
     *
     * @return array<string, ?string>
     */
    protected function socialLinks(): array
    {
        $links = (array) $this->social_links;

        return collect(School::SOCIAL_NETWORKS)
            ->mapWithKeys(fn (string $network): array => [$network => filled($links[$network] ?? null) ? $links[$network] : null])
            ->all();
    }
}
