<?php

namespace App\Console\Commands;

use App\Services\WhatsAppContactNumbers;
use Illuminate\Console\Command;

class ResolveWhatsAppCartContacts extends Command
{
    protected $signature = 'whatsapp:resolve-cart-contacts';
    protected $description = 'Resolve customer phone numbers for WhatsApp cart chat links';

    public function handle(WhatsAppContactNumbers $contacts): int
    {
        $counts = $contacts->resolvePending();
        $this->info('Customer numbers: '.$counts['resolved'].' resolved, '.$counts['unresolved'].' unavailable, '
            .$counts['failed'].' scheduled for retry.');
        return self::SUCCESS;
    }
}
