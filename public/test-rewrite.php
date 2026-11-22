<?php
echo "<pre>";
echo "=== Apache Modules Test ===\n\n";

if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    
    if (in_array('mod_rewrite', $modules)) {
        echo "✅ mod_rewrite is ENABLED\n";
    } else {
        echo "❌ mod_rewrite is NOT enabled\n";
        echo "   Contact your hosting provider to enable it.\n";
    }
    
    echo "\nAll loaded modules:\n";
    foreach ($modules as $module) {
        echo "  - $module\n";
    }
} else {
    echo "ℹ️  Cannot check modules (not running as Apache module)\n";
    echo "   But this is normal on many shared hosting.\n\n";
    
    // Alternative check
    echo "Alternative check:\n";
    if (file_exists('.htaccess')) {
        echo "✅ .htaccess file exists\n";
    } else {
        echo "❌ .htaccess file NOT found!\n";
    }
}

echo "\n--- Environment Info ---\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";

echo "</pre>";
