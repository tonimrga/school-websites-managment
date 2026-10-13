# School Websites CMS — Specification


## 1. Overview

A multi-tenant CMS that lets one central system manage the websites of many schools. Each school gets a public website (news, documents, about us, contact). Content is edited in one central, login-protected dashboard. The public sites run on the schools' existing shared hosting and read their content from a central REST API.

### Goals

- One place to manage content for all schools.
- Simple editing for non-technical school staff: articles with cover photo and gallery, documents grouped by category.
- Public sites that run on any cheap PHP shared hosting, with good SEO and correct social-media link previews.
- As little code and as few moving parts as possible.

### Non-goals for version 1

- Contact forms or any email sending from public sites.
- Draft preview inside the real public site layout (preview is shown in the dashboard only).
- Fully custom designs per school. Version 1 ships one layout with 2–3 color themes.
- Multiple languages. Everything (public sites and dashboard) is in Croatian.
- Caching on the public sites.
- Full migration of old websites. Only the last ~10 news items and current documents are imported, plus a link to the old site.
- Deployment, servers, backups and monitoring. This spec covers building and running everything locally; production setup will be a separate document.

### Glossary

| Term | Meaning |
| --- | --- |
| School | A tenant. Each school has its own content, settings, and public website. |
| Super admin | The single system owner. Manages schools and users, and can edit any content. |
| Editor | One per school. Manages that school's content only. |
| Central server | Hosts the database, the dashboard, the public API, and uploaded files. |
| School site | The small PHP website installed on a school's shared hosting. |

## 2. Architecture and tech stack

One central Laravel application serves the dashboard, the public read-only API, and uploaded files. Each school site is a small, stateless PHP site on the school's shared hosting. On every page view it calls the API on the server side and renders HTML.

```
Editor / super admin (browser)              Visitors (browser)
          |                                        |
          | edits content                          | page views
          v                                        v
+--------------- Central server (Laravel) --+   +-- School shared hosting --+
|  Dashboard (Filament)    Public API  <----+---+-- School site A (PHP)     |
|        |                      |           |   |   School site B (PHP)     |
|        v                      v           |   |   School site N (PHP)     |
|  File storage            Database         |   +---------------------------+
+-------------------------------------------+
```

School sites call the API from their own server, never from the visitor's browser; images and document downloads load directly from the central file storage.

### Components

| Component | Where it runs | Responsibility |
| --- | --- | --- |
| Dashboard | Central server | Login, content editing, school and user management (Filament panel). |
| Public API | Central server | Read-only JSON endpoints, published content only, no auth. |
| File storage | Central server (local disk, S3-compatible later if needed) | Images with resized versions, documents. Served by public URL. |
| Database | Central server | All schools in one database, every content row has `school_id`. |
| School site | School's shared hosting | Plain PHP templates; fetches from the API server-side and renders HTML. |

### Key decisions

- **Server-rendered school sites, not a JavaScript SPA.** HTML with meta tags is rendered on the server, so search engines and social-media link previews (Facebook, Viber, WhatsApp) work.
- **The dashboard does not use the REST API.** It is a server-rendered Filament panel in the same Laravel app. The REST API exists only for the public school sites.
- **No API keys.** The API only exposes content that is already public. A school is identified by its slug in the URL.
- **No caching on school sites in version 1.** Each page view makes one API call. The API returns everything a page needs in a single response to keep pages fast.
- **Images and documents are served from the central server**, not uploaded to school hosting.

### Tech stack

