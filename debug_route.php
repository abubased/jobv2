<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
$request = Illuminate\Http\Request::create('/job-applications', 'POST', [
    'name' => 'Test User',
    'father_or_husband' => 'Test Father',
    'mother' => 'Test Mother',
    'phone' => '01700000001',
    'address' => 'Dhaka',
    'education' => 'primary',
    'courier_help' => 'yes',
    'nearest_courier' => 'Dhaka',
    'submitted' => true,
]);
$response = $kernel->handle($request);
var_dump($response->getStatusCode());
echo "\n";
echo $response->getContent();
