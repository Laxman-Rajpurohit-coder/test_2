<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// What database are we connected to?
echo "=== DATABASE CONNECTION ===\n";
echo "Driver: " . config('database.default') . "\n";
$conn = config('database.connections.' . config('database.default'));
echo "Host: " . ($conn['host'] ?? 'N/A') . "\n";
echo "Database: " . ($conn['database'] ?? 'N/A') . "\n";
echo "Port: " . ($conn['port'] ?? 'N/A') . "\n";

// Check failed_jobs
echo "\n=== FAILED JOBS ===\n";
try {
    $failed = DB::table('failed_jobs')->orderBy('failed_at', 'desc')->limit(10)->get();
    if ($failed->isEmpty()) {
        echo "NONE\n";
    } else {
        foreach ($failed as $f) {
            echo "ID: {$f->id} | FAILED_AT: {$f->failed_at}\n";
            echo "PAYLOAD: " . substr($f->payload, 0, 200) . "\n";
            echo "EXCEPTION: " . substr($f->exception, 0, 300) . "\n---\n";
        }
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Check QUEUE_CONNECTION
echo "\n=== QUEUE CONFIG ===\n";
echo "QUEUE_CONNECTION: " . config('queue.default') . "\n";

// Check if there are jobs in the jobs table
echo "\n=== PENDING JOBS ===\n";
try {
    $jobs = DB::table('jobs')->count();
    echo "Pending jobs count: {$jobs}\n";
} catch (\Exception $e) {
    echo "No jobs table (using Redis/sync): " . $e->getMessage() . "\n";
}
