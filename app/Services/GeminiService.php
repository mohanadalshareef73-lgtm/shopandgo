<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    /**
     * Call the Gemini API and return the AI reply text.
     *
     * @param  string  $customerMessage  The raw text sent by the customer
     * @param  \Illuminate\Support\Collection  $roles  AiRoles collection
     * @param  \Illuminate\Support\Collection  $conversationHistory  WhatsappMessages (oldest → newest)
     * @param  string  $customerName
     * @return string|null  AI reply, or null on failure
     */
    public function respond(
        string $customerMessage,
        $roles,
        $conversationHistory,
        string $customerName
    ): ?string {
        try {
            $prompt = $this->buildPrompt(
                $customerMessage,
                $roles,
                $conversationHistory,
                $customerName
            );

            $url = sprintf(
                'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
                config('services.gemini.model'),
                config('services.gemini.api_key')
            );

            $body = [
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [['text' => $prompt]],
                    ],
                ],
                'generationConfig' => [
                    'temperature'     => 0.7,
                    'topP'            => 0.9,
                    'topK'            => 40,
                    'maxOutputTokens' => 500,
                ],
            ];

            // Retry up to 3 times for transient errors (503 / 429)
            $maxAttempts = 3;
            $response    = null;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(30)
                    ->post($url, $body);

                $status = $response->status();

                if ($response->successful()) {
                    break; // got a good response, stop retrying
                }

                // Retry only on overload / rate-limit errors
                if (in_array($status, [429, 503]) && $attempt < $maxAttempts) {
                    $delay = $attempt * 2; // 2s, 4s
                    Log::warning("Gemini {$status} on attempt {$attempt}, retrying in {$delay}s…");
                    sleep($delay);
                    continue;
                }

                break; // non-retryable error
            }

            if ($response->successful()) {
                $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

                if ($text) {
                    Log::info('✅ Gemini reply received', ['reply' => $text]);
                    return $text;
                }
            }

            Log::error('❌ Gemini API non-success', [
                'status'   => $response->status(),
                'response' => $response->json(),
            ]);

            return null;

        } catch (\Throwable $e) {
            Log::error('❌ Gemini API exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function buildPrompt(
        string $customerMessage,
        $roles,
        $conversationHistory,
        string $customerName
    ): string {
        // Roles context
        $rolesContext = '';
        if ($roles->isNotEmpty()) {
            $rolesContext = "\n\nYour roles and capabilities:\n";
            foreach ($roles as $role) {
                // Support both column-name variants gracefully
                $name  = $role->role    ?? '';
                $desc  = $role->message ?? '';
                $rolesContext .= "- {$name}: {$desc}\n";
            }
        }

        // Conversation history context — oldest first so the AI reads it naturally
        $historyContext = '';
        if ($conversationHistory->isNotEmpty()) {
            $historyContext = "\n\nRecent conversation history (oldest first):\n";
            foreach ($conversationHistory as $msg) {
                $historyContext .= "- Customer: " . ($msg->message ?? '') . "\n";
                if ($msg->replyMessage) {
                    $historyContext .= '- AI: ' . $msg->replyMessage->message . "\n";
                }
            }
        }

        return "You are a helpful customer service assistant for LibyaPost. "
            . "Customer name: {$customerName}. "
            . $rolesContext
            . $historyContext
            . "\n\nCustomer message: {$customerMessage}"
            . "\n\nRespond with a helpful and professional reply.";
    }
}
