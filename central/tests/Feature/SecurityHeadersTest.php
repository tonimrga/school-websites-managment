<?php

use App\Http\Middleware\SecurityHeaders;
use App\Models\School;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
| Every response of the central app carries the security headers; the
| dashboard (and everything else here) may not be framed.
*/

function assertSecurityHeaders(TestResponse $response): void
{
    foreach (SecurityHeaders::HEADERS as $name => $value) {
        $response->assertHeader($name, $value);
    }
}

it('sends security headers on dashboard pages', function () {
    assertSecurityHeaders($this->get('/admin/login')->assertOk());
});

it('sends security headers on redirects and errors', function () {
    assertSecurityHeaders($this->get('/admin')->assertRedirect());
    assertSecurityHeaders($this->get('/ne-postoji')->assertNotFound());
});

it('sends security headers on API responses, including errors', function () {
    $school = School::factory()->create();

    assertSecurityHeaders($this->getJson(schoolApi($school))->assertOk());
    assertSecurityHeaders($this->getJson(schoolApi($school, '/articles/ne-postoji'))->assertNotFound());
});

it('sends security headers on document downloads', function () {
    Storage::fake('local');
    $school = School::factory()->create();
    $document = createDocument($school, [], fn ($factory) => $factory->published()->withFile());

    assertSecurityHeaders($this->get($document->downloadUrl())->assertOk());
});
