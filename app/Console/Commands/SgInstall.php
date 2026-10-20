<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/** تركيب/تحديث الواجهة الجديدة بأمر واحد: php artisan sg:install */
class SgInstall extends Command
{
    protected $signature = 'sg:install';

    protected $description = 'يشغل الترحيلات الجديدة، يربط مجلد الملفات، وينظف الكاش';

    public function handle(): int
    {
        $this->info('1) ترحيل باقية البيانات…');
        Artisan::call('migrate', ['--force' => true], $this->output);
        $this->info('2) ربط مجلد الملفات (صور الفواتير وإيصالات التحويل)…');
        if (!file_exists(public_path('storage'))) {
            Artisan::call('storage:link', [], $this->output);
        }
        $this->info('3) تنظيف الكاش…');
        foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear', 'permission:cache-reset'] as $c) {
            try {
                Artisan::call($c, [], $this->output);
            } catch (\Throwable $e) {
                $this->warn($c . ': ' . $e->getMessage());
            }
        }
        $this->info('✓ جاهز. افتح /admin');

        return self::SUCCESS;
    }
}
