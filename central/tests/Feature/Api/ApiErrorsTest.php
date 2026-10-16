<?php

use App\Models\School;

/*
| The API is GET only and rate limited to 300 requests per minute per IP.
| Errors are JSON, never an HTML page.
*/

beforeEach(function () {
    $this->school = School::factory()->create();
});

it('answers unknown API paths with a JSON 404', function (string $path) {
    $this->get($path)
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['error' => 'not_found']);
})->with([
    '/api',
    '/api/v1',
    '/api/v1/schools',
    '/api/v2/schools/neki-dom',
    '/api/v1/schools/neki-dom/pages/o-nama',
]);

it('answers unknown paths under a school with a JSON 404', function () {
    $this->get(schoolApi($this->school, '/nepoznato'))
        ->assertNotFound()
        ->assertExactJson(['error' => 'not_found']);
});

it('allows only GET', function (string $method, string $path) {
    $this->json($method, schoolApi($this->school, $path))
        ->assertStatus(405)
        ->assertExactJson(['error' => 'method_not_allowed']);
})->with(['POST', 'PUT', 'PATCH', 'DELETE'])->with(['', '/home', '/articles', '/articles/neka-vijest', '/documents', '/sitemap']);

it('allows HEAD requests', function () {
    $this->call('HEAD', schoolApi($this->school))->assertOk();
});

it('allows 300 requests per minute per IP, then answers 429', function () {
    foreach (range(1, 300) as $attempt) {
        $this->get(schoolApi($this->school))->assertOk();
    }

    $this->get(schoolApi($this->school))
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertExactJson(['error' => 'too_many_requests']);

    // Another IP address has its own limit.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->get(schoolApi($this->school))
        ->assertOk();

    // The limit resets after a minute.
    $this->travel(61)->seconds();
    $this->get(schoolApi($this->school))->assertOk();
});

it('counts requests for unknown schools towards the limit', function () {
    config(['cms.api.rate_limit_per_minute' => 2]);

    $this->get('/api/v1/schools/ne-postoji')->assertNotFound();
    $this->get('/api/v1/schools/ne-postoji/home')->assertNotFound();

    $this->get(schoolApi($this->school))->assertStatus(429);
});

it('does not rate limit the dashboard', function () {
    config(['cms.api.rate_limit_per_minute' => 1]);

    $this->get(schoolApi($this->school))->assertOk();
    $this->get(schoolApi($this->school))->assertStatus(429);

    $this->get('/admin/login')->assertOk();
});
