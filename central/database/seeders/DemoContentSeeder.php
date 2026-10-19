<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Article;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\School;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Demo articles and documents for the two demo schools (SPEC section 9):
 * published, featured, draft, scheduled and trashed articles with covers and
 * galleries, and documents across the default categories.
 *
 * A school that already has any articles or documents is skipped, so running
 * the seeder again never duplicates or deletes anything. Content is created
 * as the school's editor, exactly as through the dashboard.
 */
class DemoContentSeeder extends Seeder
{
    /**
     * Per demo school: how many live articles (the first one has more than a
     * page of news) and which of them are featured.
     *
     * @var array<string, array{live: int, offset: int, featured: list<int>}>
     */
    public const SCHOOLS = [
        'os-ivana-gundulica' => ['live' => 14, 'offset' => 0, 'featured' => [1, 2, 4]],
        'os-druga-skola' => ['live' => 10, 'offset' => 6, 'featured' => [0]],
    ];

    /**
     * Articles that can be published: title and lead paragraph.
     *
     * @var list<array{0: string, 1: string}>
     */
    protected const ARTICLES = [
        ['Dobrodošlica novim učenicima', 'Prvi tjedan nove školske godine proveli smo upoznavajući nove učenike, njihove sobe, odgajatelje i kućni red doma.'],
        ['Dan otvorenih vrata', 'Pozivamo roditelje i buduće učenike da u subotu posjete dom, razgledaju sobe i zajedničke prostore te upoznaju odgajatelje.'],
        ['Uspjeh na državnom natjecanju iz matematike', 'Naš učenik trećeg razreda osvojio je drugo mjesto na državnom natjecanju iz matematike. Čestitamo!'],
        ['Jesenski izlet na Plitvička jezera', 'Šezdeset učenika provelo je subotu u Nacionalnom parku Plitvička jezera uz stručno vodstvo i puno sunca.'],
        ['Novi jelovnik u domskoj kuhinji', 'Od ovog mjeseca u domskoj kuhinji pripremamo nova jela, s više sezonskog voća i povrća te vegetarijanskom ponudom.'],
        ['Radionica o sigurnosti na internetu', 'Policijski službenici održali su za učenike radionicu o zaštiti osobnih podataka i sigurnom ponašanju na društvenim mrežama.'],
        ['Domski turnir u malom nogometu', 'Ekipe sa sva četiri kata natjecale su se za prijelazni pehar u tradicionalnom domskom turniru u malom nogometu.'],
        ['Humanitarna akcija prikupljanja hrane', 'U suradnji s Crvenim križem prikupili smo više od 300 kilograma hrane i higijenskih potrepština za obitelji u potrebi.'],
        ['Obnovljena čitaonica', 'Učenici od ovog tjedna uče u obnovljenoj čitaonici s novim stolovima, boljom rasvjetom i šest novih računala.'],
        ['Božićni koncert učenika', 'Domski zbor i glazbena skupina priredili su tradicionalni božićni koncert za učenike, roditelje i djelatnike doma.'],
        ['Upisi u učenički dom za novu školsku godinu', 'Objavljujemo uvjete, rokove i potrebnu dokumentaciju za prijavu za smještaj u domu u novoj školskoj godini.'],
        ['Predavanje o zdravim navikama učenja', 'Psihologinja doma održala je predavanje o planiranju učenja, odmoru i pripremi za ispite.'],
        ['Posjet Tehničkom muzeju', 'Učenici prvih razreda posjetili su Tehnički muzej Nikola Tesla i isprobali brojne interaktivne postave.'],
        ['Volonteri doma uredili park', 'Dvadeset učenika volontera provelo je subotnje jutro uređujući park u susjedstvu doma.'],
        ['Literarni natječaj „Moj dom”', 'Proglašeni su pobjednici literarnog natječaja na kojem su učenici pisali o životu u domu.'],
        ['Rad doma tijekom zimskih praznika', 'Tijekom zimskih praznika dom radi u skraćenom režimu. Donosimo raspored dežurstava i obroka.'],
    ];

