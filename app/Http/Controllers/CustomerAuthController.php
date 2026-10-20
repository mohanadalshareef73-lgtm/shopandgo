<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CustomerAuthController extends Controller
{

    public function index()
    {
        $customer = auth('customer')->user();
       $orders = Order::where('customer_id', $customer->id)
    ->whereDate('created_at', '>=', '2025-09-08')
    ->latest()
    ->paginate(30);
        $platforms = Platform::all();
        $page = "all";
        return view('customers.dashboard', compact('orders', 'platforms', 'page'));
    }

    public function changePasswordPage()
    {
        return view('customers.change_password');
    }


    public function showLoginForm()
    {
        return view('customers.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone_1' => 'required|string|max:30',
            'password' => 'required|string|max:200',
        ]);
        $generic = 'رقم الهاتف أو كلمة المرور غير صحيحة';
        // يدخل برقم الهاتف أو بالكود
        $login = trim($request->phone_1);
        if ($sec = \App\Services\LoginGuard::lockedFor('customer', $login)) {
            return back()->withInput($request->only('phone_1'))->with('error', \App\Services\LoginGuard::lockedMessage($sec));
        }
        $customer = Customer::withoutGlobalScope('approved')->where('phone_1', $login)->first()
            ?? Customer::withoutGlobalScope('approved')->where('code', $login)->first();
        if (!$customer && str_contains($login, '@')) {
            // الموظفون والمندوبون يدخلون من صفحة الفريق
            return redirect()->route('admin.login')->withInput(['name' => $login])->with('warning', 'هذه صفحة دخول العملاء. الموظفون والمندوبون يدخلون من هذه الصفحة');
        }
        if (!$customer) {
            // موظف أو مندوب كتب اسمه في صفحة العملاء؟ نوديوه لصفحة الفريق
            $norm = mb_strtolower(preg_replace('/\s+/u', ' ', $login));
            $staff = \App\Models\User::whereRaw('LOWER(TRIM(name)) = ?', [$norm])->exists();
            if ($staff) {
                return redirect()->route('admin.login')->withInput(['name' => $login])->with('warning', '💼 هذي صفحة العملاء — الموظفين والمندوبين يدخلوا من هني');
            }
            \App\Services\LoginGuard::failed('customer', $login);

            return back()->withInput($request->only('phone_1'))->with('error', '📱 رقمك هذا مش مسجل عندنا. اعمل حساب جديد في دقيقة 👇')->with('no_account', true);
        }
        if ($customer->signup_status === 'pending') {
            return back()->withInput($request->only('phone_1'))->with('warning', 'حسابك قيد المراجعة، وسنبلغك عبر واتساب فور الموافقة عليه');
        }
        if ($customer->signup_status === 'rejected') {
            return back()->withInput($request->only('phone_1'))->with('error', 'لم تتم الموافقة على هذا الحساب، يرجى التواصل معنا');
        }
        try {
            if (!$customer->password) {
                // أول دخول: كلمة المرور = رقم الهاتف، ثم (إن كان مفعّلاً) رمز تحقق عبر واتساب، ثم يختار كلمة مرور جديدة
                if (trim((string) $request->password) !== (string) $customer->phone_1) {
                    \App\Services\LoginGuard::failed('customer', $login);

                    return $this->otpAfterWrong($request, $customer, '🔒 أول دخول: كلمة المرور هي رقم هاتفك');
                }
                if (\App\Services\Otp::enabled()) {
                    if (!\App\Services\Otp::send('first', $customer->phone_1)) {
                        return back()->withInput($request->only('phone_1'))->with('error', 'تعذّر إرسال رمز التحقق إلى واتساب، يرجى التواصل معنا');
                    }
                    session(['otp_flow' => ['purpose' => 'first', 'customer_id' => $customer->id, 'phone' => $customer->phone_1, 'at' => time()]]);

                    return redirect()->route('customer.verify');
                }
                Auth::guard('customer')->login($customer);
                $request->session()->regenerate();

                return redirect()->route('customer.change.password.page')->with('warning', 'يجب عليك تغيير كلمة المرور أولاً');
            }
            if (Hash::check((string) $request->password, (string) $customer->password)) {
                Auth::guard('customer')->login($customer);
                $request->session()->regenerate();
                \App\Services\LoginGuard::clear('customer', $login);

                return redirect()->intended(route('customer.dashboard')); // لو جا من رابط (مثلاً السلات) يرجع ليه
            }
            \App\Services\LoginGuard::failed('customer', $login);

            return $this->otpAfterWrong($request, $customer, '🔒 كلمة المرور غلط');
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput($request->only('phone_1'))->with('error', 'حدث خطأ، يرجى المحاولة مرة أخرى');
        }
    }

    /** 📲 كلمة مرور غلط: يجيه رمز على الواتساب طول، يدخل بيه ويختار كلمة مرور جديدة */
    protected function otpAfterWrong(Request $request, Customer $customer, string $why)
    {
        try {
            $again = \App\Services\Otp::cooldownLeft('reset', (string) $customer->phone_1) > 0;
            if (\App\Services\Otp::send('reset', (string) $customer->phone_1)) {
                session(['otp_flow' => ['purpose' => 'reset', 'customer_id' => $customer->id, 'phone' => $customer->phone_1, 'at' => time()]]);

                return redirect()->route('customer.verify')->with('warning', $why . ($again
                    ? ' — الرمز انبعتلك قبل شوية على الواتساب 📲 اكتبه هني'
                    : ' — بعتنالك رمز على الواتساب 📲 اكتبه هني، وبعدها اختار كلمة مرور جديدة'));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->withInput($request->only('phone_1'))->with('error', $why)->with('wrong_pw', true);
    }

    public function logout()
    {
        Auth::guard('customer')->logout();
        return redirect()->route('customer.login');
    }

    public function changePassword(Request $request)
    {
        request()->validate(
            [
                'new_password' => ['required', 'min:8'],
                'new_confirm_password' => 'same:new_password',
            ],
            [
                'new_password.required' => 'يجب اضافة كلمة المرور الجديدة',
                'new_password.min' => 'يجب ان تكون كلمة المرور من 8 خانات علي الاقل',
                'new_confirm_password.same' => ' كلمة المرور الجديدة غير مطابقة مع الاعادة',
            ]
        );
        // الحساب المسجل دخوله فقط (لا نثق في customer_id القادم من الفورم)
        $customer = auth('customer')->user();
        $customer->update(['password' => Hash::make($request->input('new_password'))]);
        return redirect()->route('customer.dashboard')->with("success", "تم تغيير كلمة المرور بنجاح");
    }


        public function changeNewPassword(Request $request)
    {
        request()->validate(
            [
                'current_password' => 'required',
                'new_password' => ['required', 'min:8'],
                'new_confirm_password' => 'same:new_password',
            ],
            [
                'current_password.required' => 'يجب اضافة كلمة المرور الحالية',
                'new_password.required' => 'يجب اضافة كلمة المرور الجديدة',
                'new_password.min' => 'يجب ان تكون كلمة المرور من 8 خانات علي الاقل',
                'new_confirm_password.same' => ' كلمة المرور الجديدة غير مطابقة مع الاعادة',
            ]
        );
        $customer = auth('customer')->user(); // الحساب المسجل دخوله فقط
        if (!Hash::check((string) $request->input('current_password'), (string) $customer->password)) {
            return back()->with('error', 'كلمة المرور الحالية غير صحيحة');
        }
        $customer->update(['password' => Hash::make($request->input('new_password'))]);
        return redirect()->route('customer.dashboard')->with("success", "تم تغيير كلمة المرور بنجاح");
    }



    public function search(Request $request)
    {
        if (!empty($request)) {
            $query = $request->all();
        }
        if (!request('code')) {
            return redirect()->route('customer.dashboard')->with('error', 'الرجاء تعبئة احدي الخانات علي الاقل');
        }
        $orders = Order::where([['customer_id', auth('customer')->user()->id],['code', 'like', '%' . request('code') . '%']])
        ->whereDate('created_at', '>=', '2025-09-08')->latest()->paginate(30);
        $page = "all";
        $platforms = Platform::all();
        return view('customers.dashboard', compact('orders','platforms', 'query','page'));
    }


}
