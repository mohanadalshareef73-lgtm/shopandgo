<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Notif_orgs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{

        /**
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */

    function __construct()
    {
        // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
        $this->middleware('permission:role-list', ['only' => ['index','show','search']]);
        $this->middleware('permission:role-create', ['only' => ['create','store']]);
        $this->middleware('permission:role-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:role-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $roles = Role::where('id', '!=', 1)->orderBy('id','DESC')->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.role.index', compact('roles','main_notifications'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $permission = Permission::where('id', '>', 12)->get();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.role.create',compact('permission','main_notifications'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|unique:roles,name',
            'permission' => 'required',
           ],
           [
               'name.required' => 'يجب إدخال اسم الصلاحية',
               'name.unique' => 'اسم الصلاحية موجود مسبقآ',
               'permission.required' => 'يجب اختيار اذونات الصلاحية',
           ]);
           $role = Role::create(['name' => $request->input('name')]);

           $permissions = Permission::whereIn('id', $request->input('permission'))->get();
           $role->syncPermissions($permissions);
           return redirect()->route('roles.index')
                           ->with('success','تم أنشاء الصلاحية بنجاح');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
       $role = Role::find($id);
       $rolePermissions = Permission::join("role_has_permissions","role_has_permissions.permission_id","=","permissions.id")
           ->where("role_has_permissions.role_id",$id)
           ->get();
           $permission = Permission::where('id', '>', 12)->get();
           $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
           ->latest()
           ->take(5)
           ->get();
       return view('admin.role.show',compact('role','rolePermissions','permission','main_notifications'));
    }

       /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        if (!empty($request)) {
            $query = $request->all();
        }
        $conditions = [];
        $conditions[] = ['name', 'LIKE', '%' . request('name') . '%'];
        $conditions[] = ['id', '!=' , 1];
        $roles = Role::where($conditions)->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.role.index', compact('roles', 'query','main_notifications'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
       $role = Role::find($id);
       $permission = Permission::where('id', '>', 12)->get();
       $rolePermissions = DB::table("role_has_permissions")->where("role_has_permissions.role_id",$id)
           ->pluck('role_has_permissions.permission_id','role_has_permissions.permission_id')->toArray();
           $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
           ->latest()
           ->take(5)
           ->get();
       return view('admin.role.edit',compact('role','permission','rolePermissions','main_notifications'));
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
       $this->validate($request, [
        'name' => 'required',
        'permission' => 'required',
    ],
    [
        'name.required' => 'يجب إدخال اسم الصلاحية',
        'permission.required' => 'يجب اختيار اذونات الصلاحية',
    ]);

    $role = Role::find($id);
    if($role->name != $request->input('name')){
         if(Role::where('name', $request->input('name'))->exists()){
             return redirect()->route('roles.index')
             ->with('error','اسم الصلاحية الجديد موجود مسبقآ...');
         }
    }
    $role->name = $request->input('name');
    $role->save();
    $permissions = Permission::whereIn('id', $request->input('permission'))->get();
    $role->syncPermissions($permissions);
    return redirect()->route('roles.index')
                    ->with('success','تم تعديل بيانات الصلاحية بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        DB::table("roles")->where('id',$id)->delete();
        return redirect()->route('roles.index')
                        ->with('success','Role deleted successfully');
    }
}
