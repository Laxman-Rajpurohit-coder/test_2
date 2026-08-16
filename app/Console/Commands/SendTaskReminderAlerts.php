<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CustomerTask;
use App\Models\Message;
use App\Events\MessageReceived;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendTaskReminderAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Polls open tasks with due reminders, logs an internal alert message in chat, and broadcasts WebSocket update.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for due task reminders...');

        $resolver = app(\App\Services\TenantResolverService::class);

        // Find open tasks that are due across all tenants, where reminder has not been sent yet
        $tasks = CustomerTask::withoutGlobalScope('tenant_isolation')
            ->where('status', '!=', 'resolved')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->whereNull('reminder_sent_at')
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('No due task reminders found.');
            return;
        }

        $processedCount = 0;

        foreach ($tasks as $task) {
            try {
                $resolver->setActiveTenantId((int) $task->tenant_id);
                $this->info("Processing reminder for task: {$task->title} (Type: {$task->type}, Tenant ID: {$task->tenant_id})");

                if ($task->conversation_id) {
                    $conversation = \App\Models\Conversation::withoutGlobalScopes()->find($task->conversation_id);
                    
                    if (!$conversation) {
                        Log::warning("SendTaskReminderAlerts: Conversation ID {$task->conversation_id} not found for task ID: {$task->id}. Marking processed.");
                        $task->update(['reminder_sent_at' => now()]);
                        continue;
                    }

                    if ($task->type === 'auto_message') {
                        // Outbound Scheduled Auto Message to Customer
                        if (empty($conversation->customer_number)) {
                            Log::warning("SendTaskReminderAlerts: Conversation ID {$task->conversation_id} has no customer_number. Skipping auto message for task ID: {$task->id}");
                            $task->update(['reminder_sent_at' => now()]);
                            continue;
                        }

                        $integratedNumber = \App\Models\TenantNumber::where('tenant_id', $task->tenant_id)->first();
                        if ($integratedNumber && !empty($integratedNumber->integrated_number)) {
                            // Create queued outbound database message row
                            $message = Message::create([
                                'id' => (string) Str::uuid(),
                                'tenant_id' => $task->tenant_id,
                                'conversation_id' => $task->conversation_id,
                                'direction' => 'outbound',
                                'content' => json_encode([
                                    'type' => 'template',
                                    'template_name' => $task->template_name,
                                    'template_language' => $task->template_language ?: 'en',
                                    'template_components' => $task->template_components ?: []
                                ]),
                                'status' => 'queued',
                                'is_internal' => false, // Customer-facing!
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            $integratedNumberStr = $integratedNumber->integrated_number;
                            $msg91Payload = \App\Services\Msg91PayloadBuilder::build(
                                $conversation->customer_number,
                                'template',
                                [
                                    'template_name' => $task->template_name,
                                    'template_language' => $task->template_language ?: 'en',
                                    'template_components' => $task->template_components ?: []
                                ],
                                $integratedNumberStr
                            );

                            // Dispatch sending job
                            \App\Jobs\SendMsg91Message::dispatch($message->id, $msg91Payload, (int) $task->conversation_id);
                            
                            try {
                                broadcast(new MessageReceived((int) $task->conversation_id, $message))->toOthers();
                            } catch (\Throwable $e) {
                                Log::warning("SendTaskReminderAlerts: Failed to broadcast WebSocket event: " . $e->getMessage());
                            }
                        } else {
                            Log::error("SendTaskReminderAlerts: Cannot send auto message, no integrated number found for tenant: {$task->tenant_id}");
                        }
                    } else {
                        // Internal Agent Note
                        $message = Message::create([
                            'id' => (string) Str::uuid(),
                            'tenant_id' => $task->tenant_id,
                            'conversation_id' => $task->conversation_id,
                            'direction' => 'outbound',
                            'content' => json_encode([
                                'type' => 'text',
                                'text' => "⚠️ REMINDER: Task \"{$task->title}\" is due now!\nDetails: " . ($task->description ?: 'No additional details.')
                            ]),
                            'status' => 'sent',
                            'is_internal' => true, // Flagged internal so it displays in chat UI but NEVER goes out to customer
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        // Broadcast real-time WebSocket update so the chat inbox UI refreshes
                        try {
                            broadcast(new MessageReceived((int) $task->conversation_id, $message))->toOthers();
                        } catch (\Throwable $e) {
                            Log::warning("SendTaskReminderAlerts: Failed to broadcast WebSocket event: " . $e->getMessage());
                        }
                    }
                }

                // Mark reminder as sent so we don't re-fire
                $task->update([
                    'reminder_sent_at' => now(),
                ]);

                $processedCount++;
            } catch (\Throwable $e) {
                Log::error("SendTaskReminderAlerts: Exception processing task ID: {$task->id}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                // Set reminder_sent_at to prevent toxic retry loop crashing future tasks
                $task->update([
                    'reminder_sent_at' => now(),
                ]);
            }
        }

        $this->info("Processed {$processedCount} reminders successfully.");
    }
}
