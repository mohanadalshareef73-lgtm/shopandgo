<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use ZipArchive;

/**
 * النسخ الاحتياطي:
 *  - باقية البيانات كاملة (MySQL → ملف SQL، SQLite → نسخة من الملف)
 *  - الصور والإيصالات المرفوعة (اختياري)
 *  - يتحفظ في storage/app/backups ويتبعت لإيميل الإدارة، ويتخلى آخر N نسخة فقط
 */
class BackupService
{
    public static function dir(): string
    {
        $d = storage_path('app/backups');
        File::ensureDirectoryExists($d);

        return $d;
    }

    public static function list(): array
    {
        return collect(File::files(static::dir()))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.zip'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'time' => \Carbon\Carbon::createFromTimestamp($f->getMTime())])
            ->values()->all();
    }

    public static function make(): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('امتداد zip غير مركب على السيرفر (php-zip)');
        }
        @set_time_limit(0);
        $name = 'shopandgo-' . now('Africa/Tripoli')->format('Y-m-d_Hi') . '.zip';
        $path = static::dir() . '/' . $name;
        $tmp = static::dir() . '/tmp-' . uniqid();
        File::ensureDirectoryExists($tmp);
        try {
            $conn = config('database.default');
            $driver = config("database.connections.$conn.driver");
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($driver === 'sqlite') {
                $db = config("database.connections.$conn.database");
                $zip->addFile($db, 'database/database.sqlite');
            } else {
                $sql = $tmp . '/database.sql';
                if (!static::mysqldump($sql, $tmp)) {
                    static::dumpMysql($sql);
                }
                $zip->addFile($sql, 'database/database.sql');
            }
            $files = 0;
            if (AppSettings::get('backup_files', true)) {
                $root = storage_path('app/public');
                if (is_dir($root)) {
                    foreach (File::allFiles($root) as $f) {
                        $zip->addFile($f->getPathname(), 'files/' . $f->getRelativePathname());
                        $files++;
                    }
                }
            }
            $zip->addFromString('اقرأني.txt', static::readme($driver, $files));
            $zip->close();
        } finally {
            File::deleteDirectory($tmp);
        }
        static::prune();

        return ['name' => $name, 'path' => $path, 'size' => filesize($path)];
    }

    /** نُبقي آخر N نسخة فقط */
    public static function prune(): void
    {
        $keep = max(1, (int) AppSettings::get('backup_keep', 8));
        foreach (array_slice(static::list(), $keep) as $old) {
            @unlink(static::dir() . '/' . $old['name']);
        }
    }

    /** يبعث النسخة: إيميل (بالمرفق إذا صغيرة) + رسالة واتساب للإدارة */
    public static function send(array $b): array
    {
        $res = ['email' => null, 'whatsapp' => 0];
        $mb = round($b['size'] / 1048576, 1);
        $to = trim((string) AppSettings::get('backup_email'));
        if ($to) {
            try {
                $attach = $b['size'] <= 20 * 1048576;
                Mail::raw("النسخة الاحتياطية الأسبوعية الخاص بـ " . AppSettings::get('shop_name', 'Shop & Go') . "\n\nالملف: {$b['name']}\nالحجم: {$mb} MB\n"
                    . ($attach ? "النسخة مرفقة مع الإيميل. احفظها في مكان آمن." : "النسخة كبيرة على الإيميل، نزّلها من النظام: " . route('sg.backups')), function ($m) use ($to, $b, $attach) {
                        $m->to($to)->subject('🔒 نسخة احتياطية · ' . now('Africa/Tripoli')->format('Y-m-d'));
                        if ($attach) {
                            $m->attach($b['path'], ['as' => $b['name'], 'mime' => 'application/zip']);
                        }
                    });
                $res['email'] = 'sent';
            } catch (\Throwable $e) {
                report($e);
                $res['email'] = 'failed: ' . mb_substr($e->getMessage(), 0, 120);
            }
        }
        if (AppSettings::get('backup_whatsapp', true)) {
            $text = "🔒 النسخة الاحتياطية جاهزة\n{$b['name']} · {$mb} MB" . ($res['email'] === 'sent' ? "\nأُرسلت لإيميلك ✓" : ($to ? "\n⚠ الإيميل لم يصل، نزّلها من النظام" : '')) . "\n" . route('sg.backups');
            foreach (AppSettings::phonesFor('backup') as $ph) {
                if (MessageService::sendText('backup', $text, [$ph])) {
                    $res['whatsapp']++;
                }
            }
        }

        return $res;
    }

    protected static function readme(string $driver, int $files): string
    {
        return "نسخة احتياطية · " . now('Africa/Tripoli')->format('Y-m-d H:i') . "\n\n"
            . "database/  : باقية البيانات كاملة (" . ($driver === 'sqlite' ? 'SQLite' : 'MySQL') . ")\n"
            . "files/     : الصور والإيصالات المرفوعة ($files ملف)\n\n"
            . "الاسترجاع (للمبرمج):\n"
            . "  MySQL : mysql -u USER -p DBNAME < database/database.sql\n"
            . "  الملفات: انسخ محتوى files/ إلى storage/app/public/\n";
    }

    /** إذا mysqldump موجود على السيرفر نستعملوه (أسرع وأدق)، وإذا لا إرجاع للـ dump بـ PHP */
    protected static function mysqldump(string $file, string $tmp): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        $bin = trim((string) @shell_exec('command -v mysqldump 2>/dev/null'));
        if ($bin === '') {
            return false;
        }
        $c = config('database.connections.' . config('database.default'));
        $cnf = $tmp . '/my.cnf';
        file_put_contents($cnf, "[client]\nuser=\"{$c['username']}\"\npassword=\"{$c['password']}\"\nhost=\"{$c['host']}\"\nport=\"" . ($c['port'] ?? 3306) . "\"\n");
        @chmod($cnf, 0600);
        $cmd = escapeshellcmd($bin) . ' --defaults-extra-file=' . escapeshellarg($cnf) . ' --single-transaction --quick --routines --default-character-set=utf8mb4 ' . escapeshellarg($c['database']) . ' > ' . escapeshellarg($file) . ' 2>' . escapeshellarg($tmp . '/err.txt');
        exec($cmd, $out, $code);

        return $code === 0 && is_file($file) && filesize($file) > 100;
    }

    /** dump لـ MySQL بـ PHP (لا يحتاج mysqldump) */
    public static function dumpMysql(string $file): void
    {
        $pdo = DB::connection()->getPdo();
        $fh = fopen($file, 'w');
        fwrite($fh, "-- Shop & Go backup " . now()->toDateTimeString() . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach (DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) {
            $table = array_values((array) $row)[0];
            $create = array_values((array) DB::selectOne("SHOW CREATE TABLE `$table`"))[1];
            fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n$create;\n\n");
            $cols = null;
            $batch = [];
            foreach (DB::table($table)->cursor() as $r) {
                $r = (array) $r;
                $cols = $cols ?? '`' . implode('`,`', array_keys($r)) . '`';
                $batch[] = '(' . implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $r)) . ')';
                if (count($batch) >= 200) {
                    fwrite($fh, "INSERT INTO `$table` ($cols) VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                fwrite($fh, "INSERT INTO `$table` ($cols) VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            fwrite($fh, "\n");
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
    }
}
