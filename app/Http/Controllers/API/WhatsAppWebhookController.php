<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AiRoles;
use App\Models\ReplayMessages;
use App\Models\WhatsappMessages;
use App\Services\GeminiService;
use App\Services\CartQuoteService;
use App\Services\QuoteUrlExtractor;
use App\Services\WhatsAppService;
use App\Services\WhatsAppMessageContext;
use App\Services\CartWhatsAppReplies;
use App\Services\WhatsAppContactNumbers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    private GeminiService $gemini;
    private WhatsAppService $whatsApp;

    public function __construct(GeminiService $gemini, WhatsAppService $whatsApp,
        private CartQuoteService $quotes, private QuoteUrlExtractor $quoteUrls)
    {
        $this->gemini = $gemini;
        $this->whatsApp = $whatsApp;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Entry point
    // ─────────────────────────────────────────────────────────────────────────

    public function receive(Request $request)
    {
        // ── 1. Authenticate ──────────────────────────────────────────────────
        $receivedToken = $request->query('token');
        $expectedToken = config('services.whatsapp.webhook_token');

        if (! is_string($expectedToken) || strlen($expectedToken) < 32 || ! is_string($receivedToken)
            || ! hash_equals($expectedToken, $receivedToken)) {
            Log::warning('WhatsApp webhook: unauthorized token');
            return response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        // ── 2. Parse body ────────────────────────────────────────────────────
        $body = json_decode($request->getContent(), true);

        if (! is_array($body) || empty($body)) {
            Log::error('WhatsApp webhook: empty or invalid JSON body');
            return response()->json(['status' => false, 'error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        Log::info('WhatsApp webhook received');

        // ── 3. Process each message ──────────────────────────────────────────
        try {
            foreach ($body['entry'] ?? [] as $entry) {
                $sessionUuid = $entry['id'] ?? null;
                foreach ($entry['changes'] ?? [] as $change) {
                    foreach ($change['value']['messages'] ?? [] as $message) {
                        if (! is_string($sessionUuid) || preg_match('/^[a-fA-F0-9]{32}$/D', $sessionUuid) !== 1) {
                            return response()->json(['status' => false, 'error' => 'Invalid receiving Session ID'],
                                Response::HTTP_BAD_REQUEST);
                        }
                        $sender = $message['from'] ?? null;
                        $buttonReply = ($message['type'] ?? null) === 'interactive'
                            && data_get($message, 'interactive.type') === 'button_reply';
                        if (! $buttonReply && is_string($sender) && ! WhatsAppMessageContext::isGroup($message)
                            && $this->quotes->allowsWhatsAppSender($sender)) {
                            $numbers = app(WhatsAppContactNumbers::class);
                            if ($number = $numbers->webhookPhone($message, $change['value'])) {
                                $numbers->remember($sessionUuid, $this->quotes->whatsAppRecipient($sender), $number);
                            }
                        }
                        if ($buttonReply && str_starts_with((string) data_get($message, 'interactive.button_reply.id'), 'sg:')) {
                            // أزرار المنظومة (وصّلهالي، توقف، نبي بديل…)
                            try {
                                \App\Services\SgButtons::handle($message, $sessionUuid, app(WhatsAppContactNumbers::class)->webhookPhone($message, $change['value']));
                            } catch (\Throwable $e) {
                                report($e);
                            }
                        } elseif ($buttonReply) {
                            // Durable receipt only: no remote requests or cart row locks here.
                            app(CartWhatsAppReplies::class)->receive($message, $sessionUuid,
                                app(WhatsAppContactNumbers::class)->webhookPhone($message, $change['value']));
                        } else {
                            $this->handleMessage($message, $change['value'], $sessionUuid);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook fatal error', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => false, 'error' => 'Internal error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json(['status' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Per-message handling
    // ─────────────────────────────────────────────────────────────────────────

    private function handleMessage(array $message, array $value, string $sessionUuid): void
    {
        $messageId = $message['id'] ?? null;
        $from = $message['from'] ?? null;
        $type = $message['type'] ?? 'unknown';

        // Only handle text messages; skip images, audio, etc.
        if ($type !== 'text') {
            Log::info('Skipping non-text message', ['type' => $type, 'from' => $from]);
            return;
        }

        $text = $message['text']['body'] ?? null;

        if (! is_string($text) || $text === '' || ! is_string($messageId) || $messageId === ''
            || ! is_string($from) || $from === '') {
            Log::warning('Empty text body, skipping', ['from' => $from]);
            return;
        }

        // Ignore groups and restricted-trial callbacks before saving messages,
        // calling Gemini or creating paid provider requests.
        if (WhatsAppMessageContext::isGroup($message)) {
            // نلتقط الـ ID متاع القروب لما حد يكتب فيه #حجز (باش نبعتوله تأكيد الحجوزات)
            try {
                $gid = collect([$message['chat_id'] ?? null, $message['group_id'] ?? null, $from])->first(fn ($v) => is_string($v) && str_ends_with($v, '@g.us'));
                if (!$gid && preg_match('/(\d{10,}(?:-\d+)?@g\.us)/', json_encode($message), $mm)) {
                    $gid = $mm[1]; // أحياناً الـ ID مخبي داخل رقم الرسالة
                }
                Log::info('WhatsApp group seen', ['gid' => $gid, 'keys' => array_keys($message)]);
                // 🧪 نسجلو بس القروب اللي انكتب فيه #حجز (قروب الحجز) — باقي القروبات ما نحفظوهمش
                if (str_contains($text, '#حجز') || str_contains(mb_strtolower($text), '#booking')) {
                    $recent = array_filter((array) \App\Services\AppSettings::get('wa_groups_recent', []), fn ($g) => !empty($g['tag']));
                    $rname = $message['chat_name'] ?? $message['group_name'] ?? $message['group']['name'] ?? ($value['group']['subject'] ?? null);
                    $rkey = $gid ?: ('?' . mb_substr((string) $from, 0, 40));
                    $recent[$rkey] = ['id' => $gid, 'raw' => mb_substr((string) $from, 0, 60), 'name' => is_string($rname) ? mb_substr($rname, 0, 60) : null, 'session' => $sessionUuid, 'tag' => true, 'at' => now()->toDateTimeString()];
                    uasort($recent, fn ($x, $y) => strcmp($y['at'] ?? '', $x['at'] ?? ''));
                    \App\Services\AppSettings::put(['wa_groups_recent' => array_slice($recent, 0, 10, true)]);
                }
                if ($gid && (str_contains($text, '#حجز') || str_contains(mb_strtolower($text), '#booking'))) {
                    $seen = (array) \App\Services\AppSettings::get('wa_groups_seen', []);
                    $name = $message['chat_name'] ?? $message['group_name'] ?? $message['group']['name'] ?? ($value['group']['subject'] ?? null);
                    $seen[$gid] = ['id' => $gid, 'name' => is_string($name) ? mb_substr($name, 0, 60) : null, 'session' => $sessionUuid, 'at' => now()->toDateTimeString()];
                    \App\Services\AppSettings::put(['wa_groups_seen' => $seen]);
                    \App\Services\AlertService::push('backup', '👥 تم التقاط قروب واتساب', ($name ?: $gid) . ' — افتح الإعدادات وزيده لأرقام الحجز', route('sg.settings'));
                }
            } catch (\Throwable $e) {
                report($e);
            }
            Log::info('WhatsApp group message ignored', [
                'sender' => $from, 'message_id' => $messageId, 'chat_id' => $message['chat_id'] ?? null,
            ]);
            return;
        }
        if (! $this->quotes->allowsWhatsAppSender($from)
            || ($this->quotes->whatsappTestMode() && ! $this->quoteUrls->extract($text))) {
            Log::info('WhatsApp message ignored', ['sender' => $from]);
            return;
        }

        // Store the known customer phone for operator follow-up and replies.
        $from = $this->quotes->whatsAppRecipient($from);
        $numbers = app(WhatsAppContactNumbers::class);
        $contactPhone = $numbers->webhookPhone($message, $value) ?? $numbers->knownPhone($sessionUuid, $from);
        $receivingPhone = WhatsAppMessageContext::businessPhone(data_get($value, 'metadata.display_phone_number'));
        $sessionName = data_get($value, 'metadata.phone_number_id');
        $sessionName = is_string($sessionName) && trim($sessionName) !== '' && mb_strlen($sessionName) <= 255
            ? $sessionName : null;

        // Duplicate guard
        if ($existing = WhatsappMessages::where('message_id', $messageId)
            ->where(fn ($query) => $query->where('session_uuid', $sessionUuid)->orWhereNull('session_uuid'))->first()) {
            if ($existing->session_uuid === null) {
                $existing->update(['session_uuid' => $sessionUuid, 'contact_phone' => $contactPhone,
                    'contact_lookup_after' => ! $contactPhone && str_ends_with($from, '@lid') ? now() : null]);
            }
            $source = [];
            if ($existing->receiving_phone === null && $receivingPhone !== null) $source['receiving_phone'] = $receivingPhone;
            if ($existing->session_name === null && $sessionName !== null) $source['session_name'] = $sessionName;
            if ($source) $existing->update($source);
            $urls = $this->quoteUrls->extract($existing->message);
            if ($urls) {
                $this->quotes->handleIncoming($existing, $urls);
            }
            Log::info('Duplicate message skipped', ['message_id' => $messageId]);
            return;
        }

        $contacts = $value['contacts'] ?? [];
        $displayPhone = $value['metadata']['display_phone_number'] ?? $from;
        $name = $contacts[0]['profile']['name'] ?? 'Unknown';
        $urls = $this->quoteUrls->extract($text);
        $needsLookup = ! $contactPhone && str_ends_with($from, '@lid')
            && ($urls || $numbers->hasCart($sessionUuid, $from));

        // ── Persist incoming message ─────────────────────────────────────────
        $incomingMessage = WhatsappMessages::create([
            'message_id' => $messageId,
            'session_uuid' => $sessionUuid,
            'session_name' => $sessionName,
            'receiving_phone' => $receivingPhone,
            'contact_phone' => $contactPhone,
            'contact_lookup_after' => $needsLookup ? now() : null,
            'from' => $from,
            'phone' => $displayPhone,
            'name' => $name,
            'type' => $type,
            'message' => $text,
            'timestamp' => now(),
            'is_reply' => false,
        ]);

        Log::info('Incoming message saved', ['id' => $incomingMessage->id, 'from' => $from]);

        if ($urls) {
            $this->quotes->handleIncoming($incomingMessage, $urls);
            return;
        }

        // ── Load AI roles (cached for 1 hour) ────────────────────────────────
        $roles = cache()->remember('ai_roles', 3600, fn() => AiRoles::all());

        // ── Load conversation history — oldest first ──────────────────────────
        $history = WhatsappMessages::where('from', $from)
            ->where('session_uuid', $sessionUuid)
            ->with('replyMessage')
            ->latest()
            ->limit(15)
            ->get()
            ->reverse()
            ->values();

        // ── Get AI reply ─────────────────────────────────────────────────────
        $aiReply = $this->gemini->respond($text, $roles, $history, $name);

        if ($aiReply === null) {
            Log::warning('Gemini returned no reply, skipping send', ['from' => $from]);
            return;
        }

        // ── Persist AI reply ─────────────────────────────────────────────────
        ReplayMessages::create([
            'message' => $aiReply,
            'whatsapp_messages_id' => $incomingMessage->id,
        ]);

        // ── Send via WhatsApp ─────────────────────────────────────────────────
        $sent = $this->whatsApp->sendMessage($from, $aiReply, $sessionUuid);

        if ($sent) {
            $incomingMessage->update(['is_reply' => true]);
            Log::info('✅ Reply sent and marked', ['to' => $from]);
        } else {
            Log::error('Failed to send WhatsApp reply', ['to' => $from]);
        }
    }
}
