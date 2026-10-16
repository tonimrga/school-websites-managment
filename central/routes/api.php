<?php

use App\Http\Controllers\Api\SchoolApiController;
use Illuminate\Support\Facades\Route;

// Public, read-only API for the school sites. Only GET routes: any other
// method gets 405, unknown paths 404 (both as JSON).
Route::prefix('v1/schools/{slug}')
    ->controller(SchoolApiController::class)
    ->group(function (): void {
        Route::get('/', 'school');
        Route::get('/home', 'home');
        Route::get('/articles', 'articles');
        Route::get('/articles/{articleSlug}', 'article');
        Route::get('/documents', 'documents');
        Route::get('/sitemap', 'sitemap');
    });
