<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Order;
use App\Models\Status;
use App\Models\Notif_orgs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatusController extends Controller
{

    function __construct()
    {
        // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
        $this->middleware('permission:status-list', ['only' => ['index', 'show', 'download']]);
        $this->middleware('permission:status-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:status-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:status-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $status = Status::paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.status.index', compact('status','main_notifications'));
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
        if (!request('name')) {
            return redirect()->route('status.index')->with('error', 'الرجاء تعبئة احدي الخانات علي الاقل');
        }
        $status = Status::query(); // Start a new query
        if (request('name') != null) {
            $status->orWhere('name', 'like', '%' . request('name') . '%');
        }
        $status = $status->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.status.index', compact('status', 'query','main_notifications'));
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
                'name' => 'required|string|unique:statuses',
            ],
            [
                'name.required' => 'يجب ادخال اسم حالة الرحلة',
                'name.unique' => 'اسم حالة الرحلة الذي قمت بإدخاله موجود',
            ]
        );

        DB::beginTransaction();
        try {
            $status = new Status;
            $status->name = request('name');
            $status->save();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "status";
            $log->title = " حالة الرحلة جديدة (" . request('name') . ")";
            $log->log = " تم اضافة حالة الرحلة جديدة (" . request('name') . ")";
            $log->save();

            DB::commit();
            return redirect()->back()->with('success', 'تــمــت إضــافــة حـالـة الـرحـلـة بــنــجــاح');
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
        $statusOne = Status::find($id);
        $orders = Order::where('trip_status', $statusOne->name)->paginate(25);
            $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
                    $status = Status::get();

        return view('admin.status.show', compact('orders', 'statusOne','main_notifications','status'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $status = Status::find($id);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.status.edit', compact('status','main_notifications'));
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
                'name.required' => 'يجب إدخال اسم حالة الرحلة',
            ]
        );

        DB::beginTransaction();
        try {
            $status = Status::find($id);

            if (request('name') != $status->name && Status::where('name', request('name'))->exists()) {
                DB::rollback();
                return redirect()->back()->with('error', 'اسم حالة الرحلة الذي قمت بإدخاله موجود مسبقآ');
            }
            $status->name = request('name');
            $status->rate = request('rate');
            $status->update();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "status";
            $log->title = " تعديل حالة الرحلة (" . request('name') . ")";
            $log->log = " تم تعديل بيانات حالة الرحلة (" . request('name') . ")";
            $log->save();

            DB::commit();
            return redirect()->route('status.index')->with('success', 'تــم تـعـديـل بـيـانـات حـالـة الـرحـلـة بــنــجــاح');
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
            $status = Status::find($id);
            if(!$status){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }
            if($status->orders->count() > 0){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف لايمكن حذف حالة الرحلة وذلك لوجد طلبات خاصة بحالة الرحلة');
            }
                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "status";
                $log->title = " حذف حالة الرحلة (" . $status->name . ") DYL";
                $log->log = " تم حذف حالة الرحلة بتاريخ (" . now()->format('Y-m-d') . ") ";
                $log->save();
                $status->delete();
                DB::commit();
                return redirect()->route('status.index')->with('success', 'تــم حــذف حـالـة الـرحـلـة بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

}
