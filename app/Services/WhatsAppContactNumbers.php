<?php

namespace App\Services;

use App\Models\CartQuote;
use App\Models\WhatsappMessages;
use Illuminate\Support\Facades\Log;

class WhatsAppContactNumbers
{
    public function __construct(private WhatsAppService $whatsApp)
    {
    }

    public function webhookPhone(array $message, array $value): ?string
    {
        $from = $message['from'] ?? null;
        foreach ($value['contacts'] ?? [] as $contact) {
            if (is_array($contact) && ($contact['wa_id'] ?? null) === $from
                && self::validPhone($contact['phone_number'] ?? null)) {
                return $contact['phone_number'];
            }
        }
        return null;
    }

    public static function validPhone($phone): bool
    {
        return is_string($phone) && preg_match('/^[1-9]\d{6,14}$/D', $phone) === 1;
    }

    public function remember(string $sessionUuid, string $id, string $phone): void
    {
        if (! self::validPhone($phone)) {
            return;
        }
        // The number is for navigation only. Never replace the delivery identity.
        WhatsappMessages::where('session_uuid', $sessionUuid)->where('from', $id)
            ->update(['contact_phone' => $phone, 'contact_lookup_after' => null]);
    }

    public function knownPhone(string $sessionUuid, string $id): ?string
    {
        $phone = WhatsappMessages::where('session_uuid', $sessionUuid)->where('from', $id)
            ->whereNotNull('contact_phone')->latest('id')->value('contact_phone');
        return self::validPhone($phone) ? $phone : null;
    }

    public function hasCart(string $sessionUuid, string $id): bool
    {
        return CartQuote::whereHas('incomingMessage', fn ($query) => $query
            ->where('session_uuid', $sessionUuid)->where('from', $id))->exists();
    }

    public function retryAfterMessage(string $sessionUuid, string $id): void
    {
        if (preg_match('/^[1-9]\d{0,31}@lid$/D', $id) === 1 && $this->hasCart($sessionUuid, $id)) {
            WhatsappMessages::where('session_uuid', $sessionUuid)->where('from', $id)
                ->whereNull('contact_phone')->whereNull('contact_lookup_after')->update(['contact_lookup_after' => now()]);
        }
    }

    public function resolvePending(): array
    {
        $counts = ['resolved' => 0, 'unresolved' => 0, 'failed' => 0];
        $messages = WhatsappMessages::where('contact_lookup_after', '<=', now())
            ->whereNull('contact_phone')->orderBy('id')->limit(500)->get();
        $sessions = [];
        $seen = [];
        foreach ($messages as $message) {
            if (WhatsAppMessageContext::isGroup(['from' => $message->from, 'id' => $message->message_id])
                || preg_match('/^[1-9]\d{0,31}@lid$/D', $message->from) !== 1
                || preg_match('/^[a-fA-F0-9]{32}$/D', $message->session_uuid ?? '') !== 1) {
                $message->update(['contact_lookup_after' => null]);
                continue;
            }
            $key = $message->session_uuid."\n".$message->from;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $known = $this->knownPhone($message->session_uuid, $message->from);
            if ($known) {
                $this->remember($message->session_uuid, $message->from, $known);
                continue;
            }
            $sessions[$message->session_uuid][$message->from] = true;
        }
        foreach ($sessions as $sessionUuid => $ids) {
            foreach (array_chunk(array_keys($ids), 50) as $batch) {
                try {
                    $contacts = $this->whatsApp->resolveContacts($sessionUuid, $batch);
                } catch (\Throwable $e) {
                    WhatsappMessages::where('session_uuid', $sessionUuid)->whereIn('from', $batch)
                        ->whereNull('contact_phone')->update(['contact_lookup_after' => now()->addMinutes(5)]);
                    $counts['failed'] += count($batch);
                    Log::warning('Whatsy customer number lookup retry scheduled', [
                        'session_uuid' => $sessionUuid, 'count' => count($batch), 'exception' => get_class($e)]);
                    continue;
                }
                foreach ($contacts as $id => $phone) {
                    $query = WhatsappMessages::where('session_uuid', $sessionUuid)->where('from', $id)
                        ->whereNull('contact_phone');
                    if ($phone === null) {
                        // A new message during lookup gets another attempt, even after an old null response.
                        $query->where('id', '<=', $messages->max('id'));
                    }
                    // Keep a newer number captured by a webhook while lookup was running.
                    $query->update(['contact_phone' => $phone, 'contact_lookup_after' => null]);
                    $counts[$phone === null ? 'unresolved' : 'resolved']++;
                }
            }
        }
        return $counts;
    }
}
