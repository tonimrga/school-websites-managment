<?php

/*
 * Per-school settings. This file (and pages/) is the only thing that differs
 * between school sites; keep it when copying a new version of the blueprint.
 *
 * Fill in all three values when installing a school's site. Until then the
 * site shows a "not configured" page instead of another school's content.
 *
 * SCHOOL_SLUG and SITE_URL can be overridden with environment variables, so
 * several schools can run locally at the same time. Only PHP's built-in
 * server (local development) falls back to the first demo school.
 */

$local = PHP_SAPI === 'cli-server';

// Central API, without a trailing slash, e.g. https://cms.example.hr/api/v1.
define('API_BASE_URL', 'http://localhost:8000/api/v1');

// The school's slug in the central dashboard.
define('SCHOOL_SLUG', getenv('SCHOOL_SLUG') ?: ($local ? 'os-ivana-gundulica' : ''));

// Public address of this site, used for canonical links and the sitemap.
define('SITE_URL', rtrim(getenv('SITE_URL') ?: ($local ? 'http://localhost:8081' : ''), '/'));

unset($local);
