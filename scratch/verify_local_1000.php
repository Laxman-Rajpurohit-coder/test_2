<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tenant;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;

$tenant = Tenant::first();
app(\App\Services\TenantResolverService::class)->setActiveTenantId($tenant->id);

$contactsCount = Contact::count();
$conversationsCount = Conversation::count();
$messagesCount = Message::count();

echo "=== LOCAL DATABASE TOTALS ===\n";
echo "Total Contacts: " . number_format($contactsCount) . "\n";
echo "Total Conversations: " . number_format($conversationsCount) . "\n";
echo "Total Messages: " . number_format($messagesCount) . "\n";

// Test conversation pagination performance
$startTime = microtime(true);
$conversations = Conversation::orderBy('last_message_at', 'desc')->paginate(20);
$elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

echo "Paginated 20 Conversations from {$conversationsCount} total records in {$elapsedMs} ms!\n";
