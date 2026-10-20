<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Request;

/** رسالة «وصلت بضاعتك»: تُراجع البيانات والنص ثم تُرسل يدوياً */
class ArrivalNotify
{
    public static function previews($orders, string $type = 'arrived'): array
    {
        $tpl = MessageService::template($type);

        return [$tpl, $orders->mapWithKeys(fn ($o) => [$o->id => $tpl ? static::withIssues($o, MessageService::render($tpl->message, static::vars($o))) : ''])];
    }

    /** ⚠ لو على الطلب مشكلة مفتوحة (نواقص، تالف…) تنكتب في نفس رسالة «جاهز للاستلام» قبل عنوان الشركة */
    public static function withIssues(Order $o, string $text): string
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('order_issues')) {
            return $text;
        }
        $iss = \App\Models\OrderIssue::where('order_id', $o->id)->where('status', 'open')->get();
        if ($iss->isEmpty() || str_contains($text, '⚠️ ملاحظة على طلبك')) {
            return $text;
        }
        $line = "⚠️ ملاحظة على طلبك:\n" . $iss->map(fn ($i) => '• ' . $i->typeLabel() . ($i->qty ? ' (' . $i->qty . ' قطعة)' : '') . ($i->note ? ' — ' . $i->note : ''))->implode("\n")
            . "\nنتابعوها معاك، ونسوّوها وقت الاستلام 🤝";
        $pos = mb_strpos($text, '📍');

        return $pos !== false ? rtrim(mb_substr($text, 0, $pos)) . "\n\n" . $line . "\n\n" . mb_substr($text, $pos) : rtrim($text) . "\n\n" . $line;
    }

    public static function vars(Order $o): array
    {
        return $o->kind === 'shipping' ? ShippingService::vars($o) : MessageService::orderVars($o);
    }

    /** يرجع [المرسلة, الفاشلة, المعلّمة] */
    public static function send(Request $request, $orders): array
    {
        $data = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer', 'msg' => 'nullable|array', 'msg.*' => 'nullable|string|max:2000', 'mark_only' => 'nullable|boolean'],
            ['ids.required' => 'اختر طلباً واحداً على الأقل']);
        $orders = $orders->whereIn('id', $data['ids']);
        $sent = $failed = 0;
        $tpl = MessageService::template('arrived');
        foreach ($orders as $o) {
            if ($request->boolean('mark_only')) {
                $o->arrived_notified_at = now();
                $o->save();
                continue;
            }
            $text = trim((string) ($data['msg'][$o->id] ?? ''));
            if ($text === '' && $tpl) {
                $text = static::withIssues($o, MessageService::render($tpl->message, static::vars($o)));
            }
            $phones = array_values(array_filter([optional($o->customer)->phone_1, optional($o->customer)->phone_2]));
            if ($text !== '' && MessageService::sendText('arrived', $text, $phones, $o->customer, $o)) {
                $o->arrived_notified_at = now();
                $o->save();
                $sent++;
            } else {
                $failed++;
            }
        }

        return [$sent, $failed, $request->boolean('mark_only') ? $orders->count() : 0];
    }

    public static function flash(array $res)
    {
        [$sent, $failed, $marked] = $res;
        if ($marked) {
            return back()->with('success', 'تم تعليم ' . $marked . ' طلب كمُبلَّغ دون إرسال');
        }

        return back()->with($failed ? 'error' : 'success', 'تم إرسال ' . $sent . ' رسالة «وصلت بضاعتك»' . ($failed ? ' · تعذّر إرسال ' . $failed . ' (راجع سجل الواتساب)' : ''));
    }
}
