<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** ⚡ فهارس للسرعة: البحث وفتح الواجهات (الطلبات، السلات، التوصيل، الرحلات) — ما تغيّرش أي بيانات */
return new class extends Migration
{
    protected array $want = [
        'orders' => [
            'sg_ord_stage' => ['status', 'delivered', 'arrived'],
            'sg_ord_trip' => ['trip_details'],
            'sg_ord_created' => ['created_at'],
            'sg_ord_cust' => ['customer_id', 'status'],
            'sg_ord_code' => ['code'],
            'sg_ord_platform' => ['platform_id'],
        ],
        'customers' => [
            'sg_cust_code' => ['code'],
            'sg_cust_name' => ['name'],
            'sg_cust_ph2' => ['phone_2'],
        ],
        'cart_quotes' => [
            'sg_cq_order' => ['order_id'],
            'sg_cq_cust' => ['customer_id'],
            'sg_cq_portal' => ['portal_status'],
            'sg_cq_created' => ['created_at'],
        ],
        'deliveries' => [
            'sg_dl_status' => ['status'],
            'sg_dl_cart' => ['cart_id'],
            'sg_dl_cust' => ['customer_id'],
        ],
        'trip_purchases' => [
            'sg_tp_code' => ['trip_code'],
            'sg_tp_date' => ['purchased_at'],
        ],
        'order_issues' => [
            'sg_oi_order' => ['order_id', 'status'],
        ],
        'paid_histories' => [
            'sg_ph_order' => ['order_id'],
        ],
        'purchases' => [
            'sg_pu_date' => ['date'],
        ],
        'sg_alerts' => [
            'sg_al_read' => ['read_at'],
        ],
    ];

    public function up()
    {
        foreach ($this->want as $table => $indexes) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $name => $cols) {
                try {
                    if (array_filter($cols, fn ($c) => !Schema::hasColumn($table, $c))) {
                        continue; // عمود ناقص: نتخطاو
                    }
                    if ($this->covered($table, $cols)) {
                        continue; // فيه فهرس يبدا بنفس الأعمدة
                    }
                    // نصوص طويلة: نفهرسو أول 64 حرف بس
                    $parts = array_map(function ($c) use ($table) {
                        $type = DB::selectOne('SELECT DATA_TYPE t, CHARACTER_MAXIMUM_LENGTH l FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $c]);
                        $long = $type && in_array(strtolower($type->t), ['varchar', 'char', 'text'], true) && ((int) $type->l > 64 || strtolower($type->t) === 'text');

                        return '`' . $c . '`' . ($long ? '(64)' : '');
                    }, $cols);
                    DB::statement('ALTER TABLE `' . $table . '` ADD INDEX `' . $name . '` (' . implode(', ', $parts) . ')');
                } catch (\Throwable $e) {
                    // فهرس ما تعملش؟ ما يوقفش التحديث
                    report($e);
                }
            }
        }
    }

    protected function covered(string $table, array $cols): bool
    {
        $rows = DB::select('SELECT INDEX_NAME n, COLUMN_NAME c, SEQ_IN_INDEX s FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX', [$table]);
        $byIdx = [];
        foreach ($rows as $r) {
            $byIdx[$r->n][] = $r->c;
        }
        foreach ($byIdx as $icols) {
            if (array_slice($icols, 0, count($cols)) === $cols) {
                return true;
            }
        }

        return false;
    }

    public function down()
    {
    }
};
