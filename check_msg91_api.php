<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$setting = \App\Models\TenantSetting::whereNotNull('msg91_auth_key')->first();
if (!$setting) {
    echo "NO TENANT SETTING FOUND WITH AUTH KEY\n";
    exit;
}

$tenantId = $setting->tenant_id;
$number = \Illuminate\Support\Facades\DB::table('tenant_numbers')
    ->where('tenant_id', $tenantId)
    ->value('integrated_number');

if (!$number) {
    echo "NO INTEGRATED NUMBER FOUND FOR TENANT {$tenantId}\n";
    exit;
}

$authKey = $setting->msg91_auth_key; // decrypts automatically via accessor
echo "Fetching for Number: {$number}...\n";

$response = \Illuminate\Support\Facades\Http::withHeaders([
    'authkey' => $authKey,
])->get("https://control.msg91.com/api/v5/whatsapp/get-template-client/{$number}");

if ($response->failed()) {
    echo "API FAILED: " . $response->status() . "\n" . $response->body() . "\n";
    exit;
}

$data = $response->json('data') ?? [];
echo "TOTAL TEMPLATES: " . count($data) . "\n";
if (count($data) > 0) {
    echo "RAW FIRST TEMPLATE GROUP:\n";
    print_r($data[0]);
}
