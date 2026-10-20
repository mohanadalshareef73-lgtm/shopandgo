<?php

namespace App\Jobs;

use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $phones;

    protected $message;

    public function __construct($phones, $message)
    {
        $this->phones = is_array($phones) ? $phones : [$phones];
        $this->message = $message;
    }

    public function handle()
    {
        try {
            $normalizedPhones = collect($this->phones)
                ->map(function ($phone) {
                    $phone = preg_replace('/\D/', '', $phone);
                    $phone = ltrim($phone, '0+');
                    if (! str_starts_with($phone, '218')) {
                        $phone = '218'.$phone;
                    }

                    return substr($phone, 0, 12);
                })
                ->unique()
                ->values()
                ->toArray();

            $response = app(WhatsAppService::class)->send([
                'phone' => $normalizedPhones,
                'message' => $this->message,
            ]);

            $success = $response->successful();

            // ✅ Store status in session
            session()->flash('whatsapp_status', $success);

            return $success;
        } catch (\Exception $e) {
            session()->flash('whatsapp_status', false);

            return false;
        }
    }
}
