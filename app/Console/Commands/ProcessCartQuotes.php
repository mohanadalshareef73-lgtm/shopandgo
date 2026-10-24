<?php

namespace App\Console\Commands;

use App\Models\CartQuote;
use App\Services\CartQuoteService;
use Illuminate\Console\Command;

class ProcessCartQuotes extends Command
{
    protected $signature = 'cart-quotes:process';

    protected $description = 'Retry cart submissions, poll pending quotes and deliver WhatsApp quotations';

    public function handle(CartQuoteService $quotes): int
    {
        \Illuminate\Support\Facades\Cache::put('hb.cart-quotes', now()->timestamp, now()->addDays(3)); // 💓 لصفحة فحص التسعير
        $quotes->processNotifications();
        $processed = 0;
        CartQuote::whereNull('notified_at')->where(function ($query) {
            $query->where('next_attempt_at', '<=', now())
                // Older workers stopped scheduling after a pending deadline read.
                ->orWhere(fn ($query) => $query->whereNull('next_attempt_at')
                    ->where('status', 'submitted')->whereNotNull('quote_id'));
        })
            ->chunkById(50, function ($batch) use ($quotes, &$processed) {
                foreach ($batch as $quote) {
                    $quotes->process($quote->id);
                    $processed++;
                }
            });
        $this->info('Processed '.$processed.' cart quote request(s).');

        return self::SUCCESS;
    }
}