    /**
     * Two drafts, one scheduled and one trashed article per school.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected const OTHER_ARTICLES = [
        'draft' => ['Stolnoteniski turnir (nacrt)', 'Prijave za proljetni stolnoteniski turnir otvorene su do kraja mjeseca.'],
        'draft2' => ['Plan aktivnosti za proljeće', 'Pripremamo raspored izleta, radionica i sportskih aktivnosti za proljetne mjesece.'],
        'scheduled' => ['Maškare u domu', 'Najmaštovitije maske nagradit ćemo na tradicionalnoj maškaranoj zabavi u domu.'],
        'trashed' => ['Promjena termina roditeljskog sastanka', 'Roditeljski sastanak održat će se tjedan dana kasnije nego što je najavljeno.'],
    ];

    /**
     * Shared paragraphs that make the article bodies longer.
     *
     * @var list<string>
     */
    protected const PARAGRAPHS = [
        'Zahvaljujemo svim odgajateljima i djelatnicima doma koji su sudjelovali u pripremi i pomogli da sve protekne u najboljem redu.',
        'Učenici su pokazali puno truda, znatiželje i zajedništva, a dojmove su podijelili i na zajedničkom sastanku doma.',
        'Sve dodatne informacije roditelji i učenici mogu dobiti od odgajatelja skupine ili u tajništvu doma.',
        'Nastavljamo s ovakvim aktivnostima i tijekom ostatka školske godine, a o svemu ćemo vas redovito obavještavati.',
    ];

    /**
     * Gallery sizes, by article index.
     *
     * @var list<int>
     */
    protected const GALLERY_SIZES = [4, 6, 3, 0, 5, 2, 0, 4, 3, 6, 2, 0, 5, 3, 4, 2];

    /**
     * @var list<string>
     */
    protected const CAPTIONS = [
        'Zajednička fotografija sudionika',
        'Odgajatelji s učenicima',
        'Trenutak s događanja',
    ];

    /**
     * Documents: category slug, title, description, days ago for the
     * document date (null for none), published.
     *
     * The "ostalo" category stays empty and "javna-nabava" has only an
     * unpublished document, so the API lists neither.
     *
     * @var list<array{0: string, 1: string, 2: ?string, 3: ?int, 4: bool}>
     */
    protected const DOCUMENTS = [
        ['natjecaji', 'Natječaj za radno mjesto odgajatelja', 'Rok za prijavu je osam dana od dana objave natječaja.', 10, true],
        ['natjecaji', 'Odluka o izboru kandidata za radno mjesto kuhara', null, 40, true],
        ['natjecaji', 'Natječaj za radno mjesto spremača', 'Puno radno vrijeme, na neodređeno.', 60, true],
        ['natjecaji', 'Obavijest o rezultatima natječaja za tajnika', null, 95, true],
        ['upisi', 'Uvjeti upisa u učenički dom', 'Tko se može prijaviti, rokovi i način bodovanja.', null, true],
        ['upisi', 'Prijavnica za smještaj u domu', 'Ispunjenu prijavnicu predajte u tajništvu doma ili pošaljite e-poštom.', null, true],
        ['upisi', 'Popis potrebne dokumentacije', null, null, true],
        ['upisi', 'Rang-lista primljenih učenika', null, 120, true],
        ['godisnji-plan-i-program-rada', 'Godišnji plan i program rada za školsku godinu 2026./2027.', null, 30, true],
        ['godisnji-plan-i-program-rada', 'Izvješće o realizaciji godišnjeg plana 2025./2026.', null, 100, true],
        ['godisnji-plan-i-program-rada', 'Kurikulum doma', 'Odgojno-obrazovni programi i izvannastavne aktivnosti.', 30, true],
        ['statut-i-pravilnici', 'Statut učeničkog doma', null, 400, true],
        ['statut-i-pravilnici', 'Kućni red', 'Pravila boravka u domu za učenike i posjetitelje.', 380, true],
        ['statut-i-pravilnici', 'Pravilnik o zaštiti osobnih podataka', null, 300, true],
        ['statut-i-pravilnici', 'Pravilnik o pravima i obvezama učenika', 'U izradi.', null, false],
        ['financijski-dokumenti', 'Financijski plan za 2027. godinu', null, 20, true],
        ['financijski-dokumenti', 'Izvještaj o izvršenju financijskog plana za 2025.', null, 200, true],
        ['financijski-dokumenti', 'Polugodišnji financijski izvještaj za 2026.', null, 80, true],
        ['javna-nabava', 'Plan nabave za 2026. godinu', null, 250, false],
        ['pravo-na-pristup-informacijama', 'Zahtjev za pristup informacijama', 'Obrazac zahtjeva za pristup informacijama.', null, true],
    ];

