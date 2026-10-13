<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    |
    | Credentials for the single system owner account, created or updated by
    | the SuperAdminSeeder. Set them in .env; they are never committed.
    |
    */

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super admin'),
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Login Rate Limit
    |--------------------------------------------------------------------------
    |
    | Failed dashboard logins allowed per email + IP address within the decay
    | window before further attempts are blocked.
    |
    */

    'login' => [
        'max_attempts' => 5,
        'decay_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Data
    |--------------------------------------------------------------------------
    |
    | Password for the demo editor accounts created when seeding a local
    | environment. Demo data is never seeded outside APP_ENV=local.
    |
    */

    'demo' => [
        'editor_password' => env('DEMO_EDITOR_PASSWORD', 'urednik-demo-lozinka'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    |
    | Every uploaded image is scaled down to fit within this many pixels on
    | its longest side and stripped of metadata before it is stored. Uploads
    | are limited to max_upload_kb (also enforced by media-library.max_file_size).
    |
    */

    'images' => [
        'max_dimension' => 2000,
        'max_upload_kb' => 10 * 1024,
    ],

    /*
    |--------------------------------------------------------------------------
    | Articles
    |--------------------------------------------------------------------------
    */

    'articles' => [
        'gallery_max' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Trash
    |--------------------------------------------------------------------------
    |
    | Soft-deleted articles and documents are permanently deleted, with their
    | files, this many days after deletion (content:purge-trashed, daily).
    |
    */

    'trash' => [
        'purge_after_days' => 30,
    ],

];
