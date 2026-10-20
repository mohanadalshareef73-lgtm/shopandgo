<?php

namespace App\Console\Commands;

use App\Services\CartWhatsAppReplies;
use Illuminate\Console\Command;

class ProcessCartWhatsAppReplies extends Command
{
    protected $signature = 'cart-quotes:process-replies';

    protected $description = 'Apply queued Whatsy cart button taps and retry their replies';

    public function handle(CartWhatsAppReplies $replies): int
    {
        $this->info('Processed '.$replies->processPending().' cart button event(s).');

        return self::SUCCESS;
    }
}
