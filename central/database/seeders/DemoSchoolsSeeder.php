<?php

namespace Database\Seeders;

use App\Enums\SchoolTheme;
use App\Enums\UserRole;
use App\Models\DocumentCategory;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Two demo schools with one editor each, so tenant isolation can be checked
 * by hand: all settings filled in and a generated logo. Their content comes
 * from DemoContentSeeder. Local development only.
 */
class DemoSchoolsSeeder extends Seeder
{
    public function run(): void
    {
        $schools = [
            [
                'school' => [
                    'name' => 'Učenički dom Ivana Gundulića',
                    'slug' => 'os-ivana-gundulica',
                    'site_url' => 'http://localhost:8081',
                    'theme' => SchoolTheme::Blue,
                    'address' => 'Gundulićeva 1',
                    'postal_code' => '10000',
                    'city' => 'Zagreb',
                    'phone' => '01 111 1111',
                    'email' => 'ured@os-igundulica.example',
                    'office_hours' => "Tajništvo: ponedjeljak – petak, 8:00 – 15:00\nRačunovodstvo: ponedjeljak – četvrtak, 9:00 – 13:00",
                    'map_embed_url' => 'https://www.openstreetmap.org/export/embed.html?bbox=15.9700%2C45.8080%2C15.9940%2C45.8200&layer=mapnik&marker=45.8140%2C15.9820',
                    'contact_note' => "Ravnatelj: Ivan Horvat, ravnatelj@os-igundulica.example\nPedagoginja: Ana Kovač, 01 111 1112\nDežurni odgajatelj (noću): 091 111 1113",
                    'social_links' => [
                        'facebook' => 'https://www.facebook.com/ucenicki.dom.gundulic',
                        'instagram' => 'https://www.instagram.com/ucenicki.dom.gundulic',
                        'youtube' => null,
                    ],
                    'old_site_url' => 'https://stari.os-ivana-gundulica.example',
                ],
                'editor' => ['name' => 'Urednik Gundulić', 'email' => 'urednik@os-ivana-gundulica.example'],
            ],
            [
                'school' => [
                    'name' => 'Drugi učenički dom',
                    'slug' => 'os-druga-skola',
                    'site_url' => 'http://localhost:8082',
                    'theme' => SchoolTheme::Green,
                    'address' => 'Školska 2',
                    'postal_code' => '21000',
                    'city' => 'Split',
                    'phone' => '021 222 222',
                    'email' => 'ured@os-druga-skola.example',
                    'office_hours' => 'Uredovno vrijeme: ponedjeljak – petak, 7:30 – 14:30',
                    'map_embed_url' => 'https://www.openstreetmap.org/export/embed.html?bbox=16.4300%2C43.5030%2C16.4500%2C43.5130&layer=mapnik&marker=43.5081%2C16.4402',
                    'contact_note' => null,
                    'social_links' => [
                        'facebook' => null,
                        'instagram' => null,
                        'youtube' => 'https://www.youtube.com/@drugiucenickidom',
                    ],
                    'old_site_url' => null,
                ],
                'editor' => ['name' => 'Urednik Druga', 'email' => 'urednik@os-druga-skola.example'],
            ],
        ];

        $password = config('cms.demo.editor_password');

        foreach ($schools as $data) {
            $school = School::updateOrCreate(['slug' => $data['school']['slug']], $data['school']);

            // New schools get them on create; this adds any missing to existing ones.
            DocumentCategory::createDefaultsFor($school);

            if ($school->getFirstMedia('logo') === null) {
                $school->addMedia(DemoImage::logo($school->theme))
                    ->usingName('logo')
                    ->usingFileName(Str::random(40).'.png')
                    ->toMediaCollection('logo');
            }

            $editor = User::firstOrNew(['email' => $data['editor']['email']]);
            $editor->name = $data['editor']['name'];
            $editor->password = $password;
            $editor->role = UserRole::Editor;
            $editor->school_id = $school->id;
            $editor->is_active = true;
            $editor->email_verified_at ??= now();
            $editor->save();
        }

        $this->command?->info("Demo editors: {$schools[0]['editor']['email']}, {$schools[1]['editor']['email']} (password from DEMO_EDITOR_PASSWORD).");
    }
}
