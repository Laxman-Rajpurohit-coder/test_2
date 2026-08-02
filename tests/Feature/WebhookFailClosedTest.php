<?php

namespace Tests\Feature;

use App\Jobs\ProcessMsg91Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookFailClosedTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_job_throws_exception_and_fails_when_unmapped_number_is_received()
    {
        $payload = [
            'direction' => 0,
            'integratedNumber' => '910000000000', // Unmapped number
            'mobile' => '919876543210',
            'text' => 'Hello',
        ];

        $job = new ProcessMsg91Webhook($payload);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("SECURITY ABORT: Webhook received for unmapped integrated number 910000000000. Failing closed.");

        // If the exception was swallowed (the bug before the fix), this would pass silently and the test would fail because no exception was thrown.
        // Because we now rethrow it, PHPUnit will catch it and pass the test.
        $job->handle();
    }
}
