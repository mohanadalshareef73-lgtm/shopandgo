<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('cart-quotes:process')->everyMinute()->withoutOverlapping();
        $schedule->command('sg:campaigns')->everyMinute()->withoutOverlapping(); // حملات واتساب على دفعات
        $schedule->command('sg:cleanup')->dailyAt('04:30')->timezone('Africa/Tripoli')->withoutOverlapping(); // تنظيف الملفات والسجلات القديمة
        $schedule->command('cart-quotes:process-replies')->everyMinute()->withoutOverlapping();
        $schedule->command('whatsapp:resolve-cart-contacts')->everyMinute()->withoutOverlapping();
        // التقرير الشهري للشركاء: أول يوم في الشهر الساعة 9 الصبح (توقيت ليبيا)
        $schedule->command('sg:monthly-report')->monthlyOn(1, '09:00')->timezone('Africa/Tripoli');
        // تذكير الرف الأوتوماتيكي + تجهيز تذكير الديون (بتأكيد) + التقرير اليومي + النسخة الاحتياطية الأسبوعية
        $time = function (string $key, string $def) {
            try {
                $v = (string) \App\Services\AppSettings::get($key, $def);

                return preg_match('/^\d{1,2}:\d{2}$/', $v) ? $v : $def;
            } catch (\Throwable $e) {
                return $def;
            }
        };
        $schedule->command('sg:shelf-reminders')->dailyAt($time('shelf_remind_time', '11:00'))->timezone('Africa/Tripoli')->withoutOverlapping();
        $schedule->command('sg:debt-reminders')->dailyAt('10:00')->timezone('Africa/Tripoli');
        $schedule->command('sg:ops-alerts')->dailyAt('09:30')->timezone('Africa/Tripoli');
        // تقرير كل 14 يوم (يتفحص كل يوم ويبعت لما تفوت 14 يوم على آخر واحد)
        $schedule->command('sg:biweekly-report')->dailyAt('09:15')->timezone('Africa/Tripoli');
        $schedule->command('sg:daily-report')->dailyAt($time('daily_report_time', '22:00'))->timezone('Africa/Tripoli');
        try {
            $day = (int) \App\Services\AppSettings::get('backup_day', 5);
        } catch (\Throwable $e) {
            $day = 5;
        }
        $schedule->command('sg:backup --send')->weeklyOn($day, $time('backup_time', '03:00'))->timezone('Africa/Tripoli')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
