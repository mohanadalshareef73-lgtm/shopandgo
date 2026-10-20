<?php

namespace App\Http\Controllers\Auth;

use App\Models\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

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


    public function loginPage()
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
        $input = $request->all();
        $this->validate($request, [
            'name' => 'required',
            'password' => 'required',
        ]);
        $fieldType = filter_var($request->name, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';
        if(auth()->attempt(array($fieldType => $input['name'], 'password' => $input['password'])))
        {
            if(!auth()->user()->status){
                Auth::logout();
                return redirect()->route('login')->with('error', 'تم إلغاء تفعيل حسابك الرجاء التواصل مع المسؤولين');
            }
           $log = new Log;
           $log->user_id = auth()->user()->id;
           $log->type = "login";
           $log->title = " تسجيل دخول ناجح (".request('name').")";
           $log->log = " تم تسجيل دخول ناجح من قبل (".request('name').")";
           $log->save();
             return redirect()->route('home');
        }else{
               $log = new Log;
               $log->type = "login";
               $log->title = " محاولة تسجيل دخول فاشلة (".request('name').")";
               $log->log = " تمت محاولة تسجيل دخول فاشلة من قبل (".request('name').")";
               $log->save();
            return redirect()->route('login')
                ->with('error','البريد الالكتروني او كلمة المرور غير صحيحة.');
        }
    }
    public function username()
    {
        return 'name';
    }


}
