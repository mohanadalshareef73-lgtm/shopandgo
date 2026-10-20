<?php

namespace App\Http\Controllers\Auth;

use App\Models\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class AdminLoginController extends Controller
{


    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }


    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function logout(Request $request)
    {
       $this->guard()->logout();
       $request->session()->invalidate();
       return $this->loggedOut($request) ?: redirect('/login');
     }

    public function login(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string|max:120',
            'password' => 'required|string|max:200',
        ]);
        $login = trim((string) $request->name);
        if ($sec = \App\Services\LoginGuard::lockedFor('admin', $login)) {
            return redirect()->route('admin.login')->withInput($request->only('name'))->with('error', \App\Services\LoginGuard::lockedMessage($sec));
        }
        // نلقو الحساب بالاسم (بدون فرق حروف كبيرة/صغيرة وفراغات زايدة) أو الإيميل أو رقم الهاتف
        $norm = mb_strtolower(preg_replace('/\s+/u', ' ', $login));
        $digits = preg_replace('/\D/', '', $login);
        $u = \App\Models\User::whereRaw('LOWER(TRIM(name)) = ?', [$norm])->first()
            ?? \App\Models\User::whereRaw('LOWER(email) = ?', [$norm])->first()
            ?? (strlen($digits) >= 9 ? \App\Models\User::where('phone', 'like', '%' . substr($digits, -9))->first() : null);
        $pw = (string) $request->password;
        $ok = $u && (\Illuminate\Support\Facades\Hash::check($pw, (string) $u->password) || (trim($pw) !== $pw && \Illuminate\Support\Facades\Hash::check(trim($pw), (string) $u->password)));
        if ($ok) {
            auth()->login($u, $request->boolean('remember'));
        }
        if ($ok) {
            if (!auth()->user()->status) {
                Auth::logout();

                return redirect()->route('admin.login')->with('error', 'تم إيقاف حسابك، يرجى التواصل مع الإدارة');
            }
            $request->session()->regenerate();
            \App\Services\LoginGuard::clear('admin', $login);
            Log::create(['user_id' => auth()->id(), 'type' => 'login', 'title' => ' تسجيل دخول ناجح (' . e($login) . ')', 'log' => ' تسجيل دخول ناجح · IP ' . $request->ip()]);
            // الدخول جاء من صفحة «تحديث النظام» → نرجعوله
            if ($request->session()->pull('sg.after_login') === 'system-update') {
                return redirect()->route('system.update');
            }

            return redirect()->route('home');
        }
        \App\Services\LoginGuard::failed('admin', $login);
        Log::create(['type' => 'login', 'title' => ' محاولة تسجيل دخول فاشلة (' . e(mb_substr($login, 0, 60)) . ')', 'log' => ' محاولة دخول فاشلة · IP ' . $request->ip()]);

        return redirect()->route('admin.login')->withInput($request->only('name'))->with('error', $u ? '🔒 كلمة المرور غلط — لو نسيتها، المدير يغيرها من صفحة الموظفين' : '👤 اسم المستخدم «' . e(mb_substr($login, 0, 40)) . '» مش موجود — اكتب الاسم زي ما سجله المدير، أو رقم هاتفك');
    }
}
