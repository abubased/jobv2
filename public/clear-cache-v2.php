<?php
/**
 * Clear Laravel cache - Works with moved public folder
 * Delete this file after use!
 */

echo "<pre>";
echo "=== Laravel Cache Cleaner ===\n\n";

// Try to find Laravel root
$possiblePaths = [
    __DIR__ . '/..',                          // One level up
    dirname(__DIR__),                         // Alternative
    dirname($_SERVER['DOCUMENT_ROOT']),       // If public_html is doc root
    $_SERVER['DOCUMENT_ROOT'] . '/..',        // Document root parent
    '/home/7c/4a/94/rukhsana',               // Your specific path
];

$laravelRoot = null;

echo "Searching for Laravel root...\n";

foreach ($possiblePaths as $path) {
    $realPath = realpath($path);
    echo "  Checking: $path";
    
    if ($realPath && file_exists($realPath . '/artisan')) {
        echo " ✓ FOUND!\n";
        $laravelRoot = $realPath;
        break;
    }
    echo " ✗\n";
}

if (!$laravelRoot) {
    die("\n❌ Error: Could not find Laravel root (looking for 'artisan' file)\n\n" .
        "Current directory: " . __DIR__ . "\n" .
        "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n\n" .
        "Please manually check where these files are:\n" .
        "  - artisan\n" .
        "  - vendor/autoload.php\n" .
        "  - bootstrap/app.php\n");
}

echo "\nLaravel root: $laravelRoot\n";

// Check vendor
$vendorPath = $laravelRoot . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    die("\n❌ Error: vendor/autoload.php not found!\n" .
        "Path checked: $vendorPath\n\n" .
        "You need to:\n" .
        "1. Upload the 'vendor' folder to: $laravelRoot/vendor/\n" .
        "2. Or run 'composer install' on the server\n");
}

echo "Vendor found: ✓\n\n";

// Change to Laravel root
chdir($laravelRoot);

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
    echo "File to delete: " . __FILE__ . "\n";
    
} catch (Exception $e) {
    echo "\n❌ Error:\n";
    echo $e->getMessage() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "</pre>";
