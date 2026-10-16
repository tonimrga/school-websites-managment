<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ArticleResource;
use App\Http\Resources\Api\ArticleSummaryResource;
use App\Http\Resources\Api\DocumentCategoryResource;
use App\Http\Resources\Api\SchoolResource;
use App\Models\Article;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The public, read-only API of one school's site. Each action returns
 * everything one page needs.
 *
 * Tenant scoping does not apply here: every query filters by the school from
 * the URL explicitly, and only visible content is returned (live articles,
 * published documents with a file, of an active school).
 */
class SchoolApiController extends Controller
{
    public const HOME_FEATURED = 3;

    public const HOME_LATEST = 6;

    public const RECENT = 3;

    public function school(string $slug): JsonResponse
    {
        return $this->respond([
            'school' => new SchoolResource($this->findSchool($slug)),
        ]);
    }

    public function home(string $slug): JsonResponse
    {
        $school = $this->findSchool($slug);

        $featured = $this->liveArticles($school)
            ->where('is_featured', true)
            ->limit(self::HOME_FEATURED)
            ->get();

        $latest = $this->liveArticles($school)
            ->whereKeyNot($featured->modelKeys())
            ->limit(self::HOME_LATEST)
            ->get();

        return $this->respond([
            'school' => new SchoolResource($school),
            'featured' => ArticleSummaryResource::collection($featured),
            'latest' => ArticleSummaryResource::collection($latest),
        ]);
    }

    /**
     * Featured articles first, then newest. A page that does not exist is a
     * 404, except an empty first page.
     */
    public function articles(Request $request, string $slug): JsonResponse
    {
        $school = $this->findSchool($slug);

        $page = $request->query('page', '1');
        abort_unless(is_string($page) && ctype_digit($page) && (int) $page >= 1, 404);
        $page = (int) $page;

        $paginator = $this->liveArticles($school)
            ->reorder()
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate((int) config('cms.api.articles_per_page'), page: $page);

        abort_if($page > max(1, $paginator->lastPage()), 404);

        return $this->respond([
            'school' => new SchoolResource($school),
            'data' => ArticleSummaryResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function article(string $slug, string $articleSlug): JsonResponse
    {
        $school = $this->findSchool($slug);

        $article = $this->liveArticles($school, withGallery: true)
            ->where('slug', $articleSlug)
            ->first();

        abort_if($article === null, 404);

        $recent = $this->liveArticles($school)
            ->whereKeyNot($article->getKey())
            ->limit(self::RECENT)
            ->get();

        return $this->respond([
            'school' => new SchoolResource($school),
            'article' => new ArticleResource($article),
            'recent' => ArticleSummaryResource::collection($recent),
        ]);
    }

    /**
     * Categories that have published documents, in their manual order. With
     * ?category=, only that category's documents are returned; the category
     * must exist and is listed even when it is empty.
     */
    public function documents(Request $request, string $slug): JsonResponse
    {
        $school = $this->findSchool($slug);

        $categories = DocumentCategory::withoutGlobalScope(SchoolScope::class)
            ->where('school_id', $school->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $selected = null;

        if ($request->filled('category')) {
            $categorySlug = $request->query('category');
            $selected = is_string($categorySlug) ? $categories->firstWhere('slug', $categorySlug) : null;

            abort_if($selected === null, 404);
        }

        $documents = Document::withoutGlobalScope(SchoolScope::class)
            ->where('school_id', $school->getKey())
            ->published()
            ->whereHas('media', fn (Builder $media) => $media->where('collection_name', 'file'))
            ->with('media')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('document_category_id');

        $listed = $categories
            ->filter(fn (DocumentCategory $category): bool => $documents->has($category->getKey()) || $category->is($selected))
            ->each(fn (DocumentCategory $category) => $category->setRelation(
                'documents',
                $selected === null || $category->is($selected) ? $documents->get($category->getKey(), collect()) : collect(),
            ))
            ->values();

        return $this->respond([
            'school' => new SchoolResource($school),
            'categories' => DocumentCategoryResource::collection($listed),
        ]);
    }

    /**
     * Live articles only; the school site adds its fixed pages itself.
     */
    public function sitemap(string $slug): JsonResponse
    {
        $articles = $this->liveArticles($this->findSchool($slug), withMedia: false)
            ->get(['id', 'slug', 'published_at', 'updated_at']);

        return $this->respond([
            'articles' => $articles->map(fn (Article $article): array => [
                'slug' => $article->slug,
                'updated_at' => ArticleResource::lastModified($article),
            ])->all(),
        ]);
    }

    /**
     * An unknown or inactive school is a 404.
     */
    protected function findSchool(string $slug): School
    {
        $school = School::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with('media')
            ->first();

        abort_if($school === null, 404);

        return $school;
    }

    /**
     * The school's live articles, newest first, with their cover (and gallery
     * when asked) loaded.
     *
     * @return Builder<Article>
     */
    protected function liveArticles(School $school, bool $withMedia = true, bool $withGallery = false): Builder
    {
        $collections = $withGallery ? ['cover', 'gallery'] : ['cover'];

        return Article::withoutGlobalScope(SchoolScope::class)
            ->where('school_id', $school->getKey())
            ->live()
            ->when($withMedia, fn (Builder $query) => $query->with([
                'media' => fn ($media) => $media->whereIn('collection_name', $collections),
            ]))
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function respond(array $data): JsonResponse
    {
        return response()->json($data, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
