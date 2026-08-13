<?php

namespace App\Services;

use App\Models\Contact;
use App\Support\PhoneNumber;
use Illuminate\Validation\ValidationException;

class ContactIngestionService
{
    /**
     * Centralized Contact Ingestion Logic with Consent Ratchet Enforcement.
     *
     * @param int $tenantId
     * @param array $payload Must contain 'phone_number', optional 'name', 'email', 'custom_fields', 'whatsapp_consent', 'source'
     * @return Contact
     * @throws ValidationException
     */
    public function ingest(int $tenantId, array $payload): Contact
    {
        $normalizedPhone = PhoneNumber::normalize($payload['phone_number'] ?? '');
        if (empty($normalizedPhone)) {
            throw ValidationException::withMessages([
                'phone_number' => ['Invalid phone number format.'],
            ]);
        }

        // 2. Consent handling: Default to true if omitted/null when filling forms
        $rawConsent = $payload['whatsapp_consent'] ?? true;
        $requestedConsent = filter_var($rawConsent, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($requestedConsent === null) {
            $requestedConsent = true;
        }

        // 3. Existing contact lookup
        $existingContact = Contact::where('tenant_id', $tenantId)
            ->where('phone_number', $normalizedPhone)
            ->first();

        // 4. Consent Ratchet: Never downgrade from true to false
        $finalIsSubscribed = $requestedConsent;
        if ($existingContact && $existingContact->is_subscribed) {
            $finalIsSubscribed = true;
        }

        $customFields = $payload['custom_fields'] ?? [];
        $source = $customFields['Source'] ?? ($payload['source'] ?? 'Public API');
        if (isset($payload['message'])) {
            $customFields['Message'] = $payload['message'];
        }
        $customFields['Source'] = $source;

        if ($existingContact && is_array($existingContact->custom_fields)) {
            // Filter out null/empty-string custom fields while preserving 0, "0", and false values!
            $nonNullIncoming = array_filter($customFields, fn ($value) => $value !== null && $value !== '');
            $customFields = array_merge($existingContact->custom_fields, $nonNullIncoming);
        }

        $resolvedName = filled($payload['name'] ?? null) ? $payload['name'] : ($existingContact->name ?? null);
        $resolvedEmail = filled($payload['email'] ?? null) ? $payload['email'] : ($existingContact->email ?? null);

        // 6. DB Mutation (updateOrCreate)
        return Contact::updateOrCreate(
            [
                'tenant_id'    => $tenantId,
                'phone_number' => $normalizedPhone,
            ],
            [
                'name'          => $resolvedName,
                'email'         => $resolvedEmail,
                'custom_fields' => $customFields,
                'is_subscribed' => $finalIsSubscribed,
            ]
        );
    }
}
