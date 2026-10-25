<?php

namespace App\Console\Commands;

use App\Models\CartQuote;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** تنظيف يومي: ملفات وسجلات قديمة ما نحتاجوهاش، باش الاستضافة ما تتعباش والمنظومة تقعد خفيفة */
class Cleanup extends Command
{
    protected $signature = 'sg:cleanup';

    protected $description = 'يمسح ملفات PDF وصور المنتجات القديمة والسجلات الكبيرة';

    public function handle()
    {
        $freed = 0;
        $disk = Storage::disk('local');
        $keep = CartQuote::whereNotNull('pdf_path')->pluck('pdf_path')->flip();
        // سلات لسه ما انبعتلهاش السعر: ملفها ما يتمسحش (يحتاجه الإرسال)
        $pending = CartQuote::whereNotNull('pdf_path')->whereNull('notified_at')->pluck('pdf_path')->flip();
        $days = max(2, (int) \App\Services\AppSettings::get('pdf_keep_days', 5));
        foreach (['shein', 'amazon', 'iherb'] as $r) {
            $dir = $r . '-quotations';
            if (!$disk->exists($dir)) {
                continue;
            }
            // ملفات PDF: اللي ما عادش مربوطة بسلة (أقدم من يوم)، وأي سلة انبعت سعرها (أقدم من 5 أيام)
            // 🔁 آمن: أي ملف ينمسح يتعمل من جديد وحده وقت ما حد يفتحه ولا يبعته (prepare)
            foreach ($disk->files($dir) as $f) {
                $age = now()->timestamp - $disk->lastModified($f);
                $orphan = !isset($keep[$f]) && $age > 86400;
                $stale = isset($keep[$f]) && !isset($pending[$f]) && $age > $days * 86400;
                if ($orphan || $stale) {
                    $freed += $disk->size($f);
                    $disk->delete($f);
                }
            }
            // صور المنتجات المؤقتة (تنزل من جديد وقت الحاجة) أقدم من 5 أيام
            if ($disk->exists($dir . '/images')) {
                foreach ($disk->allFiles($dir . '/images') as $f) {
                    if (now()->timestamp - $disk->lastModified($f) > $days * 86400) {
                        $freed += $disk->size($f);
                        $disk->delete($f);
                    }
                }
            }
        }
        // السجلات (logs): أقدم من 14 يوم تنمسح، والكبيرة تتقص
        foreach (glob(storage_path('logs/*.log')) ?: [] as $f) {
            $size = (int) @filesize($f);
            if (time() - (int) @filemtime($f) > 14 * 86400 && basename($f) !== 'laravel.log') {
                $freed += $size;
                @unlink($f);
            } elseif ($size > 20 * 1024 * 1024) {
                $tail = (string) @file_get_contents($f, false, null, $size - 2 * 1024 * 1024);
                @file_put_contents($f, "[قصّينا السجل القديم]\n" . $tail);
                $freed += $size - strlen($tail);
            }
        }
        // قاعدة البيانات ما نلمسوهاش خالص (كل البيانات تبقى)
        $this->info('تحرر ' . round($freed / 1048576, 1) . ' MB من الملفات المؤقتة · قاعدة البيانات ما تلمستش ✓');

        return self::SUCCESS;
    }
}
