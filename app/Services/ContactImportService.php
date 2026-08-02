<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ContactImportService
{
    /**
     * Imports contacts from a CSV file.
     *
     * @param UploadedFile $file
     * @param string $tenantId
     * @return array{imported: int, updated: int, errors: array}
     */
    public function import(UploadedFile $file, string $tenantId): array
    {
        $imported = 0;
        $updated = 0;
        $errors = [];

        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            $headers = fgetcsv($handle, 1000, ',');
            
            if (!$headers) {
                return ['imported' => 0, 'updated' => 0, 'errors' => ['Row 1: Empty or invalid CSV headers']];
            }

            // Lowercase and trim headers for robust matching
            $headers = array_map(function($h) {
                return trim(strtolower($h));
            }, $headers);

            $phoneIdx = array_search('phone_number', $headers);
            $nameIdx = array_search('name', $headers);
            $emailIdx = array_search('email', $headers);

            if ($phoneIdx === false) {
                return ['imported' => 0, 'updated' => 0, 'errors' => ['Row 1: Missing required column "phone_number"']];
            }

            $rowNum = 2; // Data starts at row 2
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $rawPhone = $data[$phoneIdx] ?? '';
                $normalizedPhone = $this->normalizePhoneNumber($rawPhone);

                if (empty($normalizedPhone)) {
                    $errors[] = "Row {$rowNum}: Missing or invalid phone number.";
                    $rowNum++;
                    continue;
                }

                $name = $nameIdx !== false ? ($data[$nameIdx] ?? null) : null;
                $email = $emailIdx !== false ? ($data[$emailIdx] ?? null) : null;

                $customFields = [];
                foreach ($headers as $idx => $headerName) {
                    if (!in_array($headerName, ['phone_number', 'name', 'email']) && !empty($headerName)) {
                        $customFields[$headerName] = $data[$idx] ?? null;
                    }
                }

                try {
                    $contact = Contact::updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'phone_number' => $normalizedPhone,
                        ],
                        [
                            'name' => $name,
                            'email' => $email,
                            'custom_fields' => $customFields,
                        ]
                    );

                    if ($contact->wasRecentlyCreated) {
                        $imported++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    Log::error("CSV Import Error on row {$rowNum}: " . $e->getMessage());
                    $errors[] = "Row {$rowNum}: Database error.";
                }

                $rowNum++;
            }
            fclose($handle);
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Normalizes a phone number for deduplication.
     * Strips spaces, dashes, parentheses, and leading zeros.
     */
    public function normalizePhoneNumber(string $phone): string
    {
        // Remove everything except digits and plus sign
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Remove leading plus sign if present
        if (strpos($phone, '+') === 0) {
            $phone = substr($phone, 1);
        }

        // Remove leading zeros
        $phone = ltrim($phone, '0');

        return $phone;
    }
}
