<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

$integratedNumber = env('MSG91_INTEGRATED_NUMBER');
$authKey = env('MSG91_AUTH_KEY');

$msg91Payload = [
    'integrated_number' => '919876543210',
    'content_type' => 'text',
    'payload' => [
        'to' => '919876543210',
        'type' => 'text',
        'text' => 'Hello from test API integration'
    ]
];

try {
    $response = Http::withHeaders([
        'authkey' => $authKey,
        'Content-Type' => 'application/json'
    ])->post('https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/bulk/', $msg91Payload);

    echo "Status Code: " . $response->status() . "\n";
    echo "Response Body: " . $response->body() . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
