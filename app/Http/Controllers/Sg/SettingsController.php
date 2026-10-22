<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\Platform;
use App\Models\Status;
use App\Services\AppSettings;
use Illuminate\Http\Request;

/** إعدادات الواجهة الجديدة: المحل، الشحن، الرفوف، النقاط، التوصيل، الخزائن، حالات الرحلة، المنصات */
class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:system-edit');
    }

    public function index(Request $request)
    {
        return view('sg.settings.index', [
            'nav' => 'set', 'tab' => $request->input('tab', 'shop'), 's' => AppSettings::all(),
            'payments' => PaymentMethod::orderBy('id')->get(), 'statuses' => Status::orderBy('sort')->orderBy('id')->get(),
            'platforms' => Platform::visible()->orderBy('id')->get(),
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'shop_name' => 'nullable|string|max:80', 'shop_phone' => 'nullable|string|max:40', 'shop_address' => 'nullable|string|max:200', 'shop_hours' => 'nullable|string|max:120',
            'ship_price_per_kg' => 'nullable|numeric|min:0', 'ship_min' => 'nullable|numeric|min:0', 'cash_payment_id' => 'nullable|exists:payment_methods,id',
            'package_types' => 'nullable|string|max:500', 'shelf_rows' => 'nullable|string|max:200', 'shelf_per_row' => 'nullable|integer|min:1|max:200', 'shelf_reminder_days' => 'nullable|integer|min:1|max:90',
            'report_phones' => 'nullable|string|max:300', 'urgent_phones' => 'nullable|string|max:200', 'cart_wallet_alert' => 'nullable|numeric|min:0|max:100000',
            'points_per_order' => 'nullable|integer|min:0', 'points_per_100' => 'nullable|numeric|min:0', 'point_value' => 'nullable|numeric|min:0', 'points_min_redeem' => 'nullable|integer|min:0',
            'courier_default_fee' => 'nullable|numeric|min:0', 'bank_accounts_text' => 'nullable|string|max:1000',
            'shelf_remind_every' => 'nullable|integer|min:1|max:60', 'shelf_remind_max' => 'nullable|integer|min:0|max:50', 'shelf_remind_time' => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'debt_remind_days' => 'nullable|integer|min:0|max:365', 'debt_remind_every' => 'nullable|integer|min:1|max:90', 'debt_remind_min' => 'nullable|numeric|min:0',
            'daily_report_time' => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'backup_email' => 'nullable|email|max:150', 'backup_day' => 'nullable|integer|min:0|max:6', 'backup_time' => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'], 'backup_keep' => 'nullable|integer|min:1|max:52',
            'shein_card_digits' => 'nullable|integer|min:4|max:40', 'shein_pin_digits' => 'nullable|integer|min:2|max:12', 'card_sale_rate' => 'nullable|numeric|min:0|max:1000', 'points_mode' => 'nullable|in:money,coupon,both', 'coupon_points' => 'nullable|integer|min:1|max:100000', 'coupon_percent' => 'nullable|numeric|min:1|max:100', 'coupon_max' => 'nullable|numeric|min:0', 'coupon_days' => 'nullable|integer|min:0|max:3650',
            'order_prefix' => 'nullable|string|max:8', 'order_start' => 'nullable|integer|min:1',
            'portal_faq' => 'nullable|string|max:8000', 'deposit_min' => 'nullable|numeric|min:0', 'deposit_pct' => 'nullable|numeric|min:0|max:100', 'loyalty_t1' => 'nullable|numeric|min:0', 'loyalty_t2' => 'nullable|numeric|min:0', 'loyalty_t3' => 'nullable|numeric|min:0', 'loyalty_d1' => 'nullable|numeric|min:0', 'loyalty_d2' => 'nullable|numeric|min:0', 'loyalty_d3' => 'nullable|numeric|min:0', 'referral_points' => 'nullable|integer|min:0|max:100000', 'booking_phones' => 'nullable|string|max:800', 'wa_footer' => 'nullable|string|max:400', 'wa_cart_footer' => 'nullable|string|max:400', 'wa_ack_promo' => 'nullable|string|max:400', 'wa_quote_link_text' => 'nullable|string|max:600', 'transfer_bank_name' => 'nullable|string|max:80', 'transfer_holder' => 'nullable|string|max:120', 'transfer_account' => 'nullable|string|max:60', 'transfer_payment_id' => 'nullable|integer', 'btn_confirm' => 'nullable|string|max:20', 'btn_edit' => 'nullable|string|max:20', 'btn_cancel' => 'nullable|string|max:20', 'wa_rp_confirm' => 'nullable|string|max:2000', 'wa_rp_edit' => 'nullable|string|max:2000', 'wa_rp_courier' => 'nullable|string|max:2000', 'wa_ack_text' => 'nullable|string|max:2000', 'wa_ack_link' => 'nullable|string|max:1000', 'wa_cart_link_line' => 'nullable|string|max:600',
            'free_order_alert_days' => 'nullable|integer|min:1|max:60', 'trip_late_days' => 'nullable|integer|min:1|max:120', 'courier_writeoff_max' => 'nullable|numeric|min:0|max:100',
        ]);
        $split = fn ($v) => array_values(array_filter(array_map('trim', preg_split('/[,،\n]+/u', (string) $v))));
        $out = collect($data)->filter(fn ($v) => $v !== null)->all();
        foreach (['package_types', 'shelf_rows', 'report_phones', 'booking_phones', 'urgent_phones'] as $k) {
            if ($request->has($k)) {
                $out[$k] = $split($request->input($k));
            }
        }
        // أرقام الحجز بس (ما فيش قروبات) — ونفس القائمة اللي تطلع في ⚡ احجز
        if ($request->has('booking_phones')) {
            $out['booking_phones'] = array_values(array_filter($out['booking_phones'], fn ($x) => !str_contains($x, '@')));
            $out['booking_numbers'] = $out['booking_phones'];
        }
        // الخانات (تشغيل/إيقاف) الذي في الفورم تُرسل أسماءها في _bools[]
        foreach ((array) $request->input('_bools', []) as $k) {
            if (in_array($k, ['monthly_report_enabled', 'points_enabled', 'points_notify', 'courier_alert_whatsapp', 'portal_delivery_enabled', 'signup_enabled', 'otp_enabled', 'shelf_auto_remind', 'debt_remind_enabled', 'daily_report_enabled', 'backup_enabled', 'backup_whatsapp', 'backup_files', 'loyalty_enabled', 'biweekly_report_enabled', 'courier_auto_assign', 'wa_footer_enabled', 'courier_phone_hidden', 'wa_quote_link_mode', 'wa_buttons', 'courier_wa_notify'], true)) {
                $out[$k] = $request->boolean($k);
            }
        }
        // نصوص السلة: فارغ = يرجع للنص الافتراضي
        foreach (['wa_rp_confirm', 'wa_rp_edit', 'wa_rp_courier', 'wa_ack_text', 'wa_ack_link', 'wa_cart_link_line', 'btn_confirm', 'btn_edit', 'btn_cancel'] as $k) {
            if ($request->has($k) && trim((string) $request->input($k)) === '') {
                $out[$k] = '';
            }
        }
        AppSettings::put($out);

        return back()->with('success', 'تم حفظ الإعدادات');
    }

    public function payment(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:60|unique:payment_methods,name']);
        $p = new PaymentMethod;
        $p->name = $data['name'];
        $p->status = true;
        $p->save();

        return back()->with('success', 'انضافت الخزينة ' . $p->name);
    }

    public function paymentToggle(PaymentMethod $payment)
    {
        abort_if($payment->id == AppSettings::cashPaymentId(), 403, 'لا تستطيع أن توقف خزينة الكاش');
        $payment->status = !$payment->status;
        $payment->save();

        return back()->with('success', 'تم');
    }

    public function statuses(Request $request)
    {
        foreach ((array) $request->input('st', []) as $id => $row) {
            $st = Status::find($id);
            if (!$st) {
                continue;
            }
            if (!empty($row['delete'])) {
                $st->delete();
                continue;
            }
            $st->update(['name' => $row['name'] ?? $st->name, 'sort' => (int) ($row['sort'] ?? 0), 'stage' => in_array($row['stage'] ?? '', ['prep', 'way', 'arrived']) ? $row['stage'] : 'way', 'color' => $row['color'] ?? null]);
        }
        if ($request->filled('new_name')) {
            Status::create(['name' => trim($request->new_name), 'sort' => (int) Status::max('sort') + 1, 'stage' => $request->input('new_stage', 'way')]);
        }

        return back()->with('success', 'تم حفظ حالات الرحلة');
    }

    public function platforms(Request $request)
    {
        foreach ((array) $request->input('pf', []) as $id => $row) {
            $p = Platform::find($id);
            if ($p) {
                $p->name = $row['name'] ?? $p->name;
                $p->rate = $row['rate'] !== '' ? (float) $row['rate'] : null;
                $p->save();
            }
        }
        if ($request->filled('new_name')) {
            $p = new Platform;
            $p->name = trim($request->new_name);
            $p->rate = $request->new_rate !== null && $request->new_rate !== '' ? (float) $request->new_rate : null;
            $p->save();
        }

        return back()->with('success', 'تم حفظ المنصات');
    }

    /** 👥 جيب كل قروبات واتساب من Whatsy */
    public function groupsFetch(\App\Services\WhatsAppService $wa)
    {
        @set_time_limit(300);
        try {
            $list = $wa->groups();
        } catch (\Throwable $e) {
            return back()->with('error', 'ما قدرناش نجيبو القروبات: ' . mb_substr($e->getMessage(), 0, 150));
        }
        if (!$list) {
            return back()->with('warning', '⚠ Whatsy ما رجعش قروبات · ' . mb_substr((string) \Illuminate\Support\Facades\Cache::get('wa_groups_err'), 0, 900));
        }
        AppSettings::put(['wa_groups_list' => collect($list)->keyBy('id')->all(), 'wa_groups_at' => now()->toDateTimeString()]);

        return back()->with('success', '👥 لقينا ' . count($list) . ' قروب — اختار قروب الحجز واحفظ');
    }

    /** ✉️ رسالة تجربة لقروب */
    public function groupTest(Request $request, \App\Services\WhatsAppService $wa)
    {
        abort_unless(auth()->user()->hasRole('Admin'), 403);
        $id = trim((string) $request->input('id'));
        $ok = $id !== '' && $wa->sendToGroup($id, '🧪 تجربة من منظومة Shop & Go — لو وصلتكم الرسالة هذي، القروب مربوط ويوصله الحجز');
        $last = mb_substr((string) \Illuminate\Support\Facades\Cache::get('wa_group_last'), 0, 400);
        AppSettings::put(['wa_group_test' => ['id' => $id, 'ok' => $ok, 'msg' => $last, 'at' => now()->toDateTimeString()]]);

        return redirect()->route('sg.settings', ['tab' => 'shop'])->with($ok ? 'success' : 'error', $ok
            ? '✅ واتسي قبل الرسالة — شوف قروب منتظرة: لو وصلت قولي، ولو ما وصلتش قولي زادة'
            : '✕ واتسي رفض الرسالة · ' . $last);
    }

    /** شيل قروب واتساب من الحجز (من القائمة ومن أرقام الحجز) */
    public function groupRemove(Request $request)
    {
        $id = (string) $request->input('id');
        $seen = (array) AppSettings::get('wa_groups_seen', []);
        unset($seen[$id]);
        $recent = array_filter((array) AppSettings::get('wa_groups_recent', []), fn ($g, $k) => $k !== $id && ($g['id'] ?? null) !== $id, ARRAY_FILTER_USE_BOTH);
        $book = array_values(array_filter((array) AppSettings::get('booking_phones', []), fn ($x) => $x !== $id));
        AppSettings::put(['wa_groups_seen' => $seen, 'wa_groups_recent' => $recent, 'booking_phones' => $book]);
        if (!$request->expectsJson()) {
            return redirect()->route('sg.settings', ['tab' => 'shop'])->with('success', '🗑 انمسح القروب من القائمة');
        }

        return response()->json(['ok' => true]);
    }
}
