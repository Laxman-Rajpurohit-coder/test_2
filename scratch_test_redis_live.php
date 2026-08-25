<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "REDIS_URL: " . env('REDIS_URL') . "\n";
    echo "REDISHOST: " . env('REDISHOST') . "\n";
    echo "REDIS_HOST: " . env('REDIS_HOST') . "\n";
    echo "REDIS_CLIENT: " . config('database.redis.client') . "\n";
    echo "SESSION_DRIVER: " . config('session.driver') . "\n";
    echo "CACHE_DRIVER: " . config('cache.default') . "\n";
    
    $ping = \Illuminate\Support\Facades\Redis::ping();
    echo "REDIS PING RESULT: " . var_export($ping, true) . "\n";
} catch (\Throwable $e) {
    echo "REDIS ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "TRACE:\n" . $e->getTraceAsString() . "\n";
}
