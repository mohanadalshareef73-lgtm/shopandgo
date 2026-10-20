<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Order;
use App\Models\Status;
use App\Models\AiRoles;
use App\Models\Notif_orgs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiRolesController extends Controller
{

    function __construct()
    {
        // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
        $this->middleware('permission:aiRoles-list', ['only' => ['index', 'show', 'download']]);
        $this->middleware('permission:aiRoles-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:aiRoles-edit', ['only' => ['edit', 'update', 'destroy2']]);
        $this->middleware('permission:aiRoles-delete', ['only' => ['destroy']]);
    }

    public function index()
    {
        $roles = AiRoles::paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.aiRoles.index', compact('roles','main_notifications'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    public function search(Request $request)
    {
        if (!empty($request)) {
            $query = $request->all();
        }
        if (!request('role') && !request('message')) {
            return redirect()->route('ai-roles.index')->with('error', 'الرجاء تعبئة احدي الخانات علي الاقل');
        }
        $roles = AiRoles::query(); // Start a new query
        if (request('role') != null) {
            $roles->orWhere('role', 'like', '%' . request('role') . '%');
        }
        if (request('message') != null) {
            $roles->orWhere('message', 'like', '%' . request('message') . '%');
        }
        $roles = $roles->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.aiRoles.index', compact('roles', 'query','main_notifications'));
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
                'role' => 'required|string|unique:ai_roles',
                'message' => 'required|string',
            ],
            [
                'message.required' => 'يجب ادخال تفاصيل القاعدة',
                'role.required' => 'يجب ادخال اسم القاعدة',
                'role.unique' => 'اسم القاعدة الذي قمت بإدخاله موجود',
            ]
        );

        DB::beginTransaction();
        try {
            $platform = new AiRoles;
            $platform->message = request('message');
            $platform->role = request('role');
            $platform->save();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "aiRoles";
            $log->title = " قاعدة ذكاء اصطناعي جديدة (" . request('role') . ")";
            $log->log = " تم اضافة قاعدة ذكاء اصطناعي جديدة (" . request('message') . ")";
            $log->save();

            DB::commit();
            return redirect()->back()->with('success', 'تــمــت إضــافــة قــاعـدة الـذكـاء الاصـطـنـاعـي بــنــجــاح');
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

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $role = AiRoles::find($id);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.aiRoles.edit', compact('role','main_notifications'));
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
        'role' => 'required|string|unique:ai_roles,role,' . $id,
        'message' => 'required|string',
    ],
    [
        'message.required' => 'يجب ادخال تفاصيل القاعدة',
        'role.required' => 'يجب ادخال اسم القاعدة',
        'role.unique' => 'اسم القاعدة الذي قمت بإدخاله موجود',
    ]
);

        DB::beginTransaction();
        try {
            $role = AiRoles::find($id);

            if (request('role') != $role->role && AiRoles::where('role', request('role'))->exists()) {
                DB::rollback();
                return redirect()->back()->with('error', 'اسم القاعدة الذي قمت بإدخاله موجود مسبقآ');
            }
            $role->message = request('message');
            $role->role = request('role');
            $role->update();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "aiRoles";
            $log->title = " تعديل قاعدة الذكاء الاصطناعي (" . request('role') . ")";
            $log->log = " تم تعديل بيانات قاعدة الذكاء الاصطناعي (" . request('message') . ")";
            $log->save();

            DB::commit();
            return redirect()->route('ai-roles.index')->with('success', 'تــم تـعـديـل بـيـانـات قــاعـدة الـذكـاء الاصـطـنـاعـي بــنــجــاح');
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
        DB::beginTransaction();
        try {
            $role = AiRoles::find($id);
            if(!$role){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }
                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "aiRoles";
                $log->title = " حذف قاعدة الذكاء الاصطناعي (" . $role->role . ") ";
                $log->log = " تم حذف قاعدة الذكاء الاصطناعي بتاريخ (" . now()->format('Y-m-d') . ") ";
                $log->save();
                $role->delete();
                DB::commit();
                return redirect()->route('ai-roles.index')->with('success', 'تــم حــذف قــاعـدة الـذكـاء الاصـطـنـاعـي بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }
}
