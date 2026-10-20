<?php

namespace App\Console\Commands;

use App\Services\CampaignService;
use Illuminate\Console\Command;

class RunCampaigns extends Command
{
    protected $signature = 'sg:campaigns';

    protected $description = 'يبعت رسالة وحدة من الحملات الشغالة (يتنادى كل دقيقة)';

    public function handle()
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('campaigns')) {
            return self::SUCCESS;
        }
        $this->info((string) CampaignService::tick());

        return self::SUCCESS;
    }
}
