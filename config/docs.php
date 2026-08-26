<?php

/**
 * Kebijakan dokumentasi (ADR-085): screenshot documentation WAJIB lokal.
 * Object storage (S3/R2) hanya untuk business evidence â€” tidak pernah untuk docs.
 */
return [
    // Disk penyimpanan docs â€” default local, JANGAN FILESYSTEM_DISK.
    'disk' => env('DOCS_DISK', 'docs'),

    // Root di dalam disk (storage/app/docs untuk disk local).
    'root' => 'docs',

    'screenshots_path' => 'screenshots',
    'generated_path' => 'generated',
    'manifests_path' => 'manifests',

    // Environment tempat capture diizinkan.
    'capture_environments' => ['local', 'demo', 'testing'],

    // Override eksplisit bila ingin capture di env lain (tetap wajib demo tenant).
    'capture_allowed_override' => env('DOCS_CAPTURE_ALLOWED', false),

    // Tenant demo yang sah untuk capture (anti production tenant).
    'demo_company_code' => env('DOCS_DEMO_COMPANY', 'GP'),

    'viewport_desktop' => [1440, 1000],
    'viewport_mobile' => [390, 844],
];
