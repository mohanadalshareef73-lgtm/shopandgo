<?php

namespace App\Services;

class WhatsAppMessageContext
{
    public static function businessPhone($phone): ?string
    {
        if (! is_string($phone) || strlen($phone) > 40
            || preg_match('/^(?:\+|00)?[0-9][0-9\s().-]*$/D', $phone) !== 1) {
            return null;
        }
        $number = preg_replace('/[\s().-]/', '', $phone);
        $number = str_starts_with($number, '00') ? substr($number, 2) : ltrim($number, '+');
        return preg_match('/^[1-9]\d{6,14}$/D', $number) === 1 ? $number : null;
    }

    /** Keep linked-device IDs intact; their digits are not a telephone number. */
    public static function recipient(?string $address): ?string
    {
        if (preg_match('/^[1-9]\d{0,31}@lid$/D', $address ?? '') === 1) {
            return $address;
        }
        if (preg_match('/^\+?([1-9]\d{6,14})(?:@s\.whatsapp\.net|@c\.us)?$/D', $address ?? '', $phone) === 1) {
            return $phone[1];
        }
        return null;
    }

    public static function isGroup(array $message): bool
    {
        if (in_array($message['is_group'] ?? false, [true, 1, '1', 'true'], true)) {
            return true;
        }

        // Whatsy sends the participant's phone/LID as from. The conversation
        // must be checked separately, before any trial alias is applied.
        foreach (['from', 'chat_id'] as $field) {
            if (is_string($message[$field] ?? null) && str_ends_with($message[$field], '@g.us')) {
                return true;
            }
        }

        // Older Whatsy deliveries may only retain the chat in a serialized ID.
        return is_string($message['id'] ?? null)
            && preg_match('/^(?:true|false)_[0-9]+(?:-[0-9]+)?@g\.us_[^_]+(?:_.*)?$/D', $message['id']) === 1;
    }
}
