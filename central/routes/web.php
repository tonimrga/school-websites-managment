<?php

use App\Http\Controllers\DocumentDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public document downloads; the link of a document never changes.
Route::get('/files/documents/{document}', DocumentDownloadController::class)
    ->whereNumber('document')
    ->name('documents.download');
