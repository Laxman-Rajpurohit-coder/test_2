<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class TestRedisCommand extends Command
{
    protected $signature = 'test:redis';
    protected $description = 'Test Redis connection and performance';

    public function handle()
    {
        $this->info("Checking Redis connection...");
        try {
            $pong = Redis::ping();
            $this->info("SUCCESS: Redis::ping() returned: " . json_encode($pong));

            Cache::store('redis')->put('test_key', 'hello_redis', 60);
            $val = Cache::store('redis')->get('test_key');
            $this->info("SUCCESS: Cache::store('redis') returned: " . $val);
        } catch (\Throwable $e) {
            $this->error("REDIS ERROR: " . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
}
