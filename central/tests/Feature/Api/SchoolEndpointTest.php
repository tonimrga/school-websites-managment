<?php

use App\Models\School;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| GET /api/v1/schools/{slug} returns the school's public settings only, in
| the shape every school site page uses.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

it('returns exactly the public school fields', function () {
    $school = School::factory()->create([
        'name' => 'Učenički dom Ivana Gundulića',
        'slug' => 'ucenicki-dom-ivana-gundulica',
        'site_url' => 'https://dom-gundulic.example.com',
        'theme' => 'green',
        'address' => 'Ulica 1',
        'postal_code' => '10000',
        'city' => 'Zagreb',
        'phone' => '01 234 5678',
        'email' => 'info@dom.example.com',
        'office_hours' => 'Pon–pet 8–16',
        'map_embed_url' => 'https://www.openstreetmap.org/export/embed.html?bbox=1,2,3,4',
        'contact_note' => 'Ravnatelj: 01 111 2222',
        'social_links' => ['facebook' => 'https://www.facebook.com/dom'],
        'old_site_url' => 'https://stari.dom.example.com',
    ]);

    $this->getJson(schoolApi($school))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'school' => [
                'name' => 'Učenički dom Ivana Gundulića',
                'slug' => 'ucenicki-dom-ivana-gundulica',
                'site_url' => 'https://dom-gundulic.example.com',
                'theme' => 'green',
                'logo' => null,
                'address' => 'Ulica 1',
                'postal_code' => '10000',
                'city' => 'Zagreb',
                'phone' => '01 234 5678',
                'email' => 'info@dom.example.com',
                'office_hours' => 'Pon–pet 8–16',
                'map_embed_url' => 'https://www.openstreetmap.org/export/embed.html?bbox=1,2,3,4',
                'contact_note' => 'Ravnatelj: 01 111 2222',
                'social_links' => [
                    'facebook' => 'https://www.facebook.com/dom',
                    'instagram' => null,
                    'youtube' => null,
                ],
                'old_site_url' => 'https://stari.dom.example.com',
            ],
        ]);
});

it('sends UTF-8 JSON with unescaped characters', function () {
    $school = School::factory()->create(['name' => 'Učenički dom Đakovo']);

    expect($this->get(schoolApi($school))->getContent())
        ->toContain('"name":"Učenički dom Đakovo"')
        ->toContain('"site_url":"https://');
});

it('lists all social networks as null when none are set', function () {
    $school = School::factory()->create(['social_links' => null]);

    $this->getJson(schoolApi($school))
        ->assertJsonPath('school.social_links', ['facebook' => null, 'instagram' => null, 'youtube' => null]);
});

it('returns the logo conversions with the size of the large one, never the stored image', function () {
    $school = School::factory()->create();
    $media = $school->addMedia(UploadedFile::fake()->image('logo.png', 2400, 600))->toMediaCollection('logo');

    $logo = $this->getJson(schoolApi($school))->assertOk()->json('school.logo');

    expect($logo)->toBe([
        'small' => $media->getUrl('small'),
        'large' => $media->getUrl('large'),
        // Stored at 2000×500, the large version fits 1200×1200.
        'width' => 1200,
        'height' => 300,
    ])
        ->and($logo['small'])->toEndWith('.webp')
        ->and($logo['large'])->toEndWith('.webp')
        ->and(json_encode($logo))->not->toContain($media->file_name);
});
