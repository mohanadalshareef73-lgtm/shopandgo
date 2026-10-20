<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Order;
use App\Models\Notif_orgs;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseDescription;

class PurchaseDescriptionController extends Controller
{
    function __construct()
    {
        // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
        $this->middleware('permission:purchase_description-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:purchase_description-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:purchase_description-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:purchase_description-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $descriptions = PurchaseDescription::paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.purchaseDesc.index', compact('descriptions','main_notifications'));
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
            return redirect()->route('purchaseDesc.index')->with('error', 'الرجاء تعبئة احدي الخانات علي الاقل');
        }
        $descriptions = PurchaseDescription::query(); // Start a new query
        if (request('name') != null) {
            $descriptions->orWhere('name', 'like', '%' . request('name') . '%');
        }
        $descriptions = $descriptions->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.purchaseDesc.index', compact('descriptions', 'query','main_notifications'));
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
                'name' => 'required|string|unique:purchase_descriptions',
            ],
            [
                'name.required' => 'يجب ادخال اسم وصف للمشتريات',
                'name.unique' => 'اسم وصف المشتريات الذي قمت بإدخاله موجود',
            ]
        );

        DB::beginTransaction();
        try {
            $description = new PurchaseDescription;
            $description->name = request('name');
            $description->created_id = auth()->user()->id;
            $description->save();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "description";
            $log->title = " وصف للمشتريات جديدة (" . request('name') . ")";
            $log->log = " تم اضافة وصف للمشتريات جديدة (" . request('name') . ")";
            $log->save();

            DB::commit();
            return redirect()->back()->with('success', 'تــمــت إضــافــة وصــف للـمـشـتـريـات بــنــجــاح');
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
        $description = PurchaseDescription::find($id);
        $purchases = Purchase::where('desc_id', $id)->paginate(25);
            $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
        return view('admin.purchaseDesc.show', compact('purchases', 'description','main_notifications'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $description = PurchaseDescription::find($id);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.purchaseDesc.edit', compact('description','main_notifications'));
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
                'name.required' => 'يجب إدخال اسم الوصف للمشتريات',
            ]
        );

        DB::beginTransaction();
        try {
            $description = PurchaseDescription::find($id);

            if (request('name') != $description->name && PurchaseDescription::where('name', request('name'))->exists()) {
                DB::rollback();
                return redirect()->back()->with('error', 'اسم الوصف للمشتريات الذي قمت بإدخاله موجود مسبقآ');
            }
            $description->name = request('name');
            $description->update();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "description";
            $log->title = " تعديل الوصف للمشتريات (" . request('name') . ")";
            $log->log = " تم تعديل بيانات الوصف للمشتريات (" . request('name') . ")";
            $log->save();

            DB::commit();
            return redirect()->route('purchaseDesc.index')->with('success', 'تــم تـعـديـل بـيـانـات وصــف للـمـشـتريات بــنــجــاح');
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
            $description = PurchaseDescription::find($id);
            if(!$description){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }
            if($description->purchases->count() > 0){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف لايمكن حذف الوصف للمشتريات وذلك لوجد مشتريات خاصة بالوصف');
            }
                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "description";
                $log->title = " حذف الوصف للمشتريات (" . $description->name . ") DYL";
                $log->log = " تم حذف الوصف للمشتريات بتاريخ (" . now()->format('Y-m-d') . ") ";
                $log->save();
                $description->delete();
                DB::commit();
                return redirect()->route('purchaseDesc.index')->with('success', 'تــم حــذف وصــف الـمـشـتـريـات بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }
}
