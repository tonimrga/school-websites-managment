<?php

use App\Enums\SchoolTheme;
use App\Filament\Pages\SchoolSettings;
use App\Models\Activity;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/*
| "Postavke doma" always edits the current school. Editors change contact
| details, social links and the logo; name, slug, site URL, theme, old site
| URL and the active flag are left to the super admin, on the server too.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->schoolA = School::factory()->create([
        'name' => 'Dom A',
        'slug' => 'dom-a',
        'site_url' => 'https://dom-a.example',
        'theme' => SchoolTheme::Blue,
        'old_site_url' => null,
        'city' => 'Zagreb',
    ]);
    $this->schoolB = School::factory()->create(['name' => 'Dom B', 'city' => 'Split']);
});

/**
 * @return array<string, mixed>
 */
function adminSettingsSnapshot(School $school): array
{
    return $school->fresh()->only(School::ADMIN_SETTINGS);
}

function addLogo(School $school, string $name = 'logo.png'): Media
{
    return $school->addMedia(UploadedFile::fake()->image($name, 300, 200))->toMediaCollection('logo');
}

describe('editor', function () {
    beforeEach(function () {
        $this->editor = User::factory()->editor($this->schoolA)->create();
        $this->actingAs($this->editor);
    });

    it('opens the settings of their own school', function () {
        $this->get('/admin/school-settings')
            ->assertOk()
            ->assertSee('Postavke doma')
            ->assertSee('Dom A')
            ->assertDontSee('Dom B');

        Livewire::test(SchoolSettings::class)
            ->assertSet('schoolId', $this->schoolA->id)
            ->assertSchemaStateSet(['city' => 'Zagreb'])
            ->assertFormFieldDoesNotExist('name')
            ->assertFormFieldDoesNotExist('slug')
            ->assertFormFieldDoesNotExist('theme')
            ->assertFormFieldDoesNotExist('is_active');
    });

    it('sees the settings page in the navigation', function () {
        $this->get('/admin')->assertOk()->assertSee('/admin/school-settings');
    });

    it('changes the contact details and social links of their own school', function () {
        Livewire::test(SchoolSettings::class)
            ->fillForm([
                'address' => 'Nova ulica 5',
                'postal_code' => '10010',
                'city' => 'Zagreb-Novi',
                'phone' => '01 999 9999',
                'email' => 'tajnistvo@dom-a.example',
                'office_hours' => 'Pon–pet 8–16',
                'map_embed_url' => 'https://www.google.com/maps/embed?pb=!1m18',
                'contact_note' => 'Ravnatelj: 01 888 8888',
                'social_links' => ['facebook' => 'https://www.facebook.com/doma', 'instagram' => null, 'youtube' => 'https://youtu.be/abc'],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Postavke su spremljene.');

        expect($this->schoolA->fresh())
            ->address->toBe('Nova ulica 5')
            ->postal_code->toBe('10010')
            ->city->toBe('Zagreb-Novi')
            ->phone->toBe('01 999 9999')
            ->email->toBe('tajnistvo@dom-a.example')
            ->office_hours->toBe('Pon–pet 8–16')
            ->map_embed_url->toBe('https://www.google.com/maps/embed?pb=!1m18')
            ->contact_note->toBe('Ravnatelj: 01 888 8888')
            ->social_links->toMatchArray(['facebook' => 'https://www.facebook.com/doma', 'youtube' => 'https://youtu.be/abc']);

        expect($this->schoolB->fresh()->city)->toBe('Split');
    });

    it('cannot change name, slug, site URL, theme, old site URL or the active flag by injecting them', function () {
        $before = adminSettingsSnapshot($this->schoolA);

        Livewire::test(SchoolSettings::class)
            ->set('data.name', 'Podmetnuti naziv')
            ->set('data.slug', 'podmetnuti-slug')
            ->set('data.site_url', 'https://zlo.example')
            ->set('data.theme', SchoolTheme::Red->value)
            ->set('data.old_site_url', 'https://staro.example')
            ->set('data.is_active', false)
            ->set('data.city', 'Rijeka')
            ->call('save')
            ->assertHasNoFormErrors();

        $school = $this->schoolA->fresh();

        expect($school->only(School::ADMIN_SETTINGS))->toBe($before)
            ->and($school->city)->toBe('Rijeka');
    });

    it('cannot inject an id or school_id to change another school', function () {
        Livewire::test(SchoolSettings::class)
            ->set('data.id', $this->schoolB->id)
            ->set('data.school_id', $this->schoolB->id)
            ->set('data.city', 'Rijeka')
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->schoolA->fresh()->city)->toBe('Rijeka')
            ->and($this->schoolB->fresh()->city)->toBe('Split');
    });

    it('cannot point the page at another school', function () {
        Livewire::test(SchoolSettings::class)
            ->set('schoolId', $this->schoolB->id);
    })->throws(CannotUpdateLockedPropertyException::class);

    it('cannot open the settings once deactivated', function () {
        $this->editor->forceFill(['is_active' => false])->save();

        $this->get('/admin/school-settings')->assertForbidden();
    });

    it('uploads and replaces the logo of their own school only', function () {
        $logoB = addLogo($this->schoolB);

        Livewire::test(SchoolSettings::class)
            ->fillForm(['logo' => [UploadedFile::fake()->image('prvi.png', 600, 300)]])
            ->call('save')
            ->assertHasNoFormErrors();

        $first = $this->schoolA->fresh()->getFirstMedia('logo');

        expect($first->name)->toBe('prvi')
            ->and($first->hasGeneratedConversion('small'))->toBeTrue()
            ->and($first->hasGeneratedConversion('large'))->toBeTrue();

        Livewire::test(SchoolSettings::class)
            ->fillForm(['logo' => [UploadedFile::fake()->image('drugi.jpg', 600, 300)]])
            ->call('save')
            ->assertHasNoFormErrors();

        $logos = $this->schoolA->fresh()->getMedia('logo');

        expect($logos)->toHaveCount(1)
            ->and($logos->first()->name)->toBe('drugi')
            ->and(Media::find($first->id))->toBeNull()
            ->and($this->schoolB->fresh()->getFirstMedia('logo')->id)->toBe($logoB->id);
    });

    it('ignores another school\'s logo injected into the logo state', function () {
        $logoA = addLogo($this->schoolA, 'logo-a.png');
        $logoB = addLogo($this->schoolB, 'logo-b.png');

        Livewire::test(SchoolSettings::class)
            ->set('data.logo', [$logoB->uuid => $logoB->uuid])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($logoB->fresh())
            ->not->toBeNull()
            ->model_id->toBe($this->schoolB->id)
            ->collection_name->toBe('logo');

        Storage::disk('public')->assertExists($logoB->getPathRelativeToRoot());

        // A's own logo was dropped from the state, so it is removed.
        expect(Media::find($logoA->id))->toBeNull()
            ->and($this->schoolA->fresh()->getMedia('logo'))->toHaveCount(0);
    });

    it('rejects an SVG or a non-image as the logo', function (string $name, string $kind) {
        Livewire::test(SchoolSettings::class)
            ->fillForm(['logo' => [documentUpload($name, $kind)]])
            ->call('save')
            ->assertHasFormErrors(['logo']);

        expect($this->schoolA->fresh()->getMedia('logo'))->toHaveCount(0);
    })->with([
        'svg' => ['logo.svg', 'svg'],
        'svg named png' => ['logo.png', 'svg'],
        'html named png' => ['logo.png', 'html'],
        'pdf' => ['logo.pdf', 'pdf'],
    ]);

    it('stores the logo resized and without metadata', function () {
        $media = $this->schoolA->addMedia(jpegWithExif(3000, 1500))->toMediaCollection('logo');
        $stored = Storage::disk('public')->path($media->getPathRelativeToRoot());

        expect(getimagesize($stored)[0])->toBe(2000)
            ->and(@exif_read_data($stored) ?: [])->not->toHaveKey('GPSLatitude');
    });

    it('logs the change in the activity log for their school', function () {
        Livewire::test(SchoolSettings::class)
            ->fillForm(['phone' => '01 555 5555'])
            ->call('save')
            ->assertHasNoFormErrors();

        $activity = Activity::query()->where('subject_type', $this->schoolA->getMorphClass())->latest('id')->first();

        expect($activity)
            ->event->toBe('updated')
            ->school_id->toBe($this->schoolA->id)
            ->causer_id->toBe($this->editor->id)
            ->and($activity->properties['attributes'])->toBe(['phone' => '01 555 5555']);
    });
});

describe('super admin', function () {
    beforeEach(fn () => $this->admin = actingAsSuperAdmin($this->schoolA));

    it('changes every setting of the school selected in the switcher', function () {
        Livewire::test(SchoolSettings::class)
            ->assertFormFieldExists('name')
            ->fillForm([
                'name' => 'Dom A novi',
                'slug' => 'dom-a-novi',
                'site_url' => 'https://novi.dom-a.example',
                'theme' => SchoolTheme::Green->value,
                'old_site_url' => 'https://staro.dom-a.example',
                'is_active' => false,
                'city' => 'Velika Gorica',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->schoolA->fresh())
            ->name->toBe('Dom A novi')
            ->slug->toBe('dom-a-novi')
            ->site_url->toBe('https://novi.dom-a.example')
            ->theme->toBe(SchoolTheme::Green)
            ->old_site_url->toBe('https://staro.dom-a.example')
            ->is_active->toBeFalse()
            ->city->toBe('Velika Gorica');

        expect($this->schoolB->fresh()->name)->toBe('Dom B');
    });

    it('edits whichever school the switcher is on', function () {
        app(CurrentSchool::class)->select($this->schoolB);

        Livewire::test(SchoolSettings::class)
            ->assertSet('schoolId', $this->schoolB->id)
            ->fillForm(['city' => 'Makarska'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->schoolB->fresh()->city)->toBe('Makarska')
            ->and($this->schoolA->fresh()->city)->toBe('Zagreb');
    });

    it('saves nothing when another school was selected after the page was opened', function () {
        $page = Livewire::test(SchoolSettings::class)
            ->fillForm(['city' => 'Krivi grad', 'name' => 'Krivi naziv']);

        // e.g. switched in another browser tab
        app(CurrentSchool::class)->select($this->schoolB);

        $page->call('save')->assertNotified('Odabran je drugi dom.');

        expect($this->schoolA->fresh()->city)->toBe('Zagreb')
            ->and($this->schoolB->fresh()->city)->toBe('Split')
            ->and($this->schoolB->fresh()->name)->toBe('Dom B');
    });

    it('requires a unique slug', function () {
        Livewire::test(SchoolSettings::class)
            ->fillForm(['slug' => $this->schoolB->slug])
            ->call('save')
            ->assertHasFormErrors(['slug' => 'unique']);
    });
});

describe('validation', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->editor($this->schoolA)->create());
    });

    it('accepts Google Maps and OpenStreetMap embed URLs', function (string $url) {
        Livewire::test(SchoolSettings::class)
            ->fillForm(['map_embed_url' => $url])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->schoolA->fresh()->map_embed_url)->toBe($url);
    })->with([
        'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2781',
        'https://www.google.com/maps/embed/v1/place?key=abc&q=Zagreb',
        'https://www.openstreetmap.org/export/embed.html?bbox=15.9%2C45.8%2C16.0%2C45.9&layer=mapnik',
        'https://openstreetmap.org/export/embed.html?bbox=15.9%2C45.8%2C16.0%2C45.9',
    ]);

    it('keeps only the src of a pasted iframe snippet', function (string $snippet, string $url) {
        Livewire::test(SchoolSettings::class)
            ->fillForm(['map_embed_url' => $snippet])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->schoolA->fresh()->map_embed_url)->toBe($url);
    })->with([
        'google' => [
            '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>',
            'https://www.google.com/maps/embed?pb=!1m18!1m12',
        ],
        'openstreetmap' => [
            '<iframe width="425" height="350" src="https://www.openstreetmap.org/export/embed.html?bbox=15.9%2C45.8&amp;layer=mapnik" style="border: 1px solid black"></iframe><br/><small><a href="https://www.openstreetmap.org/#map=15/45.8/15.9">Prikaz veće karte</a></small>',
            'https://www.openstreetmap.org/export/embed.html?bbox=15.9%2C45.8&layer=mapnik',
        ],
    ]);

    it('rejects other map URLs', function (string $value) {
        Livewire::test(SchoolSettings::class)
            ->fillForm(['map_embed_url' => $value])
            ->call('save')
            ->assertHasFormErrors(['map_embed_url']);

        expect($this->schoolA->fresh()->map_embed_url)->toBeNull();
    })->with([
        'http' => 'http://www.google.com/maps/embed?pb=1',
        'not an embed' => 'https://www.google.com/maps/place/Zagreb',
        'other host' => 'https://evil.example/maps/embed?pb=1',
        'lookalike host' => 'https://www.google.com.evil.example/maps/embed?pb=1',
        'subdomain trick' => 'https://maps.evil.example/export/embed.html',
        'credentials' => 'https://user:pass@www.google.com/maps/embed?pb=1',
        'port' => 'https://www.google.com:8443/maps/embed?pb=1',
        'path prefix' => 'https://www.google.com/maps/embedded?pb=1',
        'javascript' => 'javascript:alert(1)//https://www.google.com/maps/embed',
        'data' => 'data:text/html,<script>alert(1)</script>',
        'protocol relative' => '//www.google.com/maps/embed?pb=1',
        'quote breakout' => 'https://www.google.com/maps/embed?pb=1" onload="alert(1)',
        'iframe to other host' => '<iframe src="https://evil.example/embed"></iframe>',
        'too long' => 'https://www.google.com/maps/embed?pb='.str_repeat('a', 2048),
    ]);

    it('accepts social links only on the matching https site', function (string $network, string $url, bool $valid) {
        $page = Livewire::test(SchoolSettings::class)
            ->fillForm(["social_links.{$network}" => $url])
            ->call('save');

        $valid
            ? $page->assertHasNoFormErrors()
            : $page->assertHasFormErrors(["social_links.{$network}"]);
    })->with([
        ['facebook', 'https://www.facebook.com/dom', true],
        ['facebook', 'https://m.facebook.com/dom', true],
        ['facebook', 'https://fb.com/dom', true],
        ['instagram', 'https://www.instagram.com/dom', true],
        ['youtube', 'https://www.youtube.com/@dom', true],
        ['youtube', 'https://youtu.be/abc', true],
        ['facebook', 'http://www.facebook.com/dom', false],
        ['facebook', 'https://www.instagram.com/dom', false],
        ['facebook', 'https://facebook.com.evil.example/dom', false],
        ['facebook', 'https://notfacebook.com/dom', false],
        ['instagram', 'javascript:alert(1)', false],
        ['youtube', 'https://evil.example/?u=youtube.com', false],
    ]);
});
