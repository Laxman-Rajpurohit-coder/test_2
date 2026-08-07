<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = App\Models\User::all();
foreach ($users as $user) {
    $tenant = App\Models\Tenant::find($user->tenant_id);
    echo "User: " . $user->email . " (Tenant ID: " . $tenant->id . ")\n";
}