| Area | Choice | Notes |
| --- | --- | --- |
| Backend framework | Laravel (current stable version) | PHP 8.3+ |
| Admin dashboard | Filament (current stable version) | CRUD screens, reordering, uploads |
| Media | spatie/laravel-medialibrary | Image conversions (thumbnails, resized), gallery ordering |
| Rich text sanitizing | symfony/html-sanitizer or mews/purifier | Applied on save |
| Activity log | spatie/laravel-activitylog | Who changed what |
| Database | SQLite | Used in every environment, with WAL mode enabled; in production the database file lives outside the folder that deployments replace |
| Tests | Pest | Feature tests for tenant isolation and API |
| School site | Plain PHP 8.1+, no framework, no Composer | Uses cURL; one `config.php` |
| School site CSS | Hand-written CSS with CSS variables for themes | No build step |

## 3. Roles and permissions

There are exactly two roles: one super admin for the whole system, and one editor per school.

| Action | Super admin | Editor |
| --- | --- | --- |
| Log in to dashboard | Yes | Yes |
| Create, edit, deactivate schools | Yes | No |
| Create, edit, deactivate users | Yes | No |
| Reset another user's password | Yes | No |
| Edit school settings (name, logo, theme, contact info) | Yes | Contact info and logo only |
| Create, edit, delete articles | Any school | Own school only |
| Create, edit, delete, reorder documents | Any school | Own school only |
| Create, rename, delete document categories | Any school | No |
| Reorder document categories | Any school | Own school only |
| Edit pages (About us) | Any school | Own school only |
| View activity log | All schools | No |
| Change own password | Yes | Yes |

### Rules

- An editor belongs to exactly one school (`users.school_id`). The super admin has `school_id = null` and `role = super_admin`.
- Only the super admin can create users. There is no self-registration.
- The super admin works on a school's content by selecting the school in the dashboard (a school switcher). Editors never see a switcher.
- A deactivated user cannot log in. A deactivated school's API endpoints return 404, so its site shows a "temporarily unavailable" page.
- The system should not prevent adding a second editor to a school later, but version 1 only needs one.

## 4. Data model

One database holds all schools; every content table has a required `school_id` and is filtered by it through a global scope. Articles and documents use soft deletes so mistakes can be undone.

### schools

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint |  |
| name | string | Full name, e.g. "Osnovna škola Ivana Gundulića" |
| slug | string, unique | Used in API URLs, e.g. `os-ivana-gundulica` |
| site_url | string | Public URL of the school site, used for canonical links and sitemap |
| theme | enum | `blue`, `green`, `red` (see section 7) |
| logo | media | Collection `logo`, PNG/JPG/WebP |
| address, postal_code, city | string | Shown on contact page and footer |
| phone, email | string, nullable |  |
| office_hours | text, nullable | Free text, e.g. working hours of the secretariat |
| map_embed_url | string, nullable | Google Maps or OpenStreetMap embed URL |
| contact_note | text, nullable | Extra text on the contact page (e.g. list of staff contacts) |
| social_links | json, nullable | Facebook, Instagram, YouTube URLs |
| old_site_url | string, nullable | Link to the old website, shown in the footer and news archive |
| is_active | bool | Inactive = API returns 404 |
| timestamps |  |  |

### users

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint |  |
| name | string |  |
| email | string, unique | Login |
| password | string | Hashed (bcrypt) |
| role | enum | `super_admin`, `editor` |
| school_id | FK, nullable | Required for editors, null for super admin |
| is_active | bool |  |
| last_login_at | datetime, nullable |  |
| timestamps |  |  |

### articles

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint |  |
| school_id | FK |  |
| title | string | Max 200 chars |
| slug | string | Unique per school, generated from title, editable |
| excerpt | text | Max 300 chars; used in lists and as the social preview description. Auto-filled from body if empty. |
| body | longtext | Sanitized HTML |
| cover | media | Collection `cover`, one image, required to publish |
| gallery | media | Collection `gallery`, many images, ordered, optional caption each |
| status | enum | `draft`, `published` |
| published_at | datetime | Defaults to now on publish; a future date schedules publication |
| is_featured | bool | Shown in the homepage highlight area and pinned to the top of the news list |
| created_by, updated_by | FK users |  |
| timestamps, deleted_at |  |  |

