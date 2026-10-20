<?php

namespace App\Services;

use App\Http\Controllers\Sg\ClientsController;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/** حملات واتساب: رسالة وحدة كل دقيقة-دقيقتين، بحد يومي، ونخلّو مكان لرسايل الشغل العادية */
class CampaignService
{
    public const SEGMENTS = [
        'active' => ['🟢 نشطين', 'طلبوا آخر 60 يوم'],
        'medium' => ['🟡 متوسطين', 'آخر طلب من 2 لـ 6 شهور'],
        'idle' => ['⚪ خاملين', 'أكثر من 6 شهور أو ما طلبوش'],
    ];

    public const DEFAULT_MESSAGE = "أهلاً {الاسم} 🌸\nعندنا خبر حلو ليك من Shop & Go 💙\n\nولّى عندك *حساب خاص بيك* تقدر منه:\n🛒 تحسب سعر سلتك في ثواني وتحجز بروحك\n📦 تتابع طلبياتك لحظة بلحظة\n🛵 تطلب توصيل لين حوشك\n⭐ تجمع نقاط وتاخذ خصومات\n\nادخل برقم هاتفك 👇\nhttps://shopandgo.ly/login";

    /** نحسبو كل عميل في أي مجموعة */
    public static function segmentQuery()
    {
        $last = ClientsController::sql()['last_at'];

        return Customer::query()->select('customers.id', 'customers.name', 'customers.phone_1')->selectRaw("$last as last_at")
            ->whereNotNull('customers.phone_1')->where('customers.phone_1', '!=', '');
    }

    public static function segmentOf($lastAt): string
    {
        if (!$lastAt) {
            return 'idle';
        }
        $d = \Carbon\Carbon::parse($lastAt);

        return $d->gte(now()->subDays(60)) ? 'active' : ($d->gte(now()->subDays(180)) ? 'medium' : 'idle');
    }

    public static function counts(): array
    {
        $out = ['active' => 0, 'medium' => 0, 'idle' => 0];
        foreach (static::segmentQuery()->cursor() as $c) {
            $out[static::segmentOf($c->last_at)]++;
        }

        return $out;
    }

    /** نعبّو المستلمين بالترتيب: نشطين أول، بعدين متوسطين، بعدين خاملين (ونشيلو الأرقام المكررة) */
    public static function fill(int $campaignId, array $segments, array $sessions = []): int
    {
        $order = array_flip(array_keys(self::SEGMENTS));
        $seen = [];
        $rows = [];
        $sessions = array_values(array_filter($sessions)) ?: [null];
        foreach (static::segmentQuery()->cursor() as $c) {
            $seg = static::segmentOf($c->last_at);
            if (!in_array($seg, $segments, true)) {
                continue;
            }
            $ph = MessageService::normalize((string) $c->phone_1);
            if (strlen($ph) < 11 || isset($seen[$ph])) {
                continue;
            }
            $seen[$ph] = true;
            $recency = $c->last_at ? (int) now()->diffInDays(\Carbon\Carbon::parse($c->last_at)) : 99999;
            $rows[] = ['campaign_id' => $campaignId, 'customer_id' => $c->id, 'segment' => $seg, 'prio' => $order[$seg] * 100000 + min(99999, $recency), 'phone' => $c->phone_1, 'status' => 'pending', 'session_uuid' => null];
        }
        // نوزعو العملاء على الأرقام بالتناوب (كل عميل رقم واحد بس، والنشطين يتقسمو بالتساوي)
        usort($rows, fn ($a, $b) => $a['prio'] <=> $b['prio']);
        foreach ($rows as $i => &$row) {
            $row['session_uuid'] = $sessions[$i % count($sessions)];
        }
        unset($row);
        foreach (array_chunk($rows, 500) as $ch) {
            DB::table('campaign_recipients')->insertOrIgnore($ch);
        }

        return count($rows);
    }

