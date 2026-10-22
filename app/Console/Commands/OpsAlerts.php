<?php

namespace App\Console\Commands;

use App\Http\Controllers\TripPurchaseController;
use App\Models\TripPurchase;
use App\Services\AlertService;
use App\Services\AppSettings;
use Illuminate\Console\Command;

/**
 * تنبيهات يومية للإدارة:
 *  - طلبات ليست في رحلة منذ أكثر من N يوم
 *  - رحلات مضى على شرائها أكثر من N يوم ولم تصل
 */
class OpsAlerts extends Command
{
    protected $signature = 'sg:ops-alerts';

    protected $description = 'تنبيهات الطلبات خارج الرحلات والرحلات المتأخرة';

    public function handle(): int
    {
        $days = max(1, (int) AppSettings::get('free_order_alert_days', 2));
        $free = TripPurchaseController::freeOrdersQuery()->where('created_at', '<=', now()->subDays($days))->get();
        if ($free->count()) {
            AlertService::push('free_orders', '🧾 ' . $free->count() . ' طلب ليس في أي رحلة منذ أكثر من ' . $days . ' يوم',
                $free->take(6)->map(fn ($o) => $o->code . ' (' . optional($o->customer)->name . ')')->implode('، ') . ($free->count() > 6 ? '…' : ''),
                route('finance.trips.create'), 'warn', true);
        }

        // تنبيهات تأخير قديمة لرحلات قبل رحلة البداية (B-0392) تتعلّم مقروءة وما تزعجش
        try {
            $okIds = TripPurchase::alertable()->pluck('id');
            $oldUrls = TripPurchase::whereNotIn('id', $okIds)->pluck('id')->map(fn ($id) => route('finance.trips.show', $id));
            if ($oldUrls->count()) {
                \App\Models\SgAlert::where('type', 'trip_late')->whereNull('read_at')->whereIn('url', $oldUrls->all())->update(['read_at' => now()]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $late = (int) AppSettings::get('trip_late_days', 10);
        $trips = TripPurchase::alertable()->with('orders')->where('purchased_at', '<=', now()->subDays($late)->toDateString())->get()
            ->filter(function ($t) {
                $o = $t->orders->where('status', '!=', 'canceld');

                return $o->count() && $o->where('arrived', false)->count() === $o->count();
            });
        foreach ($trips as $t) {
            if ($t->late_alert_at && \Carbon\Carbon::parse($t->late_alert_at)->gt(now()->subDays(2))) {
                continue; // لا نكرر التنبيه كل يوم
            }
            $age = (int) \Carbon\Carbon::parse($t->purchased_at)->diffInDays(now());
            AlertService::push('trip_late', '✈ الرحلة ' . $t->trip_code . ' تأخرت: ' . $age . ' يوماً ولم تصل',
                $t->orders->count() . ' إيصال · الحالة: ' . ($t->summary()['status'] ?: '—'), route('finance.trips.show', $t->id), 'warn', true);
            $t->late_alert_at = now();
            $t->saveQuietly();
        }
        $this->info('free=' . $free->count() . ' late=' . $trips->count());

        return self::SUCCESS;
    }
}
