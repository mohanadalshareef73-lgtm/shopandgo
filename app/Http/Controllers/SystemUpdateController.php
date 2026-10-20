<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * تحديث النظام من المتصفح (لاستضافة cPanel بدون Terminal).
 * صفحة مستقلة لا تعتمد على الجداول الجديدة، حتى تعمل قبل تشغيل الترحيلات.
 * للمدير (Admin) فقط. تأخذ نسخة احتياطية من قاعدة البيانات أولاً ثم تشغّل sg:install.
 */
class SystemUpdateController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->check()) {
                $request->session()->put('sg.after_login', 'system-update');

                return redirect()->route('admin.login');
            }
            abort_unless(auth()->user() && auth()->user()->hasRole('Admin'), 403, 'للمدير فقط');

            return $next($request);
        });
    }

    protected function pending(): array
    {
        $m = app('migrator');
        if (!$m->repositoryExists()) {
            return ['(جدول الترحيلات غير موجود)'];
        }
        $files = $m->getMigrationFiles([database_path('migrations')]);
        $ran = $m->getRepository()->getRan();

        return array_values(array_diff(array_keys($files), $ran));
    }

    public function index()
    {
        return view('system.update', ['pending' => $this->pending(), 'backups' => array_slice(BackupService::list(), 0, 5), 'out' => session('out'), 'zip' => class_exists(\ZipArchive::class)]);
    }

    public function run(Request $request)
    {
        @set_time_limit(0);
        $log = [];
        // 1) نسخة احتياطية أولاً
        try {
            $b = BackupService::make();
            $log[] = '✓ نسخة احتياطية: ' . $b['name'] . ' (' . round($b['size'] / 1048576, 1) . ' MB) — نزّلها من الأسفل واحفظها عندك';
        } catch (\Throwable $e) {
            report($e);
            if (!$request->boolean('skip_backup')) {
                return back()->with('out', "✗ النسخة الاحتياطية فشلت: " . $e->getMessage() . "\nلم يتم تغيير أي شيء. خذ نسخة من phpMyAdmin (Export) ثم أعد المحاولة مع خيار «أخذت نسخة بنفسي».");
            }
            $log[] = '⚠ تخطّينا النسخة الاحتياطية (قلت إنك أخذت نسخة بنفسك)';
        }
        // 2) التحديث
        $out = new BufferedOutput();
        try {
            Artisan::call('sg:install', [], $out);
            $log[] = trim($out->fetch());
            $left = $this->pending();
            $log[] = $left ? '⚠ بقيت ترحيلات لم تُنفذ: ' . implode('، ', $left) : '✓ قاعدة البيانات محدّثة بالكامل';
        } catch (\Throwable $e) {
            report($e);
            $log[] = trim($out->fetch());
            $log[] = '✗ خطأ أثناء التحديث: ' . $e->getMessage();
            $log[] = 'للرجوع: استرجع النسخة الاحتياطية (phpMyAdmin ← Import) وارجع الملفات القديمة.';
        }
        \App\Models\Log::create(['user_id' => auth()->id(), 'type' => 'backup', 'title' => ' تحديث النظام', 'log' => mb_substr(implode(' | ', $log), 0, 1000)]);

        return back()->with('out', implode("\n", $log));
    }

    public function download(string $file)
    {
        $path = BackupService::dir() . '/' . basename($file);
        abort_unless(is_file($path) && str_ends_with($path, '.zip'), 404);

        return response()->download($path);
    }
}
