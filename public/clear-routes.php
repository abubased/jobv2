<?php
/**
 * Clear route cache only
 * Delete after use!
 */

echo "<pre>";
echo "=== Route Cache Cleaner ===\n\n";

$laravelRoot = __DIR__;

if (!file_exists($laravelRoot . '/vendor/autoload.php')) {
    die("Error: vendor not found");
}

require_once $laravelRoot . '/vendor/autoload.php';

try {
    $app = require_once $laravelRoot . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    
    echo "Clearing route cache...\n";
    $kernel->call('route:clear');
    echo "✓ Route cache cleared\n\n";
    
    echo "Caching routes...\n";
    $kernel->call('route:cache');
    echo "✓ Routes cached\n\n";
    
    echo "✅ Done! Delete this file now.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

echo "</pre>";
