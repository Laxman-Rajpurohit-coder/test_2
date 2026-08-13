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

        $extension = strtolower($file->getClientOriginalExtension());

        // Validate assigned user ownership if provided
        if ($assignedUserId !== null) {
            $validUser = \App\Models\User::where('id', $assignedUserId)
                ->where('tenant_id', $tenantId)
                ->exists();
            if (!$validUser) {
                $assignedUserId = null;
            }
        }

        try {
            $reader = \Spatie\SimpleExcel\SimpleExcelReader::create($file->getRealPath(), $extension);

            if (in_array($extension, ['csv', 'txt'])) {
                $firstLine = file_get_contents($file->getRealPath(), false, null, 0, 2048) ?: '';
                // Split lines to inspect only the first row
                $firstRow = explode("\n", str_replace("\r", "", $firstLine))[0] ?? '';
                
                $commaCount = substr_count($firstRow, ',');
                $semicolonCount = substr_count($firstRow, ';');
                $tabCount = substr_count($firstRow, "\t");

                $delimiter = ',';
                if ($semicolonCount > $commaCount && $semicolonCount >= $tabCount) {
                    $delimiter = ';';
                } elseif ($tabCount > $commaCount && $tabCount > $semicolonCount) {
                    $delimiter = "\t";
                }
                $reader->useDelimiter($delimiter);
            }

            $rawHeaders = $reader->getHeaders();
        } catch (\Exception $e) {
            return ['imported' => 0, 'updated' => 0, 'errors' => ['Row 1: Empty or invalid file']];
        }

        if (empty($rawHeaders)) {
            return ['imported' => 0, 'updated' => 0, 'errors' => ['Row 1: Empty or invalid CSV/Excel headers']];
        }

        // Clean UTF-8 BOM from headers
        $headers = array_map(function ($h) {
            return preg_replace('/^\xEF\xBB\xBF/', '', trim((string)$h));
        }, $rawHeaders);

        $phoneKey = false;
        $nameKey = false;
        $emailKey = false;
        $consentKey = false;

        // Non-phone columns containing "number" to explicitly exclude
        $excludedNumberKeywords = ['order', 'invoice', 'id', 'account', 'tracking', 'card', 'serial', 'item', 'ticket'];

        // Deterministic Priority Header Matching
        // Priority 1: Exact matches for Phone
        foreach ($headers as $header) {
            $norm = str_replace([' ', '_', '-'], '', strtolower($header));
            if (in_array($norm, ['phone', 'phonenumber', 'mobile', 'mobilenumber', 'whatsapp', 'whatsappnumber', 'contactnumber', 'phone_number', 'mobile_number', 'contact_number'])) {
                $phoneKey = $header;
                break;
            }
        }

        // Priority 2: Safe substring match for Phone (excluding order/invoice numbers)
        if ($phoneKey === false) {
            foreach ($headers as $header) {
                $norm = str_replace([' ', '_', '-'], '', strtolower($header));
                
                // Skip non-phone number columns
                $isExcluded = false;
                foreach ($excludedNumberKeywords as $excluded) {
                    if (str_contains($norm, $excluded)) {
                        $isExcluded = true;
                        break;
                    }
                }
                if ($isExcluded) continue;

                if (str_contains($norm, 'phone') || str_contains($norm, 'mobile') || str_contains($norm, 'whatsapp') || str_contains($norm, 'contact')) {
                    $phoneKey = $header;
                    break;
                }
            }
        }

        // Deterministic matching for Name, Email, Consent
        foreach ($headers as $header) {
            $norm = str_replace([' ', '_', '-'], '', strtolower($header));
            
            if ($nameKey === false && (in_array($norm, ['name', 'fullname', 'contactname']) || str_contains($norm, 'name'))) {
                $nameKey = $header;
            }
            elseif ($emailKey === false && (in_array($norm, ['email', 'emailaddress', 'mail']) || str_contains($norm, 'email'))) {
                $emailKey = $header;
            }
            elseif ($consentKey === false && (in_array($norm, ['consent', 'whatsappconsent', 'optin', 'subscribed']) || str_contains($norm, 'consent') || str_contains($norm, 'optin'))) {
                $consentKey = $header;
            }
        }

        if ($phoneKey === false) {
            return ['imported' => 0, 'updated' => 0, 'errors' => ['Row 1: Missing required phone column. Please ensure one column contains "phone", "mobile", or "whatsapp" in its header.']];
        }

        $rowNum = 2; // Data starts at row 2
        
        $reader->getRows()->each(function(array $rowProperties) use (&$imported, &$updated, &$errors, &$rowNum, $tenantId, $assignedUserId, $phoneKey, $nameKey, $emailKey, $consentKey, $headers) {
            // Clean BOM from row keys
            $cleanRow = [];
            foreach ($rowProperties as $k => $v) {
                $cleanK = preg_replace('/^\xEF\xBB\xBF/', '', trim((string)$k));
                $cleanRow[$cleanK] = $v;
            }

            $rawPhone = $cleanRow[$phoneKey] ?? '';
            $normalizedPhone = $this->normalizePhoneNumber((string)$rawPhone);

            if (empty($normalizedPhone)) {
                if (count($errors) < 100) {
                    $errors[] = "Row {$rowNum}: Missing or invalid phone number.";
                }
                $rowNum++;
                return true; // continue
            }

            $name = $nameKey !== false ? ($cleanRow[$nameKey] ?? null) : null;
            $email = $emailKey !== false ? ($cleanRow[$emailKey] ?? null) : null;

            // Determine whatsapp consent (is_subscribed) - Default true per user instruction
            $isSubscribed = true; 
            if ($consentKey !== false && isset($cleanRow[$consentKey])) {
                $val = strtolower(trim((string)$cleanRow[$consentKey]));
                if (in_array($val, ['0', 'false', 'no', 'n', 'off'])) {
                    $isSubscribed = false;
                }
            }

            $matchedHeaders = array_filter([$phoneKey, $nameKey, $emailKey, $consentKey]);

            $newCustomFields = [];
            foreach ($headers as $headerName) {
                if (!in_array($headerName, $matchedHeaders) && !empty($headerName)) {
                    $newCustomFields[$headerName] = isset($cleanRow[$headerName]) ? trim((string)$cleanRow[$headerName]) : null;
                }
            }

            try {
                $existingContact = Contact::where('tenant_id', $tenantId)
                    ->where('phone_number', $normalizedPhone)
                    ->first();

                $mergedCustomFields = $newCustomFields;
                if ($existingContact && is_array($existingContact->custom_fields)) {
                    $mergedCustomFields = array_merge($existingContact->custom_fields, $newCustomFields);
                }

                // Consent Ratchet: Never downgrade consent from true to false upon import
                $finalIsSubscribed = $isSubscribed;
                if ($existingContact && $existingContact->is_subscribed) {
                    $finalIsSubscribed = true;
                }

                $contactData = [
                    'name' => $name ?: ($existingContact->name ?? null),
                    'email' => $email ?: ($existingContact->email ?? null),
                    'custom_fields' => $mergedCustomFields,
                    'is_subscribed' => $finalIsSubscribed,
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
                Log::error("CSV/Excel Import Error on row {$rowNum}: " . $e->getMessage());
                if (count($errors) < 100) {
                    $errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
            }

            $rowNum++;
        });

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Normalizes a phone number for deduplication.
     * Strips spaces, dashes, parentheses, plus signs, and leading zeros.
     * Automatically prepends '91' to 10-digit local numbers.
     */
    public function normalizePhoneNumber(string $phone): string
    {
        return \App\Support\PhoneNumber::normalize($phone);
    }
}
