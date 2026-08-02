<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\TenantInvite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

echo "--- STEP 1: Generate Invite for Tenant 1 ---\n";
$invite = TenantInvite::create([
    'tenant_id' => 1,
    'email' => 'tinker@example.com',
    'token' => TenantInvite::generateToken(),
    'expires_at' => now()->addDays(7)
]);
$token = $invite->token;
echo "Created token: {$token}\n\n";

echo "--- STEP 2: Simulate Registration POST Request ---\n";
$request = Request::create('/register', 'POST', [
    'name' => 'Tinker User',
    'password' => 'password123',
    'password_confirmation' => 'password123',
    'token' => $token,
]);
$response = app()->handle($request);
echo "HTTP Status: {$response->getStatusCode()}\n\n";

echo "--- STEP 3: Verify User Created and Invite Accepted ---\n";
$user = User::where('email', 'tinker@example.com')->first();
echo "User created? " . ($user ? 'Yes' : 'No') . "\n";
echo "User Tenant ID: {$user->tenant_id}\n";

$invite->refresh();
echo "Invite accepted_at: " . ($invite->accepted_at ? $invite->accepted_at->toDateTimeString() : 'NULL') . "\n\n";

echo "--- STEP 4: Attempt Replay Attack ---\n";
$replayRequest = Request::create('/register', 'POST', [
    'name' => 'Hacker User',
    'password' => 'password123',
    'password_confirmation' => 'password123',
    'token' => $token,
]);
$replayResponse = app()->handle($replayRequest);
echo "HTTP Status for replay: {$replayResponse->getStatusCode()}\n\n";

echo "--- STEP 5: Attempt Registration with Expired Token ---\n";
$expiredInvite = TenantInvite::create([
    'tenant_id' => 1,
    'email' => 'expired@example.com',
    'token' => TenantInvite::generateToken(),
    'expires_at' => now()->subDays(1)
]);
$expiredRequest = Request::create('/register', 'POST', [
    'name' => 'Late User',
    'password' => 'password123',
    'password_confirmation' => 'password123',
    'token' => $expiredInvite->token,
]);
$expiredResponse = app()->handle($expiredRequest);
echo "HTTP Status for expired: {$expiredResponse->getStatusCode()}\n";
