<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** صور الإيصالات والتسليم: نصغروها (أقصى 1600px، JPEG جودة 75) قبل الحفظ باش الاستضافة ما تتعباش */
class ImageShrink
{
    public static function store(UploadedFile $file, string $dir, string $disk = 'local'): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || !function_exists('imagecreatefromstring')) {
            return $file->store($dir, $disk); // PDF أو صيغة ثانية: زي ما هي
        }
        try {
            $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
            if (!$src) {
                return $file->store($dir, $disk);
            }
            $w = imagesx($src);
            $h = imagesy($src);
            $scale = min(1, 1600 / max($w, $h));
            $dst = imagecreatetruecolor(max(1, (int) ($w * $scale)), max(1, (int) ($h * $scale)));
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
            ob_start();
            imagejpeg($dst, null, 75);
            $data = ob_get_clean();
            imagedestroy($src);
            imagedestroy($dst);
            // لو الأصلية أصغر، نخلوها
            if (strlen($data) >= $file->getSize()) {
                return $file->store($dir, $disk);
            }
            $path = trim($dir, '/') . '/' . Str::random(32) . '.jpg';
            Storage::disk($disk)->put($path, $data);

            return $path;
        } catch (\Throwable $e) {
            return $file->store($dir, $disk);
        }
    }
}
