<?php
/**
 * Test Laravel routes
 */

echo "<pre>";
echo "=== Laravel Routes Test ===\n\n";

$laravelRoot = __DIR__;

if (!file_exists($laravelRoot . '/vendor/autoload.php')) {
    die("Error: vendor not found");
}

require_once $laravelRoot . '/vendor/autoload.php';

try {
    $app = require_once $laravelRoot . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    
    // Get routes
    $kernel->bootstrap();
    $routes = app('router')->getRoutes();
    
    echo "Total routes: " . count($routes) . "\n\n";
    echo "Looking for /applications route:\n\n";
    
    $found = false;
    foreach ($routes as $route) {
        $uri = $route->uri();
        if (strpos($uri, 'applications') !== false) {
            echo "✅ Found: " . $route->methods()[0] . " /" . $uri . "\n";
            echo "   Name: " . ($route->getName() ?? 'unnamed') . "\n";
            echo "   Action: " . $route->getActionName() . "\n\n";
            $found = true;
        }
    }
    
    if (!$found) {
        echo "❌ /applications route not found!\n";
        echo "   Route cache might be stale.\n";
    }
    
    echo "\n--- All Routes ---\n";
    foreach ($routes as $route) {
        $method = $route->methods()[0];
        $uri = $route->uri();
        echo "$method /$uri\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
