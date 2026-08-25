<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Contact;
use Illuminate\Support\Facades\Auth;

// Login as user to establish session auth context
$user = User::first();
if ($user) {
    Auth::login($user);
}

echo "AUTHENTICATED USER: " . (Auth::check() ? Auth::user()->id : 'GUEST') . "\n";
echo "ACTIVE TENANT ID: " . app(\App\Services\TenantResolverService::class)->getActiveTenantId() . "\n";

try {
    $req = \Illuminate\Http\Request::create('/contacts/919413819555', 'GET');
    $req->headers->set('X-Inertia', 'true');
    $controller = new \App\Http\Controllers\ContactController();
    $res = $controller->show($req, '919413819555');
    echo "SUCCESS RESP: " . get_class($res) . "\n";
} catch (\Throwable $e) {
    echo "EXCEPTION THROWN: " . $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
