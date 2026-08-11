<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalizes a phone number to standard E.164 canonical format.
     * Strips spaces, dashes, parentheses, plus signs, and leading zeros.
     * Automatically prepends '91' to 10-digit Indian local numbers.
     */
    public static function normalize(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        // Remove everything except digits
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove leading zeros
        $phone = ltrim($phone, '0');

        // If the number is exactly 10 digits, assume Indian local number and prepend 91
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }

        return $phone;
    }
}
