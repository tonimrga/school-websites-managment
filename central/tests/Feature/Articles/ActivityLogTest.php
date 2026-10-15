<?php

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Activity;
use App\Models\Article;
use App\Models\School;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
| Creating, changing, publishing, unpublishing, deleting and restoring an
| article is logged with the user and school. Only the super admin can read
| the log.
*/

beforeEach(function () {
    Storage::fake('public');

    $this->school = School::factory()->create(['name' => 'Škola A']);
    $this->editor = User::factory()->editor($this->school)->create(['name' => 'Urednica A']);

    $this->actingAs($this->editor);
});

function lastActivity(): Activity
{
    return Activity::query()->latest('id')->firstOrFail();
}

it('logs the whole life cycle of an article with user and school', function () {
    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => 'Dan škole', 'body' => '<p>Tekst.</p>'])
        ->call('create');
    $article = Article::sole();
    expect(lastActivity())->event->toBe('created');

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])
        ->fillForm(['title' => 'Dan škole 2026'])
        ->call('save');
    expect(lastActivity())
        ->event->toBe('updated')
        ->and(lastActivity()->properties['old']['title'])->toBe('Dan škole')
        ->and(lastActivity()->properties['attributes']['title'])->toBe('Dan škole 2026');

    $article->addMedia(UploadedFile::fake()->image('n.jpg', 100, 100))->toMediaCollection('cover');
    Livewire::test(ListArticles::class)->callAction(TestAction::make('publish')->table($article));
    expect(lastActivity())->event->toBe('published')
        ->and(lastActivity()->properties['attributes']['status'])->toBe('published');

    Livewire::test(ListArticles::class)->callAction(TestAction::make('unpublish')->table($article));
    expect(lastActivity())->event->toBe('unpublished');

    Livewire::test(ListArticles::class)->callAction(TestAction::make(DeleteAction::class)->table($article));
    expect(lastActivity())->event->toBe('deleted');

    Livewire::test(ListArticles::class)
        ->filterTable(TrashedFilter::class, true)
        ->callAction(TestAction::make(RestoreAction::class)->table($article));
    expect(lastActivity())->event->toBe('restored');

    // Creating the school in beforeEach is logged too; only the article matters here.
    $activities = Activity::query()->where('subject_type', $article->getMorphClass())->orderBy('id')->get();

    expect($activities->pluck('event')->all())
        ->toBe(['created', 'updated', 'published', 'unpublished', 'deleted', 'restored'])
        ->and($activities->pluck('school_id')->unique()->all())->toBe([$this->school->id])
        ->and($activities->pluck('causer_id')->unique()->all())->toBe([$this->editor->id])
        ->and($activities->pluck('subject_id')->unique()->all())->toBe([$article->id]);
});

it('does not log a save without changes', function () {
    $article = createArticle($this->school);
    $count = Activity::count();

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])->call('save');

    expect(Activity::count())->toBe($count);
});

it('hides the activity log from editors', function () {
    $this->get('/admin/activity-log')->assertForbidden();
});

it('shows the super admin the log of all schools, filterable by school and user', function () {
    $otherSchool = School::factory()->create(['name' => 'Škola B']);
    $otherEditor = User::factory()->editor($otherSchool)->create(['name' => 'Urednik B']);

    $this->actingAs($this->editor);
    $articleA = createArticle($this->school, ['title' => 'Vijest A']);
    $articleA->update(['title' => 'Vijest A izmijenjena']);
    $activityA = lastActivity();

    $this->actingAs($otherEditor);
    $articleB = createArticle($otherSchool, ['title' => 'Vijest B']);
    $articleB->update(['title' => 'Vijest B izmijenjena']);
    $activityB = lastActivity();

    // The switcher points at school A, but the log covers every school.
    actingAsSuperAdmin($this->school);

    $this->get('/admin/activity-log')
        ->assertOk()
        ->assertSee('Vijest A izmijenjena')
        ->assertSee('Vijest B izmijenjena');

    Livewire::test(ListActivities::class)
        ->assertCanSeeTableRecords([$activityA, $activityB])
        ->filterTable('school', $otherSchool->id)
        ->assertCanSeeTableRecords([$activityB])
        ->assertCanNotSeeTableRecords([$activityA])
        ->resetTableFilters()
        ->filterTable('causer', $this->editor->id)
        ->assertCanSeeTableRecords([$activityA])
        ->assertCanNotSeeTableRecords([$activityB]);
});

it('is read-only even for the super admin', function () {
    $admin = actingAsSuperAdmin($this->school);
    $activity = Activity::create(['description' => 'test', 'log_name' => 'default']);

    expect($admin->can('create', Activity::class))->toBeFalse()
        ->and($admin->can('update', $activity))->toBeFalse()
        ->and($admin->can('delete', $activity))->toBeFalse();
});
