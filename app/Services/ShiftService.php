<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\TreasuryHistory;
use App\Models\User;

/**
 * وردية الموظف: الكاش الذي قبضه أو صرفه من وقت ما فتح الوردية.
 * (الحركات متسجلة في سجل الخزينة باسم الموظف created_id)
 */
class ShiftService
{
    public static function current(User $user): ?Shift
    {
        return Shift::where('user_id', $user->id)->whereNull('closed_at')->latest('opened_at')->first();
    }

    /** يفتح وردية إذا لا يوجد (أول حركة في اليوم) */
    public static function ensureOpen(User $user, float $opening = 0): Shift
    {
        return static::current($user) ?: Shift::create(['user_id' => $user->id, 'opened_at' => now(), 'opening_cash' => $opening]);
    }

    public static function summary(User $user, ?Shift $shift = null): array
    {
        $shift = $shift ?: static::current($user);
        $since = $shift ? $shift->opened_at : now()->startOfDay();
        $cash = AppSettings::cashPaymentId();

        $q = fn () => TreasuryHistory::where('created_id', $user->id)->where('created_at', '>=', $since)
            ->when($shift && $shift->closed_at, fn ($q) => $q->where('created_at', '<=', $shift->closed_at))
            ->where('currency', 'dinar');

        $cashIn = (float) $q()->where('payment_id', $cash)->where('status', 'pay')->sum('price');
        $cashOut = (float) $q()->where('payment_id', $cash)->where('status', 'pull')->sum('price');
        $otherIn = (float) $q()->where('payment_id', '!=', $cash)->where('status', 'pay')->sum('price');
        $opening = $shift ? (float) $shift->opening_cash : 0;

        return [
            'shift' => $shift,
            'since' => $since,
            'opening' => $opening,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'other_in' => $otherIn,
            'expected' => $opening + $cashIn - $cashOut,
        ];
    }

    public static function close(User $user, float $counted, ?string $note = null): Shift
    {
        $shift = static::ensureOpen($user);
        $s = static::summary($user, $shift);
        $shift->update([
            'closed_at' => now(),
            'expected_cash' => $s['expected'],
            'counted_cash' => $counted,
            'diff' => round($counted - $s['expected'], 2),
            'note' => $note,
            'closed_by' => auth()->id(),
        ]);

        return $shift;
    }
}
