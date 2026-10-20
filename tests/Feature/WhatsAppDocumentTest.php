<?php

namespace Tests\Feature;

use App\Services\CartQuoteException;
use App\Services\WhatsAppService;
use Illuminate\Http\Client\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppDocumentTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['services.whatsapp.api_url' => 'https://whatsapp.test/api',
            'services.whatsapp.api_token' => 'test-secret',
            'services.whatsapp.main_session_uuid' => 'cf2859dd56474dd69db06308afde6d92']);
    }

    public function test_sends_pdf_as_base64_with_api_key_header_and_caption(): void
    {
        $bytes = '%PDF-1.7 test document';
        Storage::disk('local')->put('quote.pdf', $bytes);
        Http::fake(['whatsapp.test/*' => Http::response(['message' => 'Accepted'], 202)]);
        $this->assertTrue(app(WhatsAppService::class)->sendDocument('+218916655677',
            Storage::disk('local')->path('quote.pdf'), 'shein-quotation-2.pdf', 'عرض السعر جاهز'));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://whatsapp.test/api/message'
            && $request['session_uuid'] === 'cf2859dd56474dd69db06308afde6d92'
            && $request->hasHeader('X-API-Key', 'test-secret')
            && $request['phone'] === ['218916655677']
            && $request['file_name'] === 'shein-quotation-2.pdf'
            && $request['message'] === 'عرض السعر جاهز'
            && base64_decode($request['file_base64'], true) === $bytes);
        Http::assertSentCount(1);
    }

    public function test_pdf_caption_and_three_buttons_are_sent_in_one_request(): void
    {
        Storage::disk('local')->put('quote.pdf', '%PDF-1.7 test');
        Http::fake(['whatsapp.test/*' => Http::response([], 202)]);
        $buttons = [
            ['id' => 'confirm:CART-1042', 'title' => 'تأكيد الطلب'],
            ['id' => 'edit:CART-1042', 'title' => 'تعديل الكميات'],
            ['id' => 'cancel:CART-1042', 'title' => 'إلغاء الطلب'],
        ];
        $this->assertTrue(app(WhatsAppService::class)->sendDocument('218916655677',
            Storage::disk('local')->path('quote.pdf'), 'quote-CART-1042.pdf', 'عرض السعر',
            'cf2859dd56474dd69db06308afde6d92', $buttons));
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['buttons'] === $buttons
            && $request['message'] === 'عرض السعر' && $request['file_name'] === 'quote-CART-1042.pdf'
            && base64_decode($request['file_base64']) === '%PDF-1.7 test'
            && $request['session_uuid'] === 'cf2859dd56474dd69db06308afde6d92');
    }

    public function test_empty_caption_uses_buttons_text_and_two_buttons_are_supported(): void
    {
        Http::fake(['whatsapp.test/*' => Http::response([], 202)]);
        $buttons = [['id' => 'a', 'title' => 'تأكيد الطلب'], ['id' => 'b', 'title' => 'إلغاء الطلب']];
        app(WhatsAppService::class)->send(['phone' => ['218916655677'], 'message' => '', 'buttons' => $buttons]);
        Http::assertSent(fn (Request $request) => $request['buttons_text'] === 'اختر الإجراء المطلوب.'
            && $request['buttons'] === $buttons);
    }

    /** @dataProvider invalidButtons */
    public function test_invalid_buttons_are_rejected_before_http(array $buttons): void
    {
        try {
            app(WhatsAppService::class)->send(['phone' => ['218916655677'], 'message' => 'Quote', 'buttons' => $buttons]);
            $this->fail('Invalid buttons must be rejected');
        } catch (\InvalidArgumentException $e) {
            Http::assertNothingSent();
        }
    }

    public static function invalidButtons(): array
    {
        return [
            'one button' => [[['id' => 'a', 'title' => 'A']]],
            'four buttons' => [[['id' => 'a', 'title' => 'A'], ['id' => 'b', 'title' => 'B'], ['id' => 'c', 'title' => 'C'], ['id' => 'd', 'title' => 'D']]],
            'duplicate ID' => [[['id' => 'a', 'title' => 'A'], ['id' => 'a', 'title' => 'B']]],
            'duplicate title' => [[['id' => 'a', 'title' => 'A'], ['id' => 'b', 'title' => 'A']]],
            'long ID' => [[['id' => str_repeat('a', 257), 'title' => 'A'], ['id' => 'b', 'title' => 'B']]],
            'long Arabic title' => [[['id' => 'a', 'title' => str_repeat('ع', 21)], ['id' => 'b', 'title' => 'B']]],
            'missing title' => [[['id' => 'a'], ['id' => 'b', 'title' => 'B']]],
        ];
    }

    public function test_rejects_non_pdf_and_oversized_files_before_contacting_provider(): void
    {
        $service = app(WhatsAppService::class);
        foreach (['not-pdf' => 'plain text', 'too-big' => '%PDF-'.str_repeat('x', 7000000)] as $file => $bytes) {
            Storage::disk('local')->put($file, $bytes);
            try {
                $service->sendDocument('218916655677', Storage::disk('local')->path($file), 'quote.pdf', 'Caption');
                $this->fail('Invalid document should not be sent');
            } catch (CartQuoteException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        Http::assertNothingSent();
    }

    public function test_provider_document_failure_remains_retryable(): void
    {
        Storage::disk('local')->put('quote.pdf', '%PDF-1.7 test');
        Http::fake(['whatsapp.test/*' => Http::response([], 503)]);
        $this->assertFalse(app(WhatsAppService::class)->sendDocument('218916655677',
            Storage::disk('local')->path('quote.pdf'), 'quote.pdf', 'Caption'));
    }

    public function test_text_messages_also_use_header_authentication_without_token_in_url(): void
    {
        Http::fake(['whatsapp.test/*' => Http::response([], 202)]);
        $this->assertTrue(app(WhatsAppService::class)->sendMessage('218916655677', 'Message'));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://whatsapp.test/api/message'
            && $request['session_uuid'] === 'cf2859dd56474dd69db06308afde6d92'
            && $request->hasHeader('X-API-Key', 'test-secret') && $request['message'] === 'Message');
    }
}
