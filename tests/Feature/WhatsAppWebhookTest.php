<?php

namespace Tests\Feature;

use App\Models\AiRoles;
use App\Models\ReplayMessages;
use App\Models\WhatsappMessages;
use App\Services\GeminiService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Build a minimal valid WhatsApp webhook payload. */
    private function makePayload(
        string $from = '218911234567',
        string $text = 'Hello, what are your shipping prices?',
        string $messageId = 'wamid.test123',
        string $type = 'text',
        string $sessionUuid = 'cf2859dd56474dd69db06308afde6d92'
    ): array {
        return [
            'entry' => [
                [
                    'id' => $sessionUuid,
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => [
                                    'display_phone_number' => '218921000000',
                                ],
                                'contacts' => [
                                    ['profile' => ['name' => 'Ahmed']],
                                ],
                                'messages' => [
                                    [
                                        'id'   => $messageId,
                                        'from' => $from,
                                        'type' => $type,
                                        'text' => ['body' => $text],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** Valid webhook token (matches WHATSAPP_WEBHOOK_TOKEN in .env.testing). */
    private function validToken(): string
    {
        return config('services.whatsapp.webhook_token');
    }

    // ── Authentication ────────────────────────────────────────────────────────

    /** @test */
    public function it_rejects_requests_with_wrong_token(): void
    {
        $response = $this->postJson(
            '/api/webhook/whatsapp?token=WRONG_TOKEN',
            $this->makePayload()
        );

        $response->assertStatus(401);
    }

    /** @test */
    public function it_rejects_empty_body(): void
    {
        $response = $this->postJson(
            '/api/webhook/whatsapp?token=' . $this->validToken(),
            []
        );

        $response->assertStatus(400)
                 ->assertJson(['status' => false]);
    }

    // ── Happy Path ────────────────────────────────────────────────────────────

    /** @test */
    public function it_saves_incoming_message_and_sends_ai_reply(): void
    {
        // Mock GeminiService to avoid real API call
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('respond')
                 ->once()
                 ->andReturn('Our shipping prices start from 5 LYD.');
        });

        // Mock WhatsAppService to avoid real HTTP call
        $this->mock(WhatsAppService::class, function ($mock) {
            $mock->shouldReceive('sendMessage')
                 ->once()
                 ->with('218911234567', 'Our shipping prices start from 5 LYD.', 'cf2859dd56474dd69db06308afde6d92')
                 ->andReturn(true);
        });

        $response = $this->postJson(
            '/api/webhook/whatsapp?token=' . $this->validToken(),
            $this->makePayload()
        );

        $response->assertOk()->assertJson(['status' => true]);

        // Incoming message persisted
        $this->assertDatabaseHas('whatsapp_messagess', [
            'message_id' => 'wamid.test123',
            'from'       => '218911234567',
            'message'    => 'Hello, what are your shipping prices?',
            'name'       => 'Ahmed',
            'is_reply'   => true,
        ]);

        // AI reply persisted
        $this->assertDatabaseHas('replay_messages', [
            'message' => 'Our shipping prices start from 5 LYD.',
        ]);
    }

    // ── Duplicate Guard ───────────────────────────────────────────────────────

    public function test_routes_each_entry_reply_and_history_through_its_receiving_number(): void
    {
        $sessions = ['cf2859dd56474dd69db06308afde6d92', 'd8e21afbe77f4d8bbaed65f2c6c2edf7'];
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('respond')->twice()->withArgs(function ($text, $roles, $history) {
                return $history->count() === 1;
            })->andReturn('Reply');
        });
        $this->mock(WhatsAppService::class, function ($mock) use ($sessions) {
            foreach ($sessions as $session) {
                $mock->shouldReceive('sendMessage')->once()->with('218911234567', 'Reply', $session)->andReturn(true);
            }
        });
        $first = $this->makePayload(messageId: 'wamid.first', sessionUuid: $sessions[0]);
        $second = $this->makePayload(messageId: 'wamid.second', sessionUuid: $sessions[1]);
        $this->postJson('/api/webhook/whatsapp?token='.$this->validToken(),
            ['entry' => [$first['entry'][0], $second['entry'][0]]])->assertOk();
        foreach ($sessions as $session) {
            $this->assertDatabaseHas('whatsapp_messagess', ['session_uuid' => $session, 'is_reply' => true]);
        }
    }

    public function test_rejects_message_without_receiving_session_instead_of_using_main_sender(): void
    {
        $this->mock(GeminiService::class, fn ($mock) => $mock->shouldReceive('respond')->never());
        $this->mock(WhatsAppService::class, fn ($mock) => $mock->shouldReceive('sendMessage')->never());
        $payload = $this->makePayload();
        unset($payload['entry'][0]['id']);
        $this->postJson('/api/webhook/whatsapp?token='.$this->validToken(), $payload)->assertStatus(400);
        $this->assertDatabaseCount('whatsapp_messagess', 0);
    }

    public function test_group_text_from_a_participant_lid_never_reaches_ai_or_delivery(): void
    {
        $this->mock(GeminiService::class, fn ($mock) => $mock->shouldReceive('respond')->never());
        $this->mock(WhatsAppService::class, fn ($mock) => $mock->shouldReceive('sendMessage')->never());
        $payload = $this->makePayload(from: '184654183321652@lid');
        $payload['entry'][0]['changes'][0]['value']['messages'][0]['chat_id'] = '120363385304666225@g.us';

        $this->postJson('/api/webhook/whatsapp?token='.$this->validToken(), $payload)->assertOk();
        $this->assertDatabaseCount('whatsapp_messagess', 0);
        $this->assertDatabaseCount('replay_messages', 0);
    }

    /** @test */
    public function it_skips_duplicate_message_ids(): void
    {
        // Pre-insert a message with the same message_id
        WhatsappMessages::create([
            'message_id' => 'wamid.duplicate',
            'session_uuid' => 'cf2859dd56474dd69db06308afde6d92',
            'from'       => '218911234567',
            'phone'      => '218921000000',
            'name'       => 'Ahmed',
            'type'       => 'text',
            'message'    => 'First message',
            'timestamp'  => now(),
            'is_reply'   => false,
        ]);

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('respond')->never(); // must NOT be called
        });

        $this->mock(WhatsAppService::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->never(); // must NOT be called
        });

        $response = $this->postJson(
            '/api/webhook/whatsapp?token=' . $this->validToken(),
            $this->makePayload(messageId: 'wamid.duplicate')
        );

        $response->assertOk();

        // Only 1 record in DB (the pre-inserted one, not a second)
        $this->assertDatabaseCount('whatsapp_messagess', 1);
    }

    // ── Non-text Message ──────────────────────────────────────────────────────

    /** @test */
    public function it_skips_non_text_messages(): void
    {
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('respond')->never();
        });

        $this->mock(WhatsAppService::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->never();
        });

        $response = $this->postJson(
            '/api/webhook/whatsapp?token=' . $this->validToken(),
            $this->makePayload(type: 'image') // image message
        );

        $response->assertOk();
        $this->assertDatabaseCount('whatsapp_messagess', 0);
    }

    // ── Gemini Failure ────────────────────────────────────────────────────────

    /** @test */
    public function it_does_not_send_whatsapp_if_gemini_fails(): void
    {
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('respond')->once()->andReturn(null); // AI fail
        });

        $this->mock(WhatsAppService::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->never(); // must NOT send
        });

        $response = $this->postJson(
            '/api/webhook/whatsapp?token=' . $this->validToken(),
            $this->makePayload()
        );

        $response->assertOk();

        // Message saved but NOT marked as replied
        $this->assertDatabaseHas('whatsapp_messagess', [
            'message_id' => 'wamid.test123',
            'is_reply'   => false,
        ]);
    }

    // ── AI Roles Context ──────────────────────────────────────────────────────

    /** @test */
    public function it_loads_ai_roles_and_passes_them_to_gemini(): void
    {
        AiRoles::create([
            'role'    => 'Shipping Expert',
            'message' => 'You know all LibyaPost shipping prices and routes.',
        ]);

        $capturedRoles = null;

        $this->mock(GeminiService::class, function ($mock) use (&$capturedRoles) {
            $mock->shouldReceive('respond')
                 ->once()
                 ->withArgs(function ($msg, $roles, $history, $name) use (&$capturedRoles) {
                     $capturedRoles = $roles;
                     return true;
                 })
                 ->andReturn('Here are your shipping options...');
        });

        $this->mock(WhatsAppService::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
        });

        $this->postJson(
            '/api/webhook/whatsapp?token=' . $this->validToken(),
            $this->makePayload()
        );

        $this->assertNotNull($capturedRoles);
        $this->assertCount(1, $capturedRoles);
        $this->assertEquals('Shipping Expert', $capturedRoles->first()->role);
    }

    // ── Empty Messages Array ──────────────────────────────────────────────────

    /** @test */
    public function it_returns_ok_when_no_messages_in_payload(): void
    {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => [],
                                // No 'messages' key at all
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson(
            '/api/webhook/whatsapp?token=' . $this->validToken(),
            $payload
        );

        $response->assertOk()->assertJson(['status' => true]);
        $this->assertDatabaseCount('whatsapp_messagess', 0);
    }
}
