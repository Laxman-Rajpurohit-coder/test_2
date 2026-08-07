<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

app(App\Services\TenantResolverService::class)->setActiveTenantId(7);

$templates = App\Models\WhatsappTemplate::all();
foreach ($templates as $t) {
    echo "Template: {$t->name}\n";
    $raw = Illuminate\Support\Facades\DB::table('whatsapp_templates')->where('id', $t->id)->value('components');
    echo "Raw JSON string in DB: " . $raw . "\n";
    echo "PHP Array dump after casting:\n";
    print_r($t->components);
    echo "------------------------\n";
}
