<?php
/**
 * Temporary cache clearing script for shared hosting
 * Delete this file after use for security
 */

// Navigate to Laravel root
chdir(__DIR__ . '/../');

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "Clearing Laravel caches...\n\n";

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
echo "\n⚠️  IMPORTANT: Delete this file (clear-cache.php) for security!\n";
