<?php

namespace Database\Seeders;

use App\Enums\SchoolTheme;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Two demo schools with one editor each, so tenant isolation can be checked
 * by hand. Local development only; the full demo content comes later.
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
                ],
                'editor' => ['name' => 'Urednik Druga', 'email' => 'urednik@os-druga-skola.example'],
            ],
        ];

        $password = config('cms.demo.editor_password');

        foreach ($schools as $data) {
            $school = School::updateOrCreate(['slug' => $data['school']['slug']], $data['school']);

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
