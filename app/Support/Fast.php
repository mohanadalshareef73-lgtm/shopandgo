<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** فحص الجداول والأعمدة مرة كل ساعة بدل كل صفحة (المنظومة أخف) */
class Fast
{
    protected static array $mem = [];

    public static function hasColumn(string $table, string $col): bool
    {
        $k = "schema.$table.$col";

        return static::$mem[$k] ??= (bool) Cache::remember($k, 3600, fn () => Schema::hasColumn($table, $col));
    }

    public static function hasTable(string $table): bool
    {
        $k = "schema.$table";

        return static::$mem[$k] ??= (bool) Cache::remember($k, 3600, fn () => Schema::hasTable($table));
    }
}
