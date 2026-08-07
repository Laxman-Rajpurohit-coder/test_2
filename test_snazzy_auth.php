<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'like', '%snazzy%')->first();
if ($user) {
    $tenant = App\Models\Tenant::find($user->tenant_id);
    echo "User: " . $user->email . "\n";
    echo "Tenant ID: " . $tenant->id . "\n";
    echo "Tenant Auth Key: " . ($tenant->msg91_auth_key ? 'SET ('.substr($tenant->msg91_auth_key, 0, 5).'***)' : 'NOT SET') . "\n";
    echo "Tenant Number: " . ($tenant->msg91_integrated_number ? 'SET ('.$tenant->msg91_integrated_number.')' : 'NOT SET') . "\n";
} else {
    echo "No snazzy user found.\n";
}
