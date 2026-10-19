<?php

/*
 * Client for the central public API. Every page view makes one call; error
 * pages may make one more (see index.php).
 */

const API_OK = 'ok';
const API_NOT_FOUND = 'not_found';
const API_UNAVAILABLE = 'unavailable';

/**
 * GET a path below this school's API base, e.g. api_get('/articles', ['page' => '2']).
 *
 * Returns ['status' => API_OK|API_NOT_FOUND|API_UNAVAILABLE, 'data' => array|null].
 * Timeouts, connection errors, rate limiting, server errors and invalid JSON
 * all count as unavailable.
 */
function api_get(string $path, array $query = []): array
{
    $url = API_BASE_URL . '/schools/' . rawurlencode(SCHOOL_SLUG) . $path;

    if ($query !== []) {
        $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'school-site/1.0 (' . SCHOOL_SLUG . ')',
    ]);

    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($body === false) {
        return ['status' => API_UNAVAILABLE, 'data' => null];
    }

    if ($status === 404) {
        return ['status' => API_NOT_FOUND, 'data' => null];
    }

    $data = json_decode($body, true);

    if ($status !== 200 || !is_array($data)) {
        return ['status' => API_UNAVAILABLE, 'data' => null];
    }

    return ['status' => API_OK, 'data' => $data];
}