    /**
     * Upper limit for gallery sizes; tests lower it to keep seeding fast.
     */
    public static ?int $galleryLimit = null;

    public function run(): void
    {
        foreach (self::SCHOOLS as $slug => $config) {
            $school = School::where('slug', $slug)->first();

            if ($school === null || $this->hasContent($school)) {
                $this->command?->line("Demo content skipped for {$slug} (missing or not empty).");

                continue;
            }

            $editor = User::where('school_id', $school->id)->where('role', UserRole::Editor)->first();

            if ($editor === null) {
                $this->command?->line("Demo content skipped for {$slug} (no editor).");

                continue;
            }

            // Created as the school's editor, as through the dashboard.
            Auth::setUser($editor);

            try {
                $this->seedArticles($school, $config);
                $this->seedDocuments($school);
            } finally {
                Auth::guard()->forgetUser();
            }

            $this->command?->info("Demo content created for {$slug}.");
        }
    }

    protected function hasContent(School $school): bool
    {
        return Article::withoutGlobalScopes()->where('school_id', $school->id)->exists()
            || Document::withoutGlobalScopes()->where('school_id', $school->id)->exists();
    }

    /**
     * @param  array{live: int, offset: int, featured: list<int>}  $config
     */
    protected function seedArticles(School $school, array $config): void
    {
        $now = CarbonImmutable::now();

        // Oldest first, so the newest article also has the highest id.
        for ($position = $config['live'] - 1; $position >= 0; $position--) {
            $index = ($config['offset'] + $position) % count(self::ARTICLES);
            [$title, $lead] = self::ARTICLES[$index];
            $publishedAt = $now->subDays(3 + $position * 11)->setTime(9 + $position % 6, 0);

            $article = $this->createArticle($school, $title, $lead, $index, in_array($position, $config['featured'], true));
            $this->addGallery($article, $school, $index);
            $article->publish($publishedAt);
            $this->backdate($article, $publishedAt);
        }

        foreach (['draft', 'draft2'] as $number => $key) {
            [$title, $lead] = self::OTHER_ARTICLES[$key];
            $this->createArticle($school, $title, $lead, 20 + $number, false, withCover: $number === 0);
        }

        [$title, $lead] = self::OTHER_ARTICLES['scheduled'];
        $this->createArticle($school, $title, $lead, 22, false)->publish($now->addWeek()->setTime(8, 0));

        [$title, $lead] = self::OTHER_ARTICLES['trashed'];
        $trashed = $this->createArticle($school, $title, $lead, 23, false);
        $trashed->publish($now->subDays(20));
        $trashed->delete();
    }

    protected function createArticle(School $school, string $title, string $lead, int $index, bool $featured, bool $withCover = true): Article
    {
        $article = new Article([
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => $lead,
            'body' => $this->body($lead, $index),
            'is_featured' => $featured,
        ]);
        $article->save();

        if ($withCover) {
            $article->addMedia(DemoImage::photo($school->theme, $this->seed($school, $index, 0), wide: $index % 3 === 0))
                ->usingName(Str::slug($title))
                ->usingFileName(Str::random(40).'.jpg')
                ->toMediaCollection('cover');
        }

        return $article;
    }

