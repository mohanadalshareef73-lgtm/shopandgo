<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            static::alertAdmins($e);
        });
    }

    /** أي خطأ في المنظومة يوصل للإدارة على الواتساب (نفس الخطأ مرة كل 30 دقيقة، وأقصى 10 في الساعة) */
    protected static bool $alerting = false;

    public static function alertAdmins(Throwable $e): void
    {
        if (static::$alerting) {
            return;
        }
        static::$alerting = true;
        try {
            $phones = \App\Services\AppSettings::phonesFor('errors');
            if (!$phones || !class_exists(\App\Services\MessageService::class)) {
                return;
            }
            $cache = app('cache');
            $key = 'err-alert-' . md5(get_class($e) . $e->getFile() . $e->getLine());
            if ($cache->has($key) || (int) $cache->get('err-alert-count', 0) >= 10) {
                return;
            }
            $cache->put($key, 1, now()->addMinutes(30));
            $cache->put('err-alert-count', (int) $cache->get('err-alert-count', 0) + 1, now()->addHour());
            $msg = mb_substr($e->getMessage(), 0, 220);
            $hint = '';
            if (str_contains($msg, 'Unknown column') || str_contains($msg, "doesn't exist") || str_contains($msg, 'Base table')) {
                $hint = "\n💡 غالباً ما شغلتش تحديث قاعدة البيانات: افتح /system-update";
            } elseif (str_contains($msg, 'View [') || str_contains($msg, 'Route [')) {
                $hint = "\n💡 غالباً ملف ناقص من باتش — عاود Extract وامسح الكاش";
            }
            $user = auth('web')->user() ?? auth('customer')->user();
            $text = "⚠️ خطأ في المنظومة\n"
                . (app()->runningInConsole() ? 'مهمة تلقائية' : 'الصفحة: ' . request()->method() . ' ' . request()->path()) . "\n"
                . ($user ? 'المستخدم: ' . $user->name . "\n" : '')
                . 'الخطأ: ' . $msg . "\n"
                . 'المكان: ' . str_replace(base_path() . '/', '', $e->getFile()) . ':' . $e->getLine()
                . $hint . "\n🕒 " . now()->format('Y-m-d H:i');
            \App\Services\MessageService::sendText('alert', $text, $phones);
        } catch (Throwable $x) {
            // التنبيه نفسه ما يطيحش المنظومة
        } finally {
            static::$alerting = false;
        }
    }
}
