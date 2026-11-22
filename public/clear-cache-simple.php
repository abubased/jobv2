<?php
/**
 * Simple cache clearing script for shared hosting
 * Delete this file after use for security
 */

// Try different possible paths for Laravel root
$possiblePaths = [
    __DIR__ . '/..',           // Standard: public is inside Laravel
    __DIR__ . '/../..',        // If public_html is symlinked
    dirname(__DIR__),          // Alternative
];

$laravelRoot = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path . '/artisan')) {
        $laravelRoot = realpath($path);
        break;
    }
}

if (!$laravelRoot) {
    die("❌ Error: Could not find Laravel root directory (looking for 'artisan' file).\n\n" .
        "Current directory: " . __DIR__ . "\n" .
        "Please check your file structure.");
}

echo "Found Laravel root: $laravelRoot\n\n";

// Change to Laravel root
chdir($laravelRoot);

// Check if vendor exists
if (!file_exists($laravelRoot . '/vendor/autoload.php')) {
    die("❌ Error: vendor/autoload.php not found!\n\n" .
        "You need to upload the 'vendor' folder or run 'composer install' on the server.\n\n" .
        "Laravel root: $laravelRoot\n" .
        "Looking for: $laravelRoot/vendor/autoload.php");
}

require_once $laravelRoot . '/vendor/autoload.php';

$app = require_once $laravelRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "Clearing Laravel caches...\n\n";

try {
    // Clear config cache
    $kernel->call('config:clear');
    echo "✓ Config cache cleared\n";
    
    // Clear application cache
    $kernel->call('cache:clear');
    echo "✓ Application cache cleared\n";
    
    // Clear view cache
    $kernel->call('view:clear');
    echo "✓ View cache cleared\n";
    
    // Clear route cache
    $kernel->call('route:clear');
    echo "✓ Route cache cleared\n";
    
    echo "\n--- Now optimizing for production ---\n\n";
    
    // Cache config
    $kernel->call('config:cache');
    echo "✓ Config cached\n";
    
    // Cache routes
    $kernel->call('route:cache');
    echo "✓ Routes cached\n";
    
    // Cache views
    $kernel->call('view:cache');
    echo "✓ Views cached\n";
    
    echo "\n✅ All done! Your site should now load properly.\n";
    echo "\n⚠️  IMPORTANT: Delete this file (clear-cache-simple.php) for security!\n";
    
} catch (Exception $e) {
    echo "\n❌ Error occurred:\n";
    echo $e->getMessage() . "\n";
}
