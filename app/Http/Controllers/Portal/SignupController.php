<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Sg\ClientsController;
use App\Models\Customer;
use App\Models\Log;
use App\Services\AlertService;
use App\Services\AppSettings;
use App\Services\Otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * تسجيل عميل جديد بنفسه:
 *  1) يعبّئ البيانات (مع حماية من البوتات: حقل مخفي + وقت أدنى + حد للطلبات)
 *  2) رمز تحقق عبر واتساب (إن كان مفعّلاً) — لا يُحفظ شيء قبل التحقق من الرقم
 *  3) يُحفظ الحساب «قيد المراجعة» ويصل تنبيه للإدارة، ولا يدخل حتى الموافقة
 */
class SignupController extends Controller
{
    protected function enabled(): bool
    {
        return (bool) AppSettings::get('signup_enabled', true);
    }

    public static function normalizePhone(string $p): string
    {
        $p = preg_replace('/\D/', '', strtr($p, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));
        if (str_starts_with($p, '00218')) {
            $p = substr($p, 5);
        } elseif (str_starts_with($p, '218')) {
            $p = substr($p, 3);
        }
        if ($p !== '' && $p[0] !== '0') {
            $p = '0' . $p;
        }

        return $p;
    }

    public function form()
    {
        if (auth('customer')->check()) {
            return redirect()->route('customer.dashboard');
        }
        abort_unless($this->enabled(), 404);
        if (request()->filled('ref')) {
            session(['ref_code' => substr((string) request('ref'), 0, 20)]); // ادعُ صديق
        }

        return view('customers.register', ['ts' => Crypt::encryptString((string) time()), 's' => AppSettings::all()]);
    }

    public function store(Request $request)
    {
        abort_unless($this->enabled(), 404);
        // حماية من البوتات: الحقل المخفي يجب أن يبقى فارغاً، والنموذج لا يُرسل خلال أقل من 4 ثوانٍ
        $started = 0;
        try {
            $started = (int) Crypt::decryptString((string) $request->input('ts'));
        } catch (\Throwable $e) {
        }
        if (filled($request->input('website')) || !$started || time() - $started < 4 || time() - $started > 7200) {
            return back()->withInput($request->except('password', 'password_confirmation'))->with('error', 'تعذّر إرسال النموذج، يرجى المحاولة مرة أخرى');
        }
        $request->merge(['phone_1' => static::normalizePhone((string) $request->phone_1), 'phone_2' => $request->filled('phone_2') ? static::normalizePhone((string) $request->phone_2) : null]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[\p{Arabic}\p{L}\s\.\-]+$/u'],
            'phone_1' => ['required', 'regex:/^09[1-6]\d{7}$/'],
            'phone_2' => ['nullable', 'regex:/^0\d{8,9}$/', 'different:phone_1'],
            'city' => 'required|string|min:2|max:80',
            'area' => 'required|string|min:2|max:120',
            'address' => 'nullable|string|max:150',
            'password' => 'required|string|min:8|max:100|confirmed',
            'agree' => 'accepted',
        ], [
            'name.required' => 'اكتب الاسم الكامل', 'name.min' => 'الاسم قصير جداً', 'name.regex' => 'الاسم يجب أن يحتوي على حروف فقط',
            'phone_1.required' => 'اكتب رقم الهاتف', 'phone_1.regex' => 'رقم الهاتف غير صحيح (مثال: 0912345678)',
            'phone_2.regex' => 'رقم الهاتف الثاني غير صحيح', 'phone_2.different' => 'الرقم الثاني مطابق للأول',
            'city.required' => 'اكتب المدينة', 'area.required' => 'اكتب المنطقة',
            'password.required' => 'اختر كلمة مرور', 'password.min' => 'كلمة المرور 8 خانات على الأقل', 'password.confirmed' => 'تأكيد كلمة المرور غير مطابق',
            'agree.accepted' => 'يجب الموافقة على الشروط',
        ]);
        $exists = Customer::withoutGlobalScope('approved')->where('phone_1', $data['phone_1'])->orWhere('phone_2', $data['phone_1'])->first();
        if ($exists) {
            $msg = $exists->signup_status === 'pending' ? 'لديك طلب تسجيل قيد المراجعة بهذا الرقم، سنبلغك عند الموافقة' : 'هذا الرقم مسجّل لدينا مسبقاً، سجّل الدخول به (أول مرة: كلمة المرور هي رقم هاتفك)';

            return redirect()->route('customer.login')->withInput(['phone_1' => $data['phone_1']])->with('warning', $msg);
        }
        $pending = [
            'name' => trim(preg_replace('/\s+/u', ' ', $data['name'])), 'phone_1' => $data['phone_1'], 'phone_2' => $data['phone_2'] ?? null,
            'city' => strip_tags(trim($data['city'])), 'area' => strip_tags(trim($data['area'])),
            'address' => strip_tags(trim(($data['address'] ?? '') ?: (trim($data['city']) . ' - ' . trim($data['area'])))), 'password' => Hash::make($data['password']),
        ];
        if (Otp::enabled()) {
            if (!Otp::send('signup', $pending['phone_1'])) {
                return back()->withInput($request->except('password', 'password_confirmation'))->with('error', 'تعذّر إرسال رمز التحقق إلى واتساب على هذا الرقم. تأكد أن الرقم عليه واتساب، أو حاول لاحقاً');
            }
            session(['otp_flow' => ['purpose' => 'signup', 'data' => Crypt::encrypt($pending), 'phone' => $pending['phone_1'], 'at' => time()]]);

            return redirect()->route('customer.verify');
        }
        $this->createPending($pending, false);

