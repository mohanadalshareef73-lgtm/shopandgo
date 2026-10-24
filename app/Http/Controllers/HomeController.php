<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Log;
use App\Models\Org;
use App\Models\Trip;
use App\Models\User;
use App\Models\Order;
use App\Models\Report;
use App\Models\Product;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Provider;
use App\Models\Notif_orgs;
use App\Models\PaymentType;
use Illuminate\Support\Str;

use App\Models\Notification;
use Illuminate\Http\Request;
use App\Events\PasswordChanged;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

class HomeController extends Controller
{

public function __construct()
{
    $this->middleware(function ($request, $next) {
        if (!auth('customer')->check() && !auth('web')->check()) {
            return redirect()->route('customer.login');
        }
        return $next($request);
    });
}

    public function test()
    {
        $user = User::find(1);
        $role = Role::where('name', 'Admin')->first();
        // $role = Role::create([
        //     'id' => 1,
        //     'name' => 'Admin'
        // ]);
        $permissions = Permission::all();
        $role->syncPermissions($permissions);
        $user->assignRole($role->name);
        $user->update();
        return redirect()->back()->with("success", "تم تعديل بنجاح");
    }


    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
public function index()
{
    // If Customer logged in
    if (auth('customer')->check()) {
        return redirect()->route('customer.dashboard');
    }

    // المندوب
    if (auth('web')->check() && auth('web')->user()->type_id == 4) {
        return redirect()->route('courier.home');
    }

    // If Admin logged in
    if (auth('web')->check()) {
        return redirect()->route('admin.home');
    }

    // If none (shouldn’t happen because of middleware)
    return redirect()->route('login');
}



    public function admin(Request $request, \App\Services\OwnerDashboard $dashboard)
    {
        $period = in_array($request->query('period'), ['today', 'week', 'month'], true) ? $request->query('period') : 'month';
        $queue = in_array($request->query('queue'), ['ready', 'unpaid', 'no_trip', 'late_trips'], true) ? $request->query('queue') : 'ready';
        $main_notifications = Notif_orgs::where('user_id', auth()->id())->latest()->take(5)->get();

        return view('admin.dashboard', $dashboard->data(auth()->user(), $period, $queue) + compact('main_notifications'));
    }


    public function create(Request $request)
    {
        $path = base_path('.env');
        $test = file_get_contents($path);

        if (file_exists($path)) {
            if (config('system.active')) {
                file_put_contents($path, str_replace('SYSTEM_ACTIVE=true', 'SYSTEM_ACTIVE=false', $test));
            } else {
                file_put_contents($path, str_replace('SYSTEM_ACTIVE=false', 'SYSTEM_ACTIVE=true', $test));
            }
        } else {
            return redirect()->back()->with('error', 'حدث خطأ ما الرجاء إعادة المحاولة...');
        }

        if (config('system.active')) {
            Log::create([
                'title' => 'إيقاف النظام',
                'log' => 'تم إلغاء تفعيل النظام بنجاح',
                'type' => 'system',
                'user_id' => auth()->user()->id,
            ]);
            return redirect()->back()->with('error', 'تم إلغاء تفعيل النظام بنجاح');
        } else {
            Log::create([
                'title' => 'تشغيل النظام',
                'log' => 'تم تفعيل النظام بنجاح',
                'type' => 'system',
                'user_id' => auth()->user()->id,
            ]);
            return redirect()->back()->with('success', 'تم تفعيل النظام بنجاح');
        }
    }


    public function changePassword()
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
        if ((Hash::check(request('current_password'), auth()->user()->password))) {
            User::find(auth()->user()->id)->update(['password' => Hash::make(request('new_password'))]);
            $user = User::find(auth()->user()->id);
            event(new PasswordChanged($user));
        } else {
            return redirect()->back()->with("error", "كلمة المرور الحالية غير صحيحة");
        }
        return redirect()->back()->with("success", "تم تغيير كلمة المرور بنجاح");
    }

}
