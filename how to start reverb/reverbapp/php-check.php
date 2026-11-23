
<?php

header('Content-Type: text/plain');

echo "PHP Version: " . PHP_VERSION . PHP_EOL;
echo "PHP Binary: " . (defined('PHP_BINARY') ? PHP_BINARY : 'Unknown') . PHP_EOL;
echo "PHP SAPI: " . PHP_SAPI . PHP_EOL;
