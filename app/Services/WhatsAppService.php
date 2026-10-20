<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppService
{
    /** Read the account's current numbers from Whatsy; never infer IDs from names. */
    public function sessions(): array
    {
        $token = config('services.whatsapp.api_token');
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('لم يتم ضبط مفتاح Whatsy API.');
        }
        try {
            $response = Http::withHeaders(['X-API-Key' => $token])->acceptJson()
                ->connectTimeout(5)->timeout(10)
                ->get(rtrim(config('services.whatsapp.api_url'), '/').'/sessions');
        } catch (\Throwable $e) {
            Log::warning('Whatsy session list unavailable', ['exception' => get_class($e)]);
            throw new \RuntimeException('تعذر الاتصال بـ Whatsy. حاول تحديث القائمة مرة أخرى.');
        }
        if (! $response->successful()) {
            Log::warning('Whatsy session list rejected', ['status' => $response->status()]);
            throw new \RuntimeException('تعذر جلب أرقام واتساب من Whatsy. تحقق من مفتاح API ثم حاول مرة أخرى.');
        }

        $sessions = $response->json('sessions');
        if (! is_array($sessions) || array_values($sessions) !== $sessions) {
            throw new \RuntimeException('استجابة قائمة أرقام Whatsy غير صالحة. حاول مرة أخرى.');
        }
        $numbers = [];
        foreach ($sessions as $session) {
            if (! is_array($session) || ! is_string($session['uuid'] ?? null)
                || preg_match('/^[a-fA-F0-9]{32}$/D', $session['uuid']) !== 1
                || ! is_string($session['name'] ?? null) || $session['name'] === ''
                || isset($numbers[$session['uuid']])) {
                throw new \RuntimeException('استجابة قائمة أرقام Whatsy غير صالحة. حاول مرة أخرى.');
            }
            $numbers[$session['uuid']] = [
                'uuid' => $session['uuid'], 'name' => $session['name'],
                'status' => is_string($session['status'] ?? null) ? $session['status'] : 'unknown',
            ];
        }

        return array_values($numbers);
    }

    /** Customer lookup uses the receiving session, never the current main sender. */
    public function resolveContacts(string $sessionUuid, array $ids): array
    {
        if (preg_match('/^[a-fA-F0-9]{32}$/D', $sessionUuid) !== 1
            || count($ids) < 1 || count($ids) > 50 || array_values($ids) !== $ids) {
            throw new \InvalidArgumentException('Invalid Whatsy contact lookup batch.');
        }
        foreach ($ids as $id) {
            if (! is_string($id) || preg_match('/^[1-9]\d{0,31}@lid$/D', $id) !== 1) {
                throw new \InvalidArgumentException('Contact lookup requires linked WhatsApp IDs.');
            }
        }
        if (count(array_unique($ids)) !== count($ids)) {
            throw new \InvalidArgumentException('Duplicate Whatsy contact lookup IDs.');
        }
        $response = Http::withHeaders(['X-API-Key' => config('services.whatsapp.api_token')])
            ->acceptJson()->asJson()->connectTimeout(5)->timeout(15)
            ->post(rtrim(config('services.whatsapp.api_url'), '/').'/contacts/resolve',
                ['session_uuid' => $sessionUuid, 'ids' => $ids]);
        if (! $response->successful()) {
            Log::warning('Whatsy customer number lookup failed', ['status' => $response->status(), 'session_uuid' => $sessionUuid]);
            throw new \RuntimeException('Whatsy contact lookup failed.');
        }
        $contacts = $response->json('contacts');
        if (! is_array($contacts) || array_values($contacts) !== $contacts) {
            throw new \RuntimeException('Invalid Whatsy contact lookup response.');
        }
        $result = [];
        foreach ($contacts as $contact) {
            $id = is_array($contact) ? ($contact['id'] ?? null) : null;
            if (! is_string($id) || ! in_array($id, $ids, true) || array_key_exists($id, $result)
                || ! array_key_exists('phone_number', $contact)
                || ($contact['phone_number'] !== null && ! WhatsAppContactNumbers::validPhone($contact['phone_number']))) {
                throw new \RuntimeException('Invalid Whatsy contact lookup response.');
            }
            $result[$id] = $contact['phone_number'];
        }
        if (count($result) !== count($ids)) {
            throw new \RuntimeException('Incomplete Whatsy contact lookup response.');
        }
        return $result;
    }

    /** All outbound requests explicitly select a session; never allow provider failover. */
    public function send(array $body, ?string $sessionUuid = null, int $timeout = 30, int $attempts = 1,
        ?string $idempotencyKey = null): Response
    {
        if (array_key_exists('buttons', $body)) {
            $this->validateButtons($body['buttons']);
            if (trim($body['message'] ?? '') === '' && trim($body['buttons_text'] ?? '') === '') {
                $body['buttons_text'] = 'اختر الإجراء المطلوب.';
            }
        }
        if ($sessionUuid === null) {
            $sessionUuid = Setting::find(1)?->whatsappMainSessionUuid()
                ?? config('services.whatsapp.main_session_uuid');
        }
        if (! is_string($sessionUuid) || preg_match('/^[a-fA-F0-9]{32}$/D', $sessionUuid) !== 1) {
            Log::warning('WhatsApp send blocked: selected number has no valid Whatsy Session ID');
            throw new \RuntimeException('Configure a valid Whatsy Session ID for the selected WhatsApp number.');
        }

        $headers = ['X-API-Key' => config('services.whatsapp.api_token')];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }
        $response = Http::withHeaders($headers)
            ->acceptJson()->asJson()->connectTimeout(10)->timeout($timeout)
            ->retry($attempts, 2000, fn (\Exception $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && $e->response->serverError()), false)
            ->post(rtrim(config('services.whatsapp.api_url'), '/').'/message',
                array_merge($body, ['session_uuid' => $sessionUuid]));

        if (! $response->successful()) {
            Log::warning('WhatsApp session send failed', [
                'session_uuid' => $sessionUuid, 'status' => $response->status(),
                'reason' => [400 => 'Invalid input, buttons or unavailable session', 401 => 'Invalid API key',
                    403 => 'Quota exceeded', 413 => 'PDF exceeds 7 MB'][$response->status()] ?? 'Provider error',
            ]);
        }

        return $response;
    }

    private function validateButtons($buttons): void
    {
        if (! is_array($buttons) || array_values($buttons) !== $buttons || ! in_array(count($buttons), [2, 3], true)) {
            throw new \InvalidArgumentException('Whatsy requires two or three reply buttons.');
        }
        $ids = $titles = [];
        foreach ($buttons as $button) {
            $id = is_array($button) ? ($button['id'] ?? null) : null;
            $title = is_array($button) ? ($button['title'] ?? null) : null;
            if (! is_string($id) || trim($id) === '' || mb_strlen($id) > 256
                || ! is_string($title) || trim($title) === '' || mb_strlen($title) > 20
                || in_array($id, $ids, true) || in_array($title, $titles, true)) {
                throw new \InvalidArgumentException('Reply button IDs and titles must be unique and within Whatsy limits.');
            }
            $ids[] = $id;
            $titles[] = $title;
        }
    }

    /**
     * Send a text message to a WhatsApp user via the configured API.
     *
     * @param  string  $recipient  The WhatsApp ID / phone of the recipient
     * @param  string  $message  The text to send
     * @return bool true on success, false on failure
     */
    public function sendMessage(string $recipient, string $message, ?string $sessionUuid = null, array $buttons = [],
        ?string $imagePath = null, ?string $idempotencyKey = null): bool
    {
        $body = [
            'phone' => [ltrim($recipient, '+')],
            'message' => $message,
        ];
        if ($buttons) {
            $body['buttons'] = $buttons;
            $body['buttons_text'] = 'يرجى اختيار طريقة الدفع:';
        }

        Log::info('📤 WhatsApp send request', ['recipient' => $recipient]);

        try {
            if ($imagePath !== null) {
                $body['image_base64'] = $this->replyImageBase64($imagePath);
            }
            $response = $this->send($body, $sessionUuid, $imagePath ? 60 : 30, 1, $idempotencyKey);

            if ($response->successful()) {
                Log::info('WhatsApp message accepted', ['status' => $response->status(),
                    'queued' => $response->status() === 202]);

                return true;
            }

            Log::error('❌ WhatsApp API error', [
                'status' => $response->status(),
            ]);

            return false;

        } catch (\Throwable $e) {
            Log::error('❌ WhatsApp send exception', [
                'exception' => get_class($e),
            ]);

            return false;
        }
    }

    public function sendDocument(string $recipient, string $path, string $filename, string $caption,
        ?string $sessionUuid = null, array $buttons = []): bool
    {
        if (! is_file($path) || filesize($path) > 7000000) {
            throw new CartQuoteException('Quotation PDF exceeds the WhatsApp 7 MB limit or is missing');
        }
        $bytes = file_get_contents($path);
        if (! is_string($bytes) || ! str_starts_with($bytes, '%PDF-')) {
            throw new CartQuoteException('Quotation document is not a PDF');
        }
        if ($buttons) {
            $this->validateButtons($buttons);
        }
        try {
            $body = [
                'phone' => [ltrim($recipient, '+')], 'file_base64' => base64_encode($bytes),
                'file_name' => $filename, 'message' => $caption,
            ];
            if ($buttons) {
                $body['buttons'] = $buttons;
            }
            $response = $this->send($body, $sessionUuid, 60);
            Log::info('WhatsApp quotation document request', ['recipient' => $recipient,
                'status' => $response->status(), 'queued' => $response->status() === 202]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('WhatsApp document request failed', ['exception' => get_class($e)]);

            return false;
        }
    }

    private function replyImageBase64(string $path): string
    {
        if (! preg_match('~^whatsapp/reply-images/[a-f0-9-]{36}\.(?:jpg|png)$~D', $path)
            || ! Storage::disk('local')->exists($path) || Storage::disk('local')->size($path) > 5 * 1024 * 1024) {
            throw new \RuntimeException('Reply image is missing or invalid.');
        }
        $bytes = Storage::disk('local')->get($path);
        $image = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
        if (! $image || ! in_array($image['mime'], ['image/jpeg', 'image/png'], true)) {
            throw new \RuntimeException('Reply image must be a JPEG or PNG.');
        }

        return base64_encode($bytes);
    }

    /**
     * 👥 يجيب قروبات واتساب من Whatsy لكل رقم (session). ما عندناش اسم الـ endpoint بالضبط،
     * فنجربو المسارات المعروفة ونلقطو أي ID ينتهي بـ @g.us من الرد.
     */
    public function groups(): array
    {
        $api = rtrim((string) config('services.whatsapp.api_url'), '/');
        $h = ['X-API-Key' => (string) config('services.whatsapp.api_token')];
        $out = [];
        $errs = [];
        try {
            $sessions = $this->sessions();
        } catch (\Throwable $e) {
            $sessions = [];
            $errs[] = 'sessions: ' . $e->getMessage();
        }
        foreach ($sessions as $ss) {
            $u = $ss['uuid'];
            $found = false;
            // /sessions/{uuid}/groups هو الأقرب (ما رجعش 404 بس كان بطيء) فنجربوه أول ونستنوه أكثر
            $paths = ["/sessions/{$u}/groups", "/sessions/{$u}/groups?limit=500", "/groups?session_uuid={$u}", "/sessions/{$u}/chats"];
            $pref = \Illuminate\Support\Facades\Cache::get('wa_groups_path');
            if (is_string($pref)) {
                array_unshift($paths, str_replace('{u}', $u, $pref));
            }
            foreach (array_unique($paths) as $path) {
                try {
                    $r = Http::withHeaders($h)->acceptJson()->connectTimeout(20)->timeout(90)->get($api . $path);
                } catch (\Throwable $e) {
                    $errs[] = $path . ': ' . mb_substr($e->getMessage(), 0, 60);
                    continue;
                }
                if (! $r->successful()) {
                    $errs[] = $path . ': HTTP ' . $r->status() . ' ' . mb_substr((string) $r->body(), 0, 80);
                    continue;
                }
                $n = 0;
                $walk = function ($node) use (&$walk, &$out, &$n, $u, $ss) {
                    if (! is_array($node)) {
                        return;
                    }
                    $id = null;
                    foreach (['id', 'jid', 'group_id', 'groupId', 'chat_id', 'wid', 'remoteJid'] as $k) {
                        $v = $node[$k] ?? null;
                        if (is_array($v)) {
                            $v = $v['_serialized'] ?? $v['id'] ?? null;
                        }
                        if (is_string($v) && preg_match('/^[\d-]{6,}@g(\.us)?$/', $v)) {
                            $id = $v;
                            break;
                        }
                    }
                    if ($id) {
                        $name = $node['subject'] ?? $node['name'] ?? $node['title'] ?? $node['group_name'] ?? null;
                        $id = str_ends_with($id, '@g') ? $id . '.us' : $id;
                        $out[$id] = ['id' => $id, 'name' => is_string($name) ? mb_substr($name, 0, 80) : null, 'session' => $u, 'number' => $ss['name']];
                        $n++;

                        return;
                    }
                    foreach ($node as $child) {
                        $walk($child);
                    }
                };
                $walk($r->json());
                if ($n) {
                    \Illuminate\Support\Facades\Cache::forever('wa_groups_path', str_replace($u, '{u}', $path));
                    $found = true;
                    break;
                }
                $errs[] = $path . ': رد بدون قروبات ' . mb_substr((string) $r->body(), 0, 120);
            }
            if (! $found) {
                Log::info('Whatsy groups not found', ['session' => $u]);
            }
        }
        \Illuminate\Support\Facades\Cache::put('wa_groups_err', implode(' | ', array_slice($errs, 0, 12)), now()->addDay());

        return array_values($out);
    }

    /**
     * إرسال لقروب واتساب (ID ينتهي بـ @g.us) من نفس الرقم اللي في القروب.
     * Whatsy يقبل الـ ID في خانة phone، نجربو الصيغ ونحفظو اللي تمشي.
     */
    public function sendToGroup(string $groupId, string $message, ?string $file = null, ?string $fileName = null, ?string $session = null): bool
    {
        $gid = trim($groupId);
        $bare = preg_replace('/@g(\.us)?$/', '', $gid);
        if ($session === null) {
            $known = (array) \App\Services\AppSettings::get('wa_groups_list', []) + (array) \App\Services\AppSettings::get('wa_groups_seen', []) + (array) \App\Services\AppSettings::get('wa_groups_recent', []);
            $session = $known[$bare . '@g.us']['session'] ?? null;
        }
        if (! is_string($session) || preg_match('/^[a-fA-F0-9]{32}$/D', $session) !== 1) {
            $session = null; // الرقم الرئيسي
        }
        $base = ['message' => $message];
        if ($file && is_file($file)) {
            $base += ['file_base64' => base64_encode((string) file_get_contents($file)), 'file_name' => $fileName ?: basename($file)];
        }
        $variants = [
            ['phone' => [$bare . '@g.us']],
            ['phone' => [$bare . '@g']],
            ['phone' => [$bare]],
            ['phone' => $bare . '@g.us'],
        ];
        $pref = \Illuminate\Support\Facades\Cache::get('wa_group_variant2');
        if (is_int($pref) && isset($variants[$pref])) {
            $variants = [$pref => $variants[$pref]] + $variants;
        }
        $errs = [];
        foreach ($variants as $i => $v) {
            try {
                $r = $this->send($base + $v, $session, $file ? 60 : 30);
                if ($r->successful()) {
                    \Illuminate\Support\Facades\Cache::forever('wa_group_variant2', $i);
                    \Illuminate\Support\Facades\Cache::put('wa_group_last', 'ok · صيغة ' . ($i + 1), now()->addDay());

                    return true;
                }
                $errs[] = ($i + 1) . ': HTTP ' . $r->status() . ' ' . mb_substr((string) $r->body(), 0, 90);
            } catch (\Throwable $e) {
                $errs[] = ($i + 1) . ': ' . mb_substr($e->getMessage(), 0, 90);
            }
        }
        $last = implode(' | ', $errs);
        \Illuminate\Support\Facades\Cache::put('wa_group_last', $last, now()->addDay());
        Log::warning('WhatsApp group send failed', ['group' => $gid, 'last' => $last]);

        return false;
    }
}
