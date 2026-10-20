<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\User;
use App\Models\Notif_orgs;
use Illuminate\Http\Request;
use App\Events\PasswordChanged;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{

    function __construct()
    {
         $this->middleware('permission:user-list', ['only' => ['index','search']]);
         $this->middleware('permission:user-create', ['only' => ['create','store']]);
         $this->middleware('permission:user-edit', ['only' => ['edit','show','update']]);
         $this->middleware('permission:user-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = User::where([['id', '>', 1],['type_id', '<', 3]])->paginate(25);
        $roles = Role::where('id', '!=', 1)->get();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.user.index',compact('users','roles','main_notifications'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        request()->validate(
        [
            'name'  => "required|string",
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'roles' => 'required'
        ],
        [
            'name.required' => 'يجب إدخال الاسم',
            'email.required' => 'يجب اضافة البريد الالكتروني',
            'email.email' => 'يجب اضافة البريد الالكتروني',
            'email.unique' => 'البريد الالكتروني الذي قمت بإدخاله موجود',
            'password.required' => 'يجب اضافة كلمة المرور',
            'password.min' => 'يجب ان تكون كلمة المرور من 8 خانات علي الاقل',
            'password.confirmed' => 'كلمة المرور الجديدة غير مطابقة مع الاعادة',
            'roles.required' => 'يجب تحديد صلاحيات المستخدم',
        ]);

        DB::beginTransaction();
        try {
            if(request('roles') == '1'){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف لايمكن تحديد هذه الصلاحية');
            }
            $user = new User;
            $user->name = request('name');
            $user->email = request('email');
            $user->type_id = 2;
            $user->password = Hash::make(request('password'));
            if(request('status')){
                $user->status = true;
            }else{
                $user->status = false;
            }
            $user->save();
            $role = Role::find($request->input('roles'));
            $user->assignRole($role);

            DB::commit();
            return redirect()->back()->with('success','تــمــت إضــافــة مـسـتـخـدم بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user  = User::find($id);
        if($user->status){
            $user->status = false;
            $user->update();
            Log::create([
                'type' => 'user',
                'title' => ' الغاء تفعيل ('.$user->name.')',
                'log' => 'تم الغاء تفعيل حساب المستخدم  ('.$user->name.')',
                'user_id' => auth()->user()->id,
            ]);

            return redirect()->route('users.index')->with('success','تم الغاء تفعيل حساب المستخدم  ('.$user->name.')');
        }else{
            $user->status = true;
            $user->update();
            Log::create([
                'type' => 'user',
                'title' => ' تم تفعيل ('.$user->name.')',
                'log' => 'تم تفعيل حساب المستخدم  ('.$user->name.')',
                'user_id' => auth()->user()->id,
            ]);
            return redirect()->route('users.index')->with('success','تم تفعيل حساب المستخدم  ('.$user->name.')');
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = User::find($id);
        $roles = Role::where('id', '!=', 1)->get();
        $userRole = $user->roles->all();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.user.edit',compact('user','roles','userRole','main_notifications'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        request()->validate(
            [
                'name'  => "required|string",
                'roles' => 'required'
            ],
            [
                'name.required' => 'يجب إدخال الاسم',
                'roles.required' => 'يجب تحديد صلاحيات المستخدم',
            ]);

            DB::beginTransaction();
            try {

                if(request('roles') == '1'){
                    DB::rollback();
                    return redirect()->back()->with('error', 'للاسف لايمكن تحديد هذه الصلاحية');
                }

                if(request('password')){
                    if(!request('password_confirmation')){
                        DB::rollback();
                        return redirect()->back()->with('error', 'كلمة المرور الجديدة غير مطابقة مع الاعادة');
                    }elseif(strlen(request('password')) < 8){
                        DB::rollback();
                        return redirect()->back()->with('error', 'يجب ان تكون كلمة المرور من 8 خانات علي الاقل');
                    }elseif(request('password') != request('password_confirmation')){
                        DB::rollback();
                        return redirect()->back()->with('error', 'كلمة المرور الجديدة غير مطابقة مع الاعادة');
                    }
                }

                $user = User::find($id);
                $user->name = request('name');
                if(request('status')){
                    $user->status = true;
                }else{
                    $user->status = false;
                }
                // $user->email = request('email');
                if(request('password')){
                    $user->password = Hash::make(request('password'));
                    $user->update();
                    // Auth::logoutOtherDevices(request('password'));
                    event(new PasswordChanged($user));
                }else{
                    $user->update();
                }
                DB::table('model_has_roles')->where('model_id',$id)->delete();
                $role = Role::find($request->input('roles'));
                $user->assignRole($role);

            DB::commit();
            return redirect()->route('users.index')->with('success','تــم تـعـديـل بـيـانـات المـسـتـخـدم بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = User::find($id);
        if($user->active){
            $user->active = false;
            $user->update();
            return redirect()->back()->with('success','تــم تــغــيــر حــالــة المـسـتـخـدم بــنــجــاح');
        }else{
            $user->active = true;
            $user->update();
            return redirect()->back()->with('success','تــم تــغــيــر حــالــة المـسـتـخـدم بــنــجــاح');
        }
    }
}
