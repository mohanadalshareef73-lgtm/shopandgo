<?php

namespace App\Support;

use App\Http\Controllers\Portal\SignupController;
use App\Http\Controllers\Sg\ClientsController;
use App\Models\Customer;

/**
 * عميل حجز من غير حساب: نلقوه برقمه، ولو ما عندهش حساب نفتحوله واحد أوتوماتيك.
 * الحساب الجديد بدون كلمة مرور: أول دخول كلمة المرور = رقم هاتفه + رمز على الواتساب، وبعدها يختار كلمة جديدة.
 */
class GuestAccount
{
    /** @return array{0: ?Customer, 1: bool} [العميل، جديد؟] */
    public static function resolve(?string $name, ?string $phone, ?string $address = null, array $extra = []): array
    {
        // 👤 «افتحله حساب عادي»: الموظف كتب الاسم/الرقم/الكود/المدينة/المنطقة بيده
        $name = trim((string) ($extra['name'] ?? '')) ?: $name;
        $phone = trim((string) ($extra['phone'] ?? '')) ?: $phone;
        $hasCity = \App\Support\Fast::hasColumn('customers', 'city');
        $city = trim((string) ($extra['city'] ?? ''));
        $area = trim((string) ($extra['area'] ?? ''));
        $norm = SignupController::normalizePhone((string) $phone);
        $tail = substr($norm, -9);
        if (strlen($tail) < 9) {
            return [null, false];
        }
        $c = Customer::withoutGlobalScope('approved')->where('phone_1', $norm)->first()
            ?? Customer::withoutGlobalScope('approved')->where('phone_1', 'like', '%' . $tail)->first();
        if ($c) {
            if ($c->signup_status === 'pending') {
                $c->signup_status = null; // أكدنا طلبه، يتفعّل حسابه
                $c->save();
            }
            if (empty($c->address) && $address) {
                $c->address = mb_substr(trim($address), 0, 190);
                $c->save();
            }
            if ($hasCity && ($city !== '' || $area !== '')) {
                $c->city = $c->city ?: ($city ?: null);
                $c->area = $c->area ?: ($area ?: null);
                $c->save();
            }

            return [$c, false];
        }
        $c = new Customer;
        $c->name = mb_substr(trim((string) $name) ?: 'عميل', 0, 120);
        $c->phone_1 = $norm;
        $c->address = $address ? mb_substr(trim($address), 0, 190) : null;
        $want = trim((string) ($extra['code'] ?? ''));
        if ($want !== '' && Customer::withoutGlobalScope('approved')->where('code', $want)->exists()) {
            throw new \RuntimeException('الكود ' . $want . ' مستعمل — جرب: ' . implode('، ', ClientsController::codeSuggestions($want, $norm)));
        }
        $c->code = $want !== '' ? $want : ClientsController::codeFor($norm);
        if ($hasCity) {
            $c->city = $city ?: null;
            $c->area = $area ?: null;
        }
        $c->password = null; // أول دخول: رقم الهاتف + رمز واتساب
        $c->save();
        try {
            \App\Models\Log::create(['user_id' => auth('web')->id(), 'type' => 'customer', 'title' => ' حساب جديد أوتوماتيك (' . $c->name . ')', 'log' => ' انفتح وقت تأكيد حجزه · ' . $c->phone_1 . ' · كود ' . $c->code]);
        } catch (\Throwable $e) {
        }

        return [$c, true];
    }

    /** سطر الحساب اللي يتزاد تحت رسالة التأكيد/الإيصال */
    public static function accountText(Customer $c, bool $new): string
    {
        $url = rtrim((string) config('app.url'), '/') . '/login';
        try {
            $url = route('customer.login');
        } catch (\Throwable $e) {
        }
        if ($new) {
            return "🎉 *فتحنالك حساب في Shop & Go*\n"
                . "تتابع منه طلباتك وإيصالاتك وتحسب سلاتك بروحك 👇\n{$url}\n"
                . "📱 ادخل برقمك: {$c->phone_1}\n🔑 أول مرة كلمة المرور هي *رقم هاتفك*، ويجيك رمز على الواتساب، وبعدها تختار كلمة مرور جديدة.";
        }

        return "👤 *حسابك في Shop & Go* (لو ناسيه)\n{$url}\n"
            . "📱 ادخل برقمك: {$c->phone_1} · كودك: {$c->code}\n🔑 نسيت كلمة المرور؟ اضغط «نسيت كلمة المرور» ويجيك رمز على الواتساب تغيرها بيه.";
    }
}
