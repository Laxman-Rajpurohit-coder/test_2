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
     * @param string|null $assignedUserId
     * @return array{imported: int, updated: int, errors: array}
     */
    public function import(UploadedFile $file, string $tenantId, ?string $assignedUserId = null): array
    {
        $imported = 0;
        $updated = 0;
        $errors = [];

        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            $originalHeaders = fgetcsv($handle, 1000, ',');
            
            if (!$originalHeaders) {
                return ['imported' => 0, 'updated' => 0, 'errors' => ['Row 1: Empty or invalid CSV headers']];
            }

            // Lowercase and strip extra spaces for robust matching, but keep original for custom field keys
            $normalizedHeaders = array_map(function($h) {
                return str_replace([' ', '_', '-'], '', strtolower(trim($h)));
            }, $originalHeaders);

            $phoneIdx = false;
            $nameIdx = false;
            $emailIdx = false;

            // Intelligently match variations using fuzzy matching
            foreach ($normalizedHeaders as $idx => $header) {
                // Match "mobile1", "phonenumber", "contactno", "whatsapp"
                if ($phoneIdx === false && (str_contains($header, 'phone') || str_contains($header, 'mobile') || str_contains($header, 'number') || str_contains($header, 'contact') || str_contains($header, 'whatsapp'))) {
                    $phoneIdx = $idx;
                }
                // Match "name", "fullname", "firstname", "customername"
                else if ($nameIdx === false && str_contains($header, 'name')) {
                    $nameIdx = $idx;
                }
                // Match "email", "emailaddress", "mail"
                else if ($emailIdx === false && (str_contains($header, 'email') || str_contains($header, 'mail'))) {
                    $emailIdx = $idx;
                }
            }

            if ($phoneIdx === false) {
                return ['imported' => 0, 'updated' => 0, 'errors' => ['Row 1: Missing required phone column. Please ensure one column contains "phone", "mobile", or "number" in its header.']];
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

                $matchedHeaders = [];
                if ($phoneIdx !== false) $matchedHeaders[] = $originalHeaders[$phoneIdx];
                if ($nameIdx !== false) $matchedHeaders[] = $originalHeaders[$nameIdx];
                if ($emailIdx !== false) $matchedHeaders[] = $originalHeaders[$emailIdx];

                $customFields = [];
                foreach ($originalHeaders as $idx => $headerName) {
                    if (!in_array($headerName, $matchedHeaders) && !empty($headerName)) {
                        $customFields[$headerName] = $data[$idx] ?? null;
                    }
                }

                try {
                    $contactData = [
                        'name' => $name,
                        'email' => $email,
                        'custom_fields' => $customFields,
                    ];
                    
                    if ($assignedUserId !== null) {
                        $contactData['assigned_user_id'] = $assignedUserId;
                    }

                    $contact = Contact::updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'phone_number' => $normalizedPhone,
                        ],
                        $contactData
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
