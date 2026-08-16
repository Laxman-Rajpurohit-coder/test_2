<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportMsg91Csv extends Command
{
    protected $signature = 'msg91:import {file : Path to the CSV file} {--tenant= : Tenant ID to import records for (defaults to 1)}';
    protected $description = 'Import MSG91 historical logs from CSV idempotently with tenant scoping.';

    public function handle()
    {
        $file = $this->argument('file');
        
        if (!file_exists($file)) {
            $this->error("File not found at: {$file}");
            return Command::FAILURE;
        }

        $tenantId = (int) ($this->option('tenant') ?? 1);
        $tenantExists = DB::table('tenants')->where('id', $tenantId)->exists();
        if (!$tenantExists) {
            $this->error("Tenant ID {$tenantId} does not exist in the database.");
            return Command::FAILURE;
        }

        $this->info("Importing MSG91 history from {$file} for Tenant ID: {$tenantId}...");

        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle);
        
        // Clean headers (remove BOM if exists)
        $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]);
        
        // Map header names to indices
        $headerMap = array_flip(array_map('trim', $headers));
        
        // Verify necessary headers exist
        $required = ['Date Time', 'Whatsapp Number', 'Template', 'Customer Number', 'Delivery Report'];
        foreach ($required as $req) {
            if (!isset($headerMap[$req])) {
                $this->error("Missing required column in CSV: {$req}");
                fclose($handle);
                return Command::FAILURE;
            }
        }

        $count = 0;
        $skipped = 0;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < count($headers)) continue;

                $data = array_combine($headers, array_slice($row, 0, count($headers)));
                
                $dateTime = $data['Date Time'] ?? null;
                $whatsappNumber = $data['Whatsapp Number'] ?? null;
                $requestId = $data['Request ID'] ?? null;
                $template = $data['Template'] ?? null;
                $customerNumber = $data['Customer Number'] ?? null;
                $deliveryReport = strtolower($data['Delivery Report'] ?? 'sent');
                
                if (empty($customerNumber) || $customerNumber === '-') continue;
                
                // Outbound messages have templates, inbound usually don't.
                $direction = (empty($template) || $template === '-') ? 'inbound' : 'outbound';
                
                // Create unique hash to prevent duplicates (Timestamp + Customer + Template + ReqID)
                $hashInput = $dateTime . '|' . $customerNumber . '|' . $template . '|' . $requestId;
                $importHash = hash('sha256', $hashInput);
                
                // Upsert Conversation scoped to tenant
                $conversation = DB::table('conversations')
                    ->where('tenant_id', $tenantId)
                    ->where('customer_number', $customerNumber)
                    ->first();

                if (!$conversation) {
                    $conversationId = DB::table('conversations')->insertGetId([
                        'tenant_id' => $tenantId,
                        'customer_number' => $customerNumber,
                        'channel' => 'whatsapp',
                        'last_message_at' => $dateTime,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $conversationId = $conversation->id;
                    if (strtotime($dateTime) > strtotime($conversation->last_message_at)) {
                        DB::table('conversations')->where('id', $conversationId)->update([
                            'last_message_at' => $dateTime,
                            'updated_at' => now(),
                        ]);
                    }
                }

                // Insert Message if hash doesn't exist (DEDUPLICATION)
                $exists = DB::table('messages')
                    ->where('tenant_id', $tenantId)
                    ->where('import_hash', $importHash)
                    ->exists();
                
                if (!$exists) {
                    DB::table('messages')->insert([
                        'id' => Str::uuid()->toString(),
                        'tenant_id' => $tenantId,
                        'conversation_id' => $conversationId,
                        'channel' => 'whatsapp',
                        'import_hash' => $importHash,
                        'request_id' => $requestId,
                        'direction' => $direction,
                        'status' => $deliveryReport,
                        'content' => json_encode(['template' => $template, 'note' => 'Imported from historical logs']),
                        'vendor_timestamp' => $dateTime,
                        'created_at' => $dateTime, // preserve historical time
                        'updated_at' => now(),
                    ]);
                    $count++;
                } else {
                    $skipped++;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            $this->error("Failed during import: " . $e->getMessage());
            return Command::FAILURE;
        }

        fclose($handle);

        $this->info("Import completed successfully for Tenant ID {$tenantId}!");
        $this->info("Imported: {$count} messages.");
        $this->info("Skipped (Duplicates): {$skipped} messages.");

        return Command::SUCCESS;
    }
}
