<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

it('uses the Croatian locale and the Zagreb timezone', function () {
    expect(app()->getLocale())->toBe('hr')
        ->and(config('app.timezone'))->toBe('Europe/Zagreb')
        ->and(now()->getTimezone()->getName())->toBe('Europe/Zagreb');
});

it('opens SQLite database files in WAL mode', function () {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cms-wal-'.Str::random(8).'.sqlite';
    touch($path);

    config(['database.connections.wal_check' => array_merge(
        config('database.connections.sqlite'),
        ['database' => $path],
    )]);

    try {
        expect(DB::connection('wal_check')->selectOne('PRAGMA journal_mode')->journal_mode)->toBe('wal')
            ->and((int) DB::connection('wal_check')->selectOne('PRAGMA foreign_keys')->foreign_keys)->toBe(1);
    } finally {
        DB::purge('wal_check');
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($path.$suffix);
        }
    }
});

it('requires passwords of at least 12 characters', function () {
    Http::preventStrayRequests();

    $validator = Validator::make(['password' => 'Kratka1!'], ['password' => Password::defaults()]);

    expect($validator->fails())->toBeTrue();
});

it('rejects passwords found in breached-password lists', function () {
    $password = 'this-password-was-leaked';
    $hash = Str::upper(sha1($password));

    Http::preventStrayRequests();
    Http::fake([
        'api.pwnedpasswords.com/range/'.Str::substr($hash, 0, 5) => Http::response(Str::substr($hash, 5).':42'),
    ]);

    $validator = Validator::make(['password' => $password], ['password' => Password::defaults()]);

    expect($validator->fails())->toBeTrue();
});