Indexes: `(school_id, status, published_at)`, unique `(school_id, slug)`.

### document_categories

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint |  |
| school_id | FK |  |
| name | string | e.g. "Natječaji", "Godišnji plan i program" |
| slug | string | Unique per school |
| sort_order | int | Manual order (drag and drop) |
| timestamps |  |  |

### documents

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint |  |
| school_id | FK |  |
| document_category_id | FK | Required |
| title | string |  |
| description | text, nullable |  |
| file | media | Collection `file`, one file |
| document_date | date, nullable | Date shown on the site (e.g. date of the decision) |
| is_published | bool |  |
| sort_order | int | Manual order within its category (drag and drop) |
| timestamps, deleted_at |  |  |

Index: `(school_id, document_category_id, sort_order)`.

### pages

| Field | Type | Notes |
| --- | --- | --- |
| id | bigint |  |
| school_id | FK |  |
| key | string | `about` in version 1; unique per school |
| title | string |  |
| body | longtext | Sanitized HTML |
| image | media, nullable | Optional header image |
| timestamps |  |  |

Each new school is created with an empty `about` page and a default set of document categories.

### Media rules

- Images: JPG, PNG, WebP; max 10 MB per file. SVG is not allowed.
- Documents: PDF, DOC/DOCX, XLS/XLSX, ODT/ODS; max 20 MB per file.
- On upload, every image is resized to at most 2000 px on its longest side and its metadata (EXIF, including GPS location) is stripped before it is stored. The phone original is never stored.
- Image conversions are generated immediately on save, with no queue: `thumb` 400×300 crop and `large` 1600 px wide, both WebP. An article gallery holds at most 30 images.
- Files are stored with random names; the original file name is kept as metadata and used as the download name.
- All files are read and written only through Laravel's `Storage` facade on a configurable disk (`public` locally; local disk or any S3-compatible bucket in production). No code uses fixed file system paths.
- Media is removed when its article or document is permanently deleted. Soft-deleted articles and documents are permanently deleted, with their files, 30 days after deletion by a daily scheduled command.

## 5. Dashboard

The dashboard is one Filament panel at `/admin` on the central server, fully in Croatian. Editors see only their own school's content; the super admin additionally manages schools and users and switches between schools.

### Login and account

- Email + password login, with rate limiting on failed attempts.
- No two-factor authentication in version 1; Filament supports adding it later if needed.
- Password reset by email link.
- "My profile" page: change name and password.

### Home screen

- Shortcuts: "Nova vijest" (new article), "Novi dokument" (new document).
- Last 5 articles and last 5 documents with status.
- Link to the school's public site.

### Articles (Vijesti)

- List: title, status, featured flag, published date. Filters by status and featured. Search by title. Sorted newest first.
- Form fields:
  - Title, slug (auto-generated, editable), excerpt
  - Rich text body: headings, bold, italic, lists, links, quotes, tables. No inline images in version 1; images go in the gallery.
  - Cover image upload with crop preview
  - Gallery: multi-file upload, drag-and-drop reordering, optional caption per image, remove image, up to 30 images per article
  - Status (draft/published), publish date, featured toggle
- Preview: a read-only preview of the article inside the dashboard before publishing.
- Actions: save draft, publish, unpublish, delete (soft), restore from trash.
- Publishing requires a title, body, and cover image.

### Documents (Dokumenti)

- List grouped or filtered by category, ordered by `sort_order`, with drag-and-drop reordering within a category.
- Form fields: title, category, description, file upload, document date, published toggle.
- Replacing a file keeps the same document entry (same position, same link).
- Actions: create, edit, delete (soft), restore, reorder.

### Document categories (Kategorije dokumenata)

- Super admin: create, rename, delete. Editor: drag-and-drop reorder only (no create, rename or delete buttons shown).
- A category with documents cannot be deleted until its documents are moved or deleted.

### Pages (Stranice)

