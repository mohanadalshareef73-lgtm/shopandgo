<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Order;
use App\Models\Status;
use App\Models\Platform;
use App\Models\Notif_orgs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformController extends Controller
{

    function __construct()
    {
        // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
        $this->middleware('permission:platform-list', ['only' => ['index', 'show', 'download']]);
        $this->middleware('permission:platform-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:platform-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:platform-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $platforms = Platform::paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.platform.index', compact('platforms','main_notifications'));
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
        if (!request('name') && !request('rate')) {
            return redirect()->route('platform.index')->with('error', 'الرجاء تعبئة احدي الخانات علي الاقل');
        }
        $platforms = Platform::query(); // Start a new query
        if (request('name') != null) {
            $platforms->orWhere('name', 'like', '%' . request('name') . '%');
        }
        if (request('rate') != null) {
            $platforms->orWhere('rate', 'like', '%' . request('rate') . '%');
        }
        $platforms = $platforms->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.platform.index', compact('platforms', 'query','main_notifications'));
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
                'name' => 'required|string|unique:platforms',
            ],
            [
                'name.required' => 'يجب ادخال اسم المنصة',
                'name.unique' => 'اسم المنصة الذي قمت بإدخاله موجود',
            ]
        );

        DB::beginTransaction();
        try {
            $platform = new Platform;
            $platform->name = request('name');
            $platform->rate = request('rate');
            $platform->save();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "platform";
            $log->title = " منصة جديدة (" . request('name') . ")";
            $log->log = " تم اضافة منصة جديدة (" . request('name') . ")";
            $log->save();

            DB::commit();
            return redirect()->back()->with('success', 'تــمــت إضــافــة مــنــصــة بــنــجــاح');
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
        $platform = Platform::find($id);
        $orders = Order::where('platform_id', $id)->paginate(25);
            $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
                    $status = Status::get();

        return view('admin.platform.show', compact('orders', 'platform','main_notifications','status'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $platform = Platform::find($id);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.platform.edit', compact('platform','main_notifications'));
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
            ],
            [
                'name.required' => 'يجب إدخال اسم المنصة',
            ]
        );

        DB::beginTransaction();
        try {
            $platform = Platform::find($id);

            if (request('name') != $platform->name && Platform::where('name', request('name'))->exists()) {
                DB::rollback();
                return redirect()->back()->with('error', 'اسم المنصة الذي قمت بإدخاله موجود مسبقآ');
            }
            $platform->name = request('name');
            $platform->rate = request('rate');
            $platform->update();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "platform";
            $log->title = " تعديل المنصة (" . request('name') . ")";
            $log->log = " تم تعديل بيانات المنصة (" . request('name') . ")";
            $log->save();

            DB::commit();
            return redirect()->route('platform.index')->with('success', 'تــم تـعـديـل بـيـانـات الــمـنـصـة بــنــجــاح');
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
            $platform = Platform::find($id);
            if(!$platform){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }
            if($platform->orders->count() > 0){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف لايمكن حذف المنصة وذلك لوجد طلبات خاصة بالمنصة');
            }
                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "platform";
                $log->title = " حذف المنصة (" . $platform->name . ") DYL";
                $log->log = " تم حذف المنصة بتاريخ (" . now()->format('Y-m-d') . ") ";
                $log->save();
                $platform->delete();
                DB::commit();
                return redirect()->route('platform.index')->with('success', 'تــم حــذف الـمـنـصـة بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }
}
