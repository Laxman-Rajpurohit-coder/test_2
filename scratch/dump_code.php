<?php
$files = [
    'app/Services/TenantResolverService.php',
    'app/Services/Msg91PayloadBuilder.php',
    'app/Http/Controllers/ChatController.php',
    'app/Services/OutboundReplyService.php',
    'app/Responders/KeywordBotResponder.php',
    'modules/FlowBuilder/src/Services/FlowExecutionService.php',
    'modules/AiBot/src/Responders/AiBotResponder.php',
    'modules/AiBot/src/Services/AiBotService.php',
    'database/migrations/2026_07_30_065132_add_ai_fallback_count_to_conversations_table.php'
];
$out = "---\nrequest_feedback: true\nsummary: \"Full Source Code for CodeRabbit Architectural Fixes\"\nuser_facing: true\n---\n\n";
$out .= "# Architectural Fixes Source Code\n\n";
foreach ($files as $f) {
    $out .= "## " . basename($f) . "\n```php\n" . file_get_contents(__DIR__ . '/../' . $f) . "\n```\n\n";
}
file_put_contents('C:/Users/msanj/.gemini/antigravity/brain/64d8ef90-cda2-4b87-9194-f911d97141f4/architectural_fixes_code.md', $out);
echo "Done!\n";