- Edit the "O nama" (About us) page: title, rich text body, optional image.

### School settings (Postavke škole)

- Editor: logo, address, phone, email, office hours, map embed URL, contact note, social links.
- Super admin additionally: name, slug, site URL, theme, old site URL, active flag.

### Super admin only

- **Schools:** list, create, edit, deactivate. Creating a school also creates its `about` page and default document categories. The schools list shows each school's storage use (total size of its images and documents).
- **Users:** list, create editor (assign to one school), edit, deactivate, send password reset.
- **School switcher:** a dropdown in the top bar that sets which school's content the super admin is working on.
- **Activity log:** who created, changed, published or deleted what, and when; filterable by school and user.

### Croatian UI

Use Filament's Croatian translation where available; add missing labels in `lang/hr`. Dates are shown as `d. m. Y.` (e.g. 12. 10. 2026.).

## 6. Public REST API

The API is read-only, unauthenticated, and returns only published content. Each endpoint returns everything one public page needs, so every page view makes exactly one API call.

Base URL: `https://<central-domain>/api/v1/schools/{slug}`

### Endpoints

| Method + path | Used by page | Returns |
| --- | --- | --- |
| `GET /` | Contact, generic | `school` only |
| `GET /home` | Homepage | `school`, `featured` (up to 3 articles), `latest` (6 newest non-featured articles), `about_excerpt` |
| `GET /articles?page=N` | News list | `school`, `data` (12 per page; featured first, then newest), `meta` (pagination) |
| `GET /articles/{article_slug}` | Article detail | `school`, `article` (full body, cover, gallery), `recent` (3 other newest articles) |
| `GET /documents` | Documents | `school`, `categories` (ordered), each with its published `documents` (ordered) |
| `GET /documents?category={category_slug}` | Documents, filtered | Same shape, only that category's documents; all categories still listed for the filter |
| `GET /pages/{key}` | About us | `school`, `page` |
| `GET /sitemap` | `sitemap.xml` on school site | List of article slugs with `updated_at`, plus static page paths |

### Visibility rules

- School must exist and be active; otherwise 404.
- Articles: `status = published`, `published_at <= now`, not soft-deleted.
- Documents: `is_published = true`, not soft-deleted.
- Never expose drafts, user data, internal IDs of users, or original image files.

### Shared objects

- **school:** name, slug, site_url, theme, logo (URLs), address, postal_code, city, phone, email, office_hours, map_embed_url, contact_note, social_links, old_site_url.
- **image:** `thumb` and `large` URLs, `width`, `height`, `alt` (caption or article title).
- **file:** `url`, `name` (original file name), `extension`, `size_bytes`.
- **article (list item):** title, slug, excerpt, cover (image), published_at (ISO 8601), is_featured.
- **article (detail):** list item fields + `body` (sanitized HTML) + `gallery` (ordered list of image + caption) + `updated_at`.

### Example: article detail

```json
{
  "school": { "name": "OŠ Ivana Gundulića", "slug": "os-ivana-gundulica", "theme": "blue", "...": "..." },
  "article": {
    "title": "Dan otvorenih vrata",
    "slug": "dan-otvorenih-vrata",
    "excerpt": "Pozivamo roditelje i buduće učenike...",
    "body": "<p>...</p>",
    "published_at": "2026-10-12T09:00:00+02:00",
    "updated_at": "2026-10-12T10:15:00+02:00",
    "is_featured": true,
    "cover": { "thumb": "https://.../thumb.webp", "large": "...", "width": 1600, "height": 900, "alt": "Dan otvorenih vrata" },
    "gallery": [ { "image": { "thumb": "...", "large": "..." }, "caption": "Posjet knjižnici" } ]
  },
  "recent": [ { "title": "...", "slug": "...", "excerpt": "...", "cover": { "thumb": "..." }, "published_at": "..." } ]
}
```

### Errors and limits