    protected function addGallery(Article $article, School $school, int $index): void
    {
        $size = min(self::GALLERY_SIZES[$index % count(self::GALLERY_SIZES)], self::$galleryLimit ?? PHP_INT_MAX);

        for ($number = 1; $number <= $size; $number++) {
            $caption = $number % 2 === 1 ? self::CAPTIONS[($index + $number) % count(self::CAPTIONS)] : null;

            $article->addMedia(DemoImage::photo($school->theme, $this->seed($school, $index, $number)))
                ->usingName('galerija-'.$number)
                ->usingFileName(Str::random(40).'.jpg')
                ->withCustomProperties($caption === null ? [] : ['caption' => $caption])
                ->toMediaCollection('gallery');
        }
    }

    /**
     * Rich text with the tags the sanitizer allows; some articles get a
     * list, a quote, a link or a table.
     */
    protected function body(string $lead, int $index): string
    {
        $paragraph = fn (int $offset): string => '<p>'.self::PARAGRAPHS[($index + $offset) % count(self::PARAGRAPHS)].'</p>';

        $extra = match (true) {
            $index === 10 => '<h2>Rokovi</h2><table><thead><tr><th>Korak</th><th>Rok</th></tr></thead><tbody>'
                .'<tr><td>Predaja prijava</td><td>do 10. srpnja</td></tr>'
                .'<tr><td>Objava rang-liste</td><td>15. srpnja</td></tr>'
                .'<tr><td>Potpisivanje ugovora</td><td>do 25. kolovoza</td></tr></tbody></table>',
            $index % 4 === 0 => '<h2>Program</h2><ul><li>okupljanje i dobrodošlica</li><li>zajednički ručak</li><li>druženje i radionice</li></ul>',
            $index % 4 === 1 => '<blockquote><p>Ovakva iskustva ostaju u sjećanju cijeli život.</p></blockquote>',
            $index % 4 === 2 => '<p>Više informacija pročitajte na <a href="https://mzo.gov.hr">stranicama Ministarstva znanosti, obrazovanja i mladih</a>.</p>',
            default => '<h3>Zahvala</h3><p>Hvala <strong>svim sudionicima</strong> i <em>organizatorima</em>.</p>',
        };

        return '<p>'.$lead.'</p>'.$paragraph(0).$extra.$paragraph(1);
    }

    /**
     * Demo articles look as if they had been written on their publish date.
     */
    protected function backdate(Article $article, CarbonImmutable $date): void
    {
        $article->timestamps = false;
        $article->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();
        $article->timestamps = true;
    }

    protected function seedDocuments(School $school): void
    {
        $categories = DocumentCategory::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->pluck('id', 'slug');

        foreach (self::DOCUMENTS as [$categorySlug, $title, $description, $daysAgo, $published]) {
            $categoryId = $categories[$categorySlug] ?? null;

            if ($categoryId === null) {
                continue;
            }

            $date = $daysAgo === null ? null : CarbonImmutable::now()->subDays($daysAgo)->toDateString();

            $document = Document::create([
                'document_category_id' => $categoryId,
                'title' => $title,
                'description' => $description,
                'document_date' => $date,
                'is_published' => $published,
            ]);

            $lines = array_filter([
                $school->name,
                $school->address.', '.$school->postal_code.' '.$school->city,
                '',
                $description,
                $date === null ? null : 'Datum: '.CarbonImmutable::parse($date)->format('d. m. Y.'),
                'Ovo je demo dokument.',
            ], fn (?string $line): bool => $line !== null);

            $document->addMedia(DemoPdf::make($title, array_values($lines)))
                ->usingName($title)
                ->usingFileName(Str::random(40).'.pdf')
                ->toMediaCollection('file');
        }
    }

    /**
     * A stable seed per school, article and image, so pictures differ.
     */
    protected function seed(School $school, int $index, int $number): int
    {
        return crc32($school->slug.'-'.$index.'-'.$number);
    }
}
