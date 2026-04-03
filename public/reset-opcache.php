<?php

/**
 * OPcache Reset Script for Production
 *
 * Usage:
 * 1. Upload this file to public/ folder
 * 2. Visit: https://yourdomain.com/reset-opcache.php
 * 3. DELETE this file immediately after use!
 */

// Security: Only allow from specific IP or with secret key
$secret = 'YOUR_SECRET_KEY_HERE'; // Change this!
$providedSecret = $_GET['secret'] ?? '';

if ($providedSecret !== $secret) {
    http_response_code(403);
    exit('Access denied');
}

echo '<h1>OPcache Reset Tool</h1>';
echo '<pre>';

// Reset OPcache
if (function_exists('opcache_reset')) {
    $result = opcache_reset();
    echo '✓ OPcache reset: '.($result ? 'SUCCESS' : 'FAILED')."\n";

    $status = opcache_get_status();
    echo "\nOPcache Status:\n";
    echo '  Enabled: '.($status['opcache_enabled'] ? 'Yes' : 'No')."\n";
    echo '  Cache full: '.($status['cache_full'] ? 'Yes' : 'No')."\n";
    echo '  Cached scripts: '.$status['opcache_statistics']['num_cached_scripts']."\n";
} else {
    echo "✗ OPcache not available\n";
}

echo "\n";

// Clear Laravel caches
echo "\n--- Clearing Laravel Caches ---\n";
$baseDir = dirname(__DIR__);

// Clear cache
passthru("cd $baseDir && php artisan cache:clear", $exitCode);
echo ($exitCode === 0 ? '✓' : '✗')." Cache cleared\n";

// Clear config
passthru("cd $baseDir && php artisan config:clear", $exitCode);
echo ($exitCode === 0 ? '✓' : '✗')." Config cleared\n";

// Clear route
passthru("cd $baseDir && php artisan route:clear", $exitCode);
echo ($exitCode === 0 ? '✓' : '✗')." Routes cleared\n";

echo "\n<strong>⚠️ IMPORTANT: DELETE THIS FILE NOW!</strong>\n";
echo '</pre>';
