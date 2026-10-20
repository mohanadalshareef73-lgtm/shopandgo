<?php

namespace App\Services;

use App\Models\Treasury;
use App\Models\TreasuryDollar;
use App\Models\TreasuryEuro;
use App\Models\TreasuryHistory;
use RuntimeException;

/**
 * مكان واحد لأي حركة على الخزائن (دخول / خروج) مع تسجيلها في سجل الخزينة.
 * الخزائن مقسومة حسب العملة (دينار/دولار/يورو) وحسب طريقة الدفع (نقداً، مصرف، فيزا...).
 */
class TreasuryService
{
    public const CURRENCIES = [
        'dinar' => 'د.ل',
        'dollar' => '$',
    ];

    public const CURRENCY_NAMES = [
        'dinar' => 'دينار',
        'dollar' => 'دولار',
    ];

    /** تنسيق رقم للعرض: 1,250.5 */
    public static function fmt($n, int $dec = 2): string
    {
        $s = number_format((float) $n, $dec, '.', ',');

        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }

    public static function sign(?string $currency): string
    {
        return self::CURRENCIES[$currency ?? 'dinar'] ?? ($currency === 'euro' ? '€' : 'د.ل'); // اليورو انلغى، نخلي رمزه للسجلات القديمة
    }

    public static function modelFor(string $currency): string
    {
        return match ($currency) {
            'dinar' => Treasury::class,
            'dollar' => TreasuryDollar::class,
            'euro' => TreasuryEuro::class,
            default => throw new RuntimeException('عملة غير معروفة'),
        };
    }

    public static function row(string $currency, int $paymentId)
    {
        $model = self::modelFor($currency);
        $row = $model::where('payment_id', $paymentId)->lockForUpdate()->first();
        if (!$row) {
            $row = new $model;
            $row->payment_id = $paymentId;
            $row->total = 0;
            $row->save();
        }

        return $row;
    }

    public static function balance(string $currency, int $paymentId): float
    {
        $model = self::modelFor($currency);

        return (float) ($model::where('payment_id', $paymentId)->value('total') ?? 0);
    }

    /** دخول أموال للخزينة */
    public static function deposit(float $amount, string $currency, int $paymentId, string $type, int $procedureId, ?string $note = null): TreasuryHistory
    {
        return self::move('pay', $amount, $currency, $paymentId, $type, $procedureId, $note);
    }

    /** خروج أموال من الخزينة. يرفض إذا الرصيد لا يكفي (إلا إذا allowNegative). */
    public static function withdraw(float $amount, string $currency, int $paymentId, string $type, int $procedureId, ?string $note = null, bool $allowNegative = false): TreasuryHistory
    {
        return self::move('pull', $amount, $currency, $paymentId, $type, $procedureId, $note, $allowNegative);
    }

    protected static function move(string $status, float $amount, string $currency, int $paymentId, string $type, int $procedureId, ?string $note, bool $allowNegative = false): TreasuryHistory
    {
        if ($amount <= 0) {
            throw new RuntimeException('القيمة يجب أن تكون أكبر من صفر');
        }

        $row = self::row($currency, $paymentId);
        $before = (float) $row->total;
        $after = $status === 'pay' ? $before + $amount : $before - $amount;

        if ($status === 'pull' && !$allowNegative && $after < -0.0001) {
            $name = optional(\App\Models\PaymentMethod::find($paymentId))->name;
            throw new RuntimeException('الرصيد في خزينة (' . $name . ' - ' . (self::CURRENCY_NAMES[$currency] ?? $currency) . ') لا يكفي. الرصيد الحالي ' . round($before, 2) . ' ' . self::sign($currency));
        }

        $history = new TreasuryHistory;
        $history->price = $amount;
        $history->price_before = $before;
        $history->price_after = $after;
        $history->status = $status;
        $history->type = $type;
        $history->procedure_id = $procedureId;
        $history->created_id = auth('web')->id() ?? \App\Models\User::orderBy('id')->value('id'); // أوامر النظام بدون مستخدم
        $history->currency = $currency;
        $history->currency_sign = self::sign($currency);
        $history->payment_id = $paymentId;
        $history->note = $note;
        $history->save();

        $row->total = $after;
        $row->save();

        return $history;
    }
}
