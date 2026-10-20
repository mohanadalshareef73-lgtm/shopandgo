<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Log;
use App\Services\AppSettings;
use App\Services\BackupService;
use Illuminate\Http\Request;

/** النسخ الاحتياطي: الإعدادات، نسخة الآن، تنزيل */
class BackupController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:system-edit');
    }

    public function index()
    {
        return view('sg.backups.index', ['nav' => 'backup', 'list' => BackupService::list(), 's' => AppSettings::all(), 'zip' => class_exists(\ZipArchive::class)]);
    }

    public function store(Request $request)
    {
        try {
            $b = BackupService::make();
            $msg = 'تمت النسخة ' . $b['name'] . ' (' . round($b['size'] / 1048576, 1) . ' MB)';
            if ($request->boolean('send')) {
                $r = BackupService::send($b);
                $msg .= $r['email'] === 'sent' ? ' · أُرسلت لإيميلك' : ($r['email'] ? ' · ⚠ الإيميل لم يصل (راجع إعدادات الإيميل في السيرفر)' : ' · لا يوجد إيميل مسجل');
            }
            Log::create(['user_id' => auth()->id(), 'type' => 'backup', 'title' => ' نسخة احتياطية', 'log' => ' ' . $b['name']]);

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'النسخة فشلت: ' . mb_substr($e->getMessage(), 0, 200));
        }
    }

    public function download(string $file)
    {
        $path = BackupService::dir() . '/' . basename($file);
        abort_unless(is_file($path) && str_ends_with($path, '.zip'), 404);
        Log::create(['user_id' => auth()->id(), 'type' => 'backup', 'title' => ' تنزيل نسخة احتياطية', 'log' => ' ' . basename($file)]);

        return response()->download($path);
    }
}
