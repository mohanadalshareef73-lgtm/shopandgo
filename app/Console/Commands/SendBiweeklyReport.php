<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Order;
use App\Services\AppSettings;
use App\Services\MessageService;
use App\Services\Period;
use App\Services\ReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** تقرير كل 14 يوم لأرقام الإدارة: الأرقام + أهم العملاء + اللي يحتاج انتباه */
class SendBiweeklyReport extends Command
{
    protected $signature = 'sg:biweekly-report {--force : يبعث توا حتى لو ما فاتتش 14 يوم}';

    protected $description = 'تقرير كل 14 يوم على الواتساب';

    public function handle(): int
    {
        if (!AppSettings::get('biweekly_report_enabled', true) && !$this->option('force')) {
            return self::SUCCESS;
        }
        $last = AppSettings::get('biweekly_last');
        if (!$this->option('force') && $last && now()->diffInDays(\Carbon\Carbon::parse($last)) < 14) {
            return self::SUCCESS;
        }
        $phones = AppSettings::phonesFor('biweekly');
        if (!$phones) {
            $this->warn('لا يوجد أرقام للتقارير');

            return self::SUCCESS;
        }
        $from = now()->subDays(14)->startOfDay();
        $p = Period::make('custom', $from->toDateString(), now()->toDateString());
        $p->label = 'آخر 14 يوم (' . $from->format('d/m') . ' – ' . now()->format('d/m') . ')';
        $text = '📊 تقرير كل 14 يوم' . "\n" . ReportService::whatsappText(ReportService::summary($p));

        // أهم العملاء والجدد
        $top = Order::where('status', '!=', 'canceld')->where('created_at', '>=', $from)
            ->selectRaw('customer_id, SUM(total_price - COALESCE(discount,0)) s, COUNT(*) n')->groupBy('customer_id')->orderByDesc('s')->take(3)->get();
        $names = Customer::whereIn('id', $top->pluck('customer_id'))->pluck('name', 'id');
        $x = "\n\n👥 العملاء";
        $x .= "\n• جدد: " . Customer::where('created_at', '>=', $from)->count();
        foreach ($top as $i => $t) {
            $x .= "\n• " . ($i + 1) . '. ' . ($names[$t->customer_id] ?? '—') . ' — ' . number_format($t->s) . ' د.ل (' . $t->n . ' إيصال)';
        }

        // اللي يحتاج انتباه
        $warn = [];
        $rateAt = AppSettings::get('rate_updated_at');
        if (!$rateAt || \Carbon\Carbon::parse($rateAt)->lt(now()->subDay())) {
            $warn[] = 'سعر صرف السلات ما تحدثش من ' . ($rateAt ? \Carbon\Carbon::parse($rateAt)->diffForHumans() : 'مدة');
        }
        if (Schema::hasColumn('cart_quotes', 'portal_status') && ($n = \App\Models\CartQuote::where('portal_status', 'ordered')->count())) {
            $warn[] = $n . ' حجز سلة يستنى تأكيد';
        }
        if ($n = Order::where('arrived', true)->where('delivered', false)->where('status', '!=', 'canceld')->whereNotNull('arrived_at')->where('arrived_at', '<', now()->subDays(14)->toDateString())->count()) {
            $warn[] = $n . ' طلبية قاعدة في الرف أكثر من 14 يوم';
        }
        if (Schema::hasColumn('orders', 'rating') && ($n = Order::where('rating', '<=', 2)->where('rated_at', '>=', $from)->count())) {
            $warn[] = $n . ' تقييم منخفض (نجمتين أو أقل)';
        }
        if (Schema::hasTable('support_tickets') && ($n = \App\Models\SupportTicket::where('status', 'open')->count())) {
            $warn[] = $n . ' بلاغ عميل مفتوح';
        }
        $x .= "\n\n" . ($warn ? "⚠️ يحتاج انتباه\n• " . implode("\n• ", $warn) : '✅ ما فيش حاجة تستنى انتباه');

        foreach ($phones as $ph) {
            $ok = MessageService::sendText('report', $text . $x, [$ph]);
            $this->line(($ok ? '✓ ' : '✗ ') . $ph);
        }
        AppSettings::put(['biweekly_last' => now()->toIso8601String()]);

        return self::SUCCESS;
    }
}
