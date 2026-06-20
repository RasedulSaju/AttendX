<?php
declare(strict_types=1);

/**
 * JWT Configuration
 * Set JWT_SECRET in your .env or Windows environment variables.
 * Minimum 32 characters, randomly generated.
 */
return [
    // Signing secret — MUST be set in production via environment variable
    'secret' => env('JWT_SECRET', ''),

    // Access token TTL in seconds (default 24 hours)
    'ttl' => (int)env('JWT_TTL', 86400),

    // Mobile access token TTL (default 7 days)
    'mobile_ttl' => (int)env('JWT_MOBILE_TTL', 604800),

    // Refresh token TTL (default 30 days)
    'refresh_ttl' => (int)env('JWT_REFRESH_TTL', 2592000),
];
