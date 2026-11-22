<?php
/**
 * Clear Laravel cache - For when everything is in public_html
 * Delete this file after use!
 */

echo "<pre>";
echo "=== Laravel Cache Cleaner ===\n\n";

// When everything is in the same folder (public_html)
$laravelRoot = __DIR__;

echo "Laravel root: $laravelRoot\n";

// Check if this is actually Laravel root
if (!file_exists($laravelRoot . '/artisan')) {
    die("\n❌ Error: 'artisan' file not found in current directory\n" .
        "Current directory: $laravelRoot\n\n" .
        "Please make sure:\n" .
        "  - This script is in the same folder as 'artisan'\n" .
        "  - Laravel files are uploaded to the server\n");
}

echo "✓ artisan found\n";

// Check vendor
$vendorPath = $laravelRoot . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    die("\n❌ Error: vendor/autoload.php not found!\n" .
        "Path checked: $vendorPath\n\n" .
        "You need to:\n" .
        "1. Upload the 'vendor' folder to: $laravelRoot/vendor/\n" .
        "2. Or run 'composer install' on the server\n\n" .
        "The vendor folder should be in the same directory as this script.\n");
}

echo "✓ vendor found\n";

// Check bootstrap
if (!file_exists($laravelRoot . '/bootstrap/app.php')) {
    die("\n❌ Error: bootstrap/app.php not found!\n" .
        "Path checked: $laravelRoot/bootstrap/app.php\n\n" .
        "Please upload the 'bootstrap' folder.\n");
}

echo "✓ bootstrap found\n\n";

// Load Laravel
require_once $vendorPath;

try {
    $app = require_once $laravelRoot . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    
    echo "--- Clearing caches ---\n\n";
    
    // Clear caches
    $kernel->call('config:clear');
    echo "✓ Config cache cleared\n";
    
    $kernel->call('cache:clear');
    echo "✓ Application cache cleared\n";
    
    $kernel->call('view:clear');
    echo "✓ View cache cleared\n";
    
    $kernel->call('route:clear');
    echo "✓ Route cache cleared\n";
    
    echo "\n--- Optimizing for production ---\n\n";
    
    $kernel->call('config:cache');
    echo "✓ Config cached\n";
    
    $kernel->call('route:cache');
    echo "✓ Routes cached\n";
    
    $kernel->call('view:cache');
    echo "✓ Views cached\n";
    
    echo "\n✅ SUCCESS! All caches cleared and optimized.\n";
    echo "\n⚠️  IMPORTANT: Delete this file now for security!\n";
    echo "File to delete: clear-cache-v3.php\n";
    
} catch (Exception $e) {
    echo "\n❌ Error:\n";
    echo $e->getMessage() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "</pre>";
