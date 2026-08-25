<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$types = ['image', 'text', 'template', 'audio', 'video', 'document', 'interactive', 'button_reply'];

foreach ($types as $t) {
    $countOld = DB::table('messages')->where('content', 'like', '%"type":"' . $t . '"%')->count();
    
    $countNew = DB::table('messages')->where(function($q) use ($t) {
        $q->where('content', 'like', '%"type":"' . $t . '"%')
          ->orWhere('content', 'like', '%"type": "' . $t . '"%')
          ->orWhere('content', 'like', '%"type"%"' . $t . '"%');
    })->count();

    echo "TYPE: {$t} | OLD MATCH: {$countOld} | NEW MATCH: {$countNew}\n";
}
