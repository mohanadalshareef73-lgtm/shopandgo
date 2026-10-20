<?php

namespace App\Services;

/** تصدير جدول كملف CSV يفتح في Excel بالعربي */
class Csv
{
    public static function download(string $name, array $head, iterable $rows)
    {
        return response()->streamDownload(function () use ($head, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $head);
            foreach ($rows as $r) {
                fputcsv($out, array_values((array) $r));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