    /** كم رسالة تبعتت اليوم من كل المنظومة (من سجل الواتساب) */
    public static function sentToday(): int
    {
        try {
            return (int) DB::table('whatsapp_outbox')->where('created_at', '>=', now()->startOfDay())->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function dailyLimit(): int
    {
        return (int) AppSettings::get('wa_daily_limit', 500);
    }

    public static function reserve(): int
    {
        return (int) AppSettings::get('wa_daily_reserve', 150); // نخلّو للطلبيات والتنبيهات العادية
    }

    /** يتنادى كل دقيقة: من كل رقم رسالة وحدة على الأكثر، لو الوقت مناسب */
    public static function tick(): ?string
    {
        $now = now()->setTimezone('Africa/Tripoli');
        $c = DB::table('campaigns')->where('status', 'running')->orderBy('id')->first();
        if (!$c) {
            return null;
        }
        if ($now->hour < $c->from_hour || $now->hour >= $c->to_hour) {
            return 'برا وقت الإرسال';
        }
        $pending = DB::table('campaign_recipients')->where('campaign_id', $c->id)->where('status', 'pending');
        if (!(clone $pending)->exists()) {
            DB::table('campaigns')->where('id', $c->id)->update(['status' => 'done', 'updated_at' => now()]);

            return 'الحملة كملت';
        }
        $out = [];
        foreach ((clone $pending)->distinct()->pluck('session_uuid') as $ses) {
            $mine = fn () => DB::table('campaign_recipients')->where('campaign_id', $c->id)->where(fn ($q) => $ses === null ? $q->whereNull('session_uuid') : $q->where('session_uuid', $ses));
            // مسافة عشوائية 60–150 ثانية بين كل رسالة والثانية من نفس الرقم
            $last = $mine()->where('status', '!=', 'pending')->max('sent_at');
            if ($last && \Carbon\Carbon::parse($last)->gt(now()->subSeconds(random_int(60, 150)))) {
                continue;
            }
            if ($mine()->where('status', 'sent')->where('sent_at', '>=', now()->startOfDay())->count() >= $c->per_day) {
                $out[] = 'حد اليوم';
                continue;
            }
            // الرقم الأساسي: نخلّو مكان لرسايل الطلبيات والتنبيهات
            if (($ses === null || $ses === static::mainSession()) && static::sentToday() >= static::dailyLimit() - static::reserve()) {
                $out[] = 'خلّينا الباقي للشغل';
                continue;
            }
            $r = $mine()->where('status', 'pending')->orderBy('prio')->first();
            if (!$r) {
                continue;
            }
            $cu = Customer::withoutGlobalScopes()->find($r->customer_id);
            if ($cu && SgButtons::optedOut((string) $r->phone)) {
                DB::table('campaign_recipients')->where('id', $r->id)->update(['status' => 'skipped', 'error' => 'طلب ما يجيهش رسايل', 'sent_at' => now()]);
                continue;
            }
            if (!$cu) {
                DB::table('campaign_recipients')->where('id', $r->id)->update(['status' => 'skipped', 'error' => 'العميل مش موجود', 'sent_at' => now()]);
                continue;
            }
            $text = strtr($c->message, ['{الاسم}' => trim((string) $cu->name) ?: 'عميلنا العزيز', '{كود_الزبون}' => (string) $cu->code]);
            $ok = false;
            try {
                $ok = MessageService::sendText('campaign', $text, [$r->phone], $cu, null, null, $ses);
            } catch (\Throwable $e) {
                report($e);
            }
            DB::table('campaign_recipients')->where('id', $r->id)->update(['status' => $ok ? 'sent' : 'failed', 'sent_at' => now(), 'error' => $ok ? null : 'ما وصلتش']);
            $out[] = $ok ? 'sent' : 'failed';
        }

        return implode(' · ', $out);
    }

    public static function mainSession(): ?string
    {
        try {
            return \App\Models\Setting::find(1)?->whatsappMainSessionUuid() ?? config('services.whatsapp.main_session_uuid');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
