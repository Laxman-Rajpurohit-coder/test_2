<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\DTOs\InboundMessageContext;
use App\Models\Conversation;
use App\Services\BotResponderPipeline;
use Illuminate\Support\Str;
use Modules\AiBot\Models\AiBotSetting;
use Modules\AiBot\Responders\AiBotResponder;

$pipeline = app(BotResponderPipeline::class);
$aiResponder = app(AiBotResponder::class);

echo "=== AI BOT MODULE & PIPELINE INTEGRATION TEST ===\n";

// 1. Check Priority 90 registration
$priority = $aiResponder->priority();
echo "1. AiBotResponder Priority: {$priority} " . ($priority === 90 ? "PASS ✅" : "FAIL ❌") . "\n";

// 2. Test Human Escalation Keyword Trigger
$setting = AiBotSetting::updateOrCreate(
    ['tenant_id' => 1],
    [
        'provider'                 => 'openai',
        'api_key'                  => 'sk-test-mock-key-12345',
        'model_or_chatflow_id'     => 'gpt-4o-mini',
        'is_active'                => true,
        'human_escalation_enabled' => true,
    ]
);

$conv = Conversation::create([
    'tenant_id'          => 1,
    'customer_number'    => '919876543266',
    'last_message_at'    => now(),
    'is_human_escalated' => false,
]);

$context = new InboundMessageContext(
    tenantId: 1,
    conversationId: $conv->id,
    customerNumber: '919876543266',
    messageText: 'I need to speak with a human agent'
);

$handled = $aiResponder->handle($context);
$conv->refresh();

echo "2. Human Escalation Keyword Handled: " . ($handled ? "PASS ✅" : "FAIL ❌") . "\n";
echo "3. Conversation Marked Human Escalated: " . ($conv->is_human_escalated ? "PASS ✅" : "FAIL ❌") . "\n";

// 3. Verify Human-Escalated Conversation Skips AI
$contextNext = new InboundMessageContext(
    tenantId: 1,
    conversationId: $conv->id,
    customerNumber: '919876543266',
    messageText: 'What are your store hours?'
);

$handledNext = $aiResponder->handle($contextNext);
echo "4. Human Escalated Conversation Skips AI Generation: " . (!$handledNext ? "PASS ✅" : "FAIL ❌") . "\n";

// Cleanup
$conv->delete();
$setting->delete();
