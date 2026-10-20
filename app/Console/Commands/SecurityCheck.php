<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** فحص سريع لإعدادات الأمان على السيرفر: php artisan sg:security-check */
class SecurityCheck extends Command
{
    protected $signature = 'sg:security-check';
    protected $description = 'فحص إعدادات الأمان للنظام على السيرفر';

    public function handle(): int
    {
        $rows = [];
        $add = function (bool $ok, string $item, string $fix) use (&$rows) {
            $rows[] = [$ok ? '✅' : '❌', $item, $ok ? '' : $fix];
        };
        $add(!config('app.debug'), 'APP_DEBUG مُطفأ', 'ضع APP_DEBUG=false في ملف .env (التشغيل مع debug يكشف أسرار النظام)');
        $add(config('app.env') === 'production', 'APP_ENV=production', 'ضع APP_ENV=production في .env');
        $add(strlen((string) config('app.key')) >= 32, 'APP_KEY موجود', 'شغّل php artisan key:generate');
        $add(str_starts_with((string) config('app.url'), 'https://'), 'الموقع يعمل على HTTPS', 'فعّل شهادة SSL واجعل APP_URL يبدأ بـ https://');
        $add((bool) config('session.secure'), 'كوكيز الجلسة آمنة (Secure)', 'ضع SESSION_SECURE_COOKIE=true في .env (بعد تفعيل HTTPS)');
        $add(config('session.http_only') === true, 'كوكيز الجلسة HttpOnly', 'config/session.php: http_only => true');
        $add(!file_exists(base_path('.env')) || (fileperms(base_path('.env')) & 0o004) === 0, 'ملف .env غير مقروء للجميع', 'chmod 640 .env');
        $add(!is_dir(public_path('storage')) || file_exists(storage_path('app/public/.htaccess')), 'منع تشغيل الملفات في مجلد الرفع', 'انسخ storage/app/public/.htaccess (أو أضف قاعدة في nginx تمنع php داخل /storage و /uploads)');
        $add(!file_exists(public_path('.env')) && !file_exists(public_path('phpinfo.php')), 'لا توجد ملفات حساسة داخل public', 'احذف أي .env أو phpinfo.php من مجلد public');
        $weak = 0;
        foreach (DB::table('users')->where('status', true)->get(['name', 'password']) as $u) {
            foreach (['123456', '12345678', 'password', 'admin', '123123', (string) $u->name] as $p) {
                if ($u->password && Hash::check($p, $u->password)) {
                    $weak++;
                    $this->warn('كلمة مرور ضعيفة للمستخدم: ' . $u->name);
                    break;
                }
            }
        }
        $add($weak === 0, 'لا توجد كلمات مرور ضعيفة للموظفين', 'غيّر كلمات المرور المذكورة أعلاه من صفحة الموظفين');
        $this->table(['', 'البند', 'الإصلاح'], $rows);
        $bad = count(array_filter($rows, fn ($r) => $r[0] === '❌'));
        $bad ? $this->error("يوجد {$bad} بند يحتاج إصلاح") : $this->info('كل البنود سليمة ✅');

        return $bad ? 1 : 0;
    }
}