- 404 `{"error": "not_found"}` for unknown or inactive school, unknown or unpublished article, unknown category or page.
- 429 when rate-limited: 300 requests per minute per IP (school sites call from their host's IP, so the limit is generous).
- Responses are UTF-8 JSON, keys in `snake_case`, dates in ISO 8601 with Europe/Zagreb offset.
- Breaking changes go into `/api/v2`; `/api/v1` stays stable once school sites are installed.

## 7. Public school site

The school site is one folder of plain PHP files that is identical for every school except `config.php`. It has no database, no Composer, and no build step, so it can later run on any shared hosting.

### Routes

| URL | Page | API call |
| --- | --- | --- |
| `/` | Homepage (Naslovna) | `/home` |
| `/vijesti` and `/vijesti?stranica=2` | News list | `/articles?page=N` |
| `/vijesti/{slug}` | Article | `/articles/{slug}` |
| `/dokumenti` and `/dokumenti?kategorija={slug}` | Documents | `/documents` |
| `/o-nama` | About us | `/pages/about` |
| `/kontakt` | Contact | `/` |
| `/sitemap.xml` | Sitemap | `/sitemap` |
| `/robots.txt` | Static file | none |

Pretty URLs use an `.htaccess` rewrite to `index.php` (Apache and LiteSpeed, which nearly all shared hosts use). Locally, `index.php` also works as the router script for PHP's built-in server (see section 9).

### File structure

```
school-site/
  config.php            # API_BASE_URL, SCHOOL_SLUG, SITE_URL
  index.php             # front controller and router
  .htaccess             # rewrite rules, security headers
  robots.txt
  lib/
    api.php             # cURL GET with 5 s timeout, JSON decode, error mapping
    helpers.php         # e() escaping, Croatian date format, URL helpers
  templates/
    layout.php          # <head>, header, nav, footer
    home.php, news.php, article.php, documents.php,
    about.php, contact.php, 404.php, unavailable.php
    partials/           # article card, pagination, gallery, document row
  assets/
    css/style.css       # layout + themes as CSS variables
    js/gallery.js       # small vanilla-JS lightbox, no dependencies
```

### Page content

- **Layout:** header with logo, school name and navigation (Naslovna, Vijesti, Dokumenti, O nama, Kontakt); mobile menu; footer with address, phone, email, social links, and a link to the old website if set.
- **Homepage:** featured articles as large cards, latest news grid, short "O nama" excerpt with link, contact block.
- **News list:** cards with cover, title, date, excerpt; featured articles first and marked; pagination; link to the old site's news archive at the end.
- **Article:** title, date, cover image, body, gallery grid that opens a lightbox, three recent articles.
- **Documents:** category filter as links; documents grouped by category, each with title, date, description, file type and size, and a download link.
- **About us:** title, optional image, body.
- **Contact:** address, clickable phone (`tel:`) and email (`mailto:`), office hours, map embed, contact note. No form.

### SEO and link previews

- `<html lang="hr">`, unique `<title>` and meta description per page.
- Open Graph and Twitter card tags on every page; on articles `og:type=article`, `og:image` = the cover's `large` version.
- Canonical URL built from `SITE_URL`.
- `sitemap.xml` generated from the API's `/sitemap` response.

### Themes

Version 1 has one layout and three color themes, chosen by the super admin per school and returned by the API as `school.theme`. The layout sets `<body data-theme="blue">` and `style.css` defines each theme as a set of CSS variables.

| Theme | Primary color idea | Variables |
| --- | --- | --- |
| `blue` | Navy blue | `--color-primary`, `--color-primary-dark`, `--color-accent`, `--color-surface`, `--color-text` |
| `green` | Forest green | Same variables |
| `red` | Burgundy | Same variables |

Adding a theme = adding one CSS block and one enum value. All CSS uses the variables; no color is hard-coded elsewhere. Every theme must pass WCAG AA contrast (4.5:1 for text). Later, layout variants can be added as separate template folders selected the same way.

### Behavior

- All output is escaped with `htmlspecialchars` except article and page `body`, which is already sanitized by the central server.
- API timeout or connection error → `unavailable.php` with HTTP 503 and a friendly message.
- API 404 → `404.php` with HTTP 404.
- Responsive, mobile-first; images use `srcset` with the `thumb` and `large` versions and `loading="lazy"`.
- Accessibility: semantic HTML, skip link, visible focus states, alt text on all images, keyboard-operable lightbox.

## 8. Security requirements

Laravel and Filament cover password hashing, sessions, CSRF, SQL injection and template escaping by default; the requirements below cover what the application itself must get right. Each item marked **[test]** must have an automated test.

### Authentication

- Passwords hashed with bcrypt (Laravel default); minimum 12 characters; checked against the breached-password list (`Password::uncompromised()`).
- Login rate limit: 5 failed attempts per minute per email + IP. **[test]**
- No two-factor authentication in version 1. Accounts are protected by strong passwords and login rate limiting; 2FA can be added later.
- Sessions: secure, HttpOnly, SameSite=Lax cookies; session regenerated on login; idle timeout 2 hours.
- Inactive users cannot log in, and existing sessions are ended when a user is deactivated. **[test]**
- Password reset links expire after 60 minutes and work once.

### Tenant isolation (highest priority)

- Every content model (`Article`, `Document`, `DocumentCategory`, `Page`) uses a global scope that filters by the current user's `school_id`. The super admin's scope uses the school selected in the switcher.
- `school_id` is never accepted from form input; it is set from the current school on create.
- Policies check school ownership on view, update, delete and reorder actions.
- Media upload and reorder endpoints check that the parent record belongs to the user's school.
- Tests: an editor of school A cannot list, view, edit, delete, reorder or attach media to any record of school B, through the UI or by changing IDs in URLs or requests. **[test]**
- Tests: an editor cannot open school or user management screens, and cannot create, rename or delete document categories (only reorder them). **[test]**

### Content and uploads

- Rich text (`articles.body`, `pages.body`) is sanitized on save with an allow-list: `p, br, h2, h3, h4, strong, em, u, s, ul, ol, li, a[href], blockquote, table, thead, tbody, tr, th, td`. Links allow only `http`, `https`, `mailto`, `tel`. **[test]**
- Uploads are validated by real MIME type and extension against the allow-lists in section 4; SVG, HTML and executable files are rejected. **[test]**
- Uploaded files get random names, and the upload directory never executes scripts.
- Downloads are sent with `Content-Disposition: attachment` for documents and `X-Content-Type-Options: nosniff`.

### Public API

- Read-only (GET only), no authentication, rate-limited.
- Only published content is returned. **[test]** for drafts, future `published_at`, soft-deleted records, unpublished documents and inactive schools.
- No user data or internal fields in responses.

### Application hardening

- `APP_DEBUG` is only ever enabled for local development; `.env` is never committed to git.
- Security headers set by middleware in the central app and in the school site's `.htaccess`: `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options` (dashboard: DENY).
- Run `composer audit` regularly; keep Laravel and Filament on supported versions.
- Activity log of logins, failed logins, and every create/update/delete/publish action.

### Privacy (GDPR)

- Content often includes photos of children. Add a short reminder in the article form ("Objavljujete li fotografije učenika, provjerite imate li suglasnost roditelja.").
- Uploaded images have EXIF metadata (including GPS location) stripped on upload, and the phone original is never stored.
- The public sites set no tracking cookies in version 1, so no cookie banner is needed.

## 9. Local development

Everything runs on one machine: the central Laravel app on one port and one or more copies of the school site on others.

### Central app

- Run with `php artisan serve` (http://localhost:8000), or Laravel Herd / Sail if preferred.
- Database: SQLite with WAL mode, the same as in production; no database server is needed. The daily trash cleanup can be tested with Laravel's schedule:work command.
- `QUEUE_CONNECTION=sync`; no queue worker is needed, image conversions run during the save request.
- Uploaded files on the `public` disk (`php artisan storage:link`).
- `MAIL_MAILER=log`, so password reset emails are written to the log instead of sent.

### Demo data

`php artisan migrate --seed` creates:

- the super admin account (email and password from `.env`),
- two schools with different themes, each with an editor account,
- for each school: the about page, default document categories, about 15 articles (some featured, some drafts, one scheduled in the future) with covers and galleries, and about 20 documents.

Two schools are needed to check tenant isolation by hand.

### School site

- Run with PHP's built-in server from the `school-site/` folder: `php -S localhost:8081 index.php`.
- `config.php` points to `http://localhost:8000/api/v1` and one school slug.
- `config.php` reads `SCHOOL_SLUG` and `SITE_URL` from environment variables when they are set, so a second school can run at the same time: `SCHOOL_SLUG=os-druga-skola SITE_URL=http://localhost:8082 php -S localhost:8082 index.php`.

## 10. Implementation phases for Claude Code

Build in eight phases, each small enough for one or two Claude Code sessions and each ending with passing tests. Tenant isolation comes in phase 2, before any content features, so every later feature is built on it.

### Repository layout

```
repo/
  SPEC.md          # this document
  CLAUDE.md        # conventions for Claude Code (see below)
  central/         # Laravel + Filament app (dashboard + API)
  school-site/     # plain PHP public site
```

### Suggested CLAUDE.md conventions

- Read `SPEC.md` before starting a phase; do only that phase.
- Every content model uses the `BelongsToSchool` trait (global scope + auto-set `school_id`).
- Never accept `school_id` from request input.
- Write Pest feature tests for every policy and API endpoint; run `php artisan test` before finishing.
- UI text in Croatian, code and comments in English. All files go through Laravel's Storage facade (never fixed paths), and SQLite with WAL mode is used in every environment.
- School site: no frameworks, no Composer, escape all output with `e()`.

### Phases

1. **Foundation.** New Laravel app in `central/`, Filament panel at `/admin`, Croatian locale and timezone Europe/Zagreb, SQLite database with WAL mode, `users` with `role` and `school_id`, super admin seeder, login with rate limit, Pest. Done when the super admin can log in.
2. **Schools, users and tenant isolation.** `schools` table and Filament resource, user management, school switcher for the super admin, `BelongsToSchool` trait, policies, and the isolation test suite (using a placeholder content model). Done when all isolation tests pass.
3. **Articles.** Model, media library collections (cover, gallery with order and captions), images resized to max 2000 px with metadata stripped on upload, conversions generated on save (no queue), gallery limited to 30 images, HTML sanitizer, draft/publish/schedule, featured flag, dashboard preview, soft delete and restore with automatic purge after 30 days, activity log. Done when an editor can publish an article with a gallery and isolation tests cover articles.
4. **Documents and categories.** Categories with drag-and-drop order (editor and super admin) and create, rename and delete with delete protection (super admin only); documents with file validation, drag-and-drop order within a category, and file replacement. Default categories created with each new school.
5. **Pages and school settings.** "O nama" page, school settings form with role-based fields, dashboard home screen.
6. **Public API.** All endpoints from section 6 as API Resources, visibility rules, 404 handling, rate limiting, full test coverage of visibility. Done when every endpoint matches the documented shape.
7. **School site.** Router, API client, all templates, three themes, gallery lightbox, SEO and Open Graph tags, sitemap, error pages, accessibility pass. Done when both demo schools work locally against the central app.
8. **Demo data and final review.** Complete the demo seeder from section 9, run the full test suite, go through the security requirements in section 8, and click through the dashboard as each role and both school sites.

### Open questions

- Final list of default document categories for new schools.
- Exact colors for the three themes.