        return redirect()->route('customer.login')->with('success', 'تم استلام طلب التسجيل ✅ سنراجعه ونبلغك عبر واتساب فور الموافقة');
    }

    /** نسيت كلمة المرور: رمز على الواتساب ويختار كلمة جديدة */
    public function forgotForm()
    {
        return view('customers.forgot', ['s' => AppSettings::all()]);
    }

    public function forgot(Request $request)
    {
        $request->validate(['phone_1' => 'required|string|max:30'], ['phone_1.required' => 'اكتب رقم هاتفك']);
        $login = trim($request->phone_1);
        $c = Customer::withoutGlobalScope('approved')->where('phone_1', $login)->first()
            ?? Customer::withoutGlobalScope('approved')->where('phone_1', static::normalizePhone($login))->first()
            ?? Customer::withoutGlobalScope('approved')->where('code', $login)->first();
        if (!$c) {
            return back()->withInput()->with('error', '📱 رقمك هذا مش مسجل عندنا. اعمل حساب جديد 👇')->with('no_account', true);
        }
        if ($c->signup_status === 'pending') {
            return back()->with('warning', 'حسابك لسه قيد المراجعة، نبلغوك على الواتساب أول ما يتفعّل');
        }
        if (!Otp::send('reset', $c->phone_1)) {
            return back()->withInput()->with('error', 'ما قدرناش نبعتو الرمز على الواتساب، تواصل معانا');
        }
        session(['otp_flow' => ['purpose' => 'reset', 'customer_id' => $c->id, 'phone' => $c->phone_1, 'at' => time()]]);

        return redirect()->route('customer.verify')->with('success', 'بعتنالك رمز على الواتساب ✓ اكتبه هني');
    }

    protected function flow(): ?array
    {
        $f = session('otp_flow');

        return is_array($f) && time() - (int) ($f['at'] ?? 0) < 1800 ? $f : null;
    }

    public function verifyForm()
    {
        $f = $this->flow();
        if (!$f) {
            return redirect()->route('customer.login')->with('error', 'انتهت الجلسة، يرجى البدء من جديد');
        }

        return view('customers.verify', ['phone' => $f['phone'], 'purpose' => $f['purpose'], 'wait' => Otp::cooldownLeft($f['purpose'], $f['phone']), 's' => AppSettings::all()]);
    }

    public function resend()
    {
        $f = $this->flow();
        if (!$f) {
            return redirect()->route('customer.login');
        }
        if (Otp::cooldownLeft($f['purpose'], $f['phone']) > 0) {
            return back()->with('warning', 'انتظر قليلاً قبل طلب رمز جديد');
        }

        $ok = Otp::send($f['purpose'], $f['phone']);

        return back()->with($ok ? 'success' : 'error', $ok ? 'أُرسل رمز جديد إلى واتساب' : 'تعذّر إرسال الرمز');
    }

    public function verify(Request $request)
    {
        $f = $this->flow();
        if (!$f) {
            return redirect()->route('customer.login')->with('error', 'انتهت الجلسة، يرجى البدء من جديد');
        }
        $code = preg_replace('/\D/', '', strtr((string) $request->input('code'), ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));
        $res = Otp::check($f['purpose'], $f['phone'], $code);
        if ($res !== 'ok') {
            if ($res === 'locked' || $res === 'expired') {
                session()->forget('otp_flow');

                return redirect()->route($f['purpose'] === 'signup' ? 'customer.register' : 'customer.login')->with('error', Otp::message($res));
            }

            return back()->with('error', Otp::message($res));
        }
        session()->forget('otp_flow');
        if ($f['purpose'] === 'signup') {
            $this->createPending(Crypt::decrypt($f['data']), true);

            return redirect()->route('customer.login')->with('success', 'تم التحقق من رقمك واستلام طلب التسجيل ✅ سنبلغك عبر واتساب فور الموافقة على حسابك');
        }
        if ($f['purpose'] === 'reset') {
            $c = Customer::withoutGlobalScope('approved')->find($f['customer_id'] ?? 0);
            if (!$c) {
                return redirect()->route('customer.login');
            }
            \App\Services\LoginGuard::clear('customer', (string) $c->phone_1);
            \App\Services\LoginGuard::clear('customer', (string) $c->code);
            $c->phone_verified_at = $c->phone_verified_at ?: now();
            $c->save();
            Auth::guard('customer')->login($c);
            $request->session()->regenerate();

            return redirect()->route('customer.change.password.page')->with('warning', '✅ تم التحقق — اختار كلمة مرور جديدة');
        }
        // أول دخول لعميل موجود
        $c = Customer::find($f['customer_id'] ?? 0);
        if (!$c || $c->password) {
            return redirect()->route('customer.login');
        }
        $c->password = Hash::make($c->phone_1);
        $c->phone_verified_at = now();
        $c->save();
        Auth::guard('customer')->login($c);
        $request->session()->regenerate();

        return redirect()->route('customer.change.password.page')->with('warning', 'تم التحقق ✅ يجب عليك الآن اختيار كلمة مرور جديدة');
    }

    protected function createPending(array $d, bool $verified): Customer
    {
        // تكرار سريع (نقرتان) أو سباق: لا ننشئ حساباً ثانياً بنفس الرقم
        if ($c = Customer::withoutGlobalScope('approved')->where('phone_1', $d['phone_1'])->first()) {
            return $c;
        }
        $taken = fn ($code) => Customer::withoutGlobalScope('approved')->where('code', $code)->exists();
        $code = ClientsController::codeSuggestions('', $d['phone_1'], $taken, 1)[0] ?? ('N' . substr($d['phone_1'], -6) . random_int(10, 99));
        $c = new Customer;
        $c->name = $d['name'];
        $c->phone_1 = $d['phone_1'];
        $c->phone_2 = $d['phone_2'];
        $c->address = $d['address'];
        if (\App\Support\Fast::hasColumn('customers', 'city')) {
            $c->city = $d['city'] ?? null;
            $c->area = $d['area'] ?? null;
        }
        $c->password = $d['password'];
        $c->code = $code;
        $c->signup_status = 'pending';
        if (($ref = session('ref_code')) && \Illuminate\Support\Facades\Schema::hasColumn('customers', 'referred_by')) {
            $c->referred_by = Customer::withoutGlobalScope('approved')->where('code', $ref)->value('id');
        }
        $c->signup_at = now();
        $c->signup_ip = request()->ip();
        $c->phone_verified_at = $verified ? now() : null;
        $c->save();
        try {
            \App\Http\Controllers\Portal\CartsController::claimGuest($c); // السلات اللي حسبها قبل التسجيل تولي متاعه
        } catch (\Throwable $e) {
        }
        Log::create(['type' => 'customer', 'title' => ' طلب تسجيل عميل جديد (' . $c->name . ')', 'log' => ' ' . $c->phone_1 . ' · ' . ($verified ? 'الرقم مُتحقَّق منه' : 'بدون تحقق') . ' · IP ' . request()->ip()]);
        try {
            AlertService::push('signup', '👤 طلب تسجيل عميل جديد', $c->name . ' · ' . $c->phone_1 . ' · ' . $c->address, route('sg.signups'), 'info', true);
        } catch (\Throwable $e) {
            report($e);
        }

        return $c;
    }
}
