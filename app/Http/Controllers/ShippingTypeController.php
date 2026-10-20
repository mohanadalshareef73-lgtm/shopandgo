<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Notif_orgs;
use App\Models\ShippingCharges;
use App\Models\ShippingType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShippingTypeController extends Controller
{
    function __construct()
    {
        // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
        $this->middleware('permission:shipping_type-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:shipping_type-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:shipping_type-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:shipping_type-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $types = ShippingType::paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.shippingType.index', compact('types','main_notifications'));
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
            return redirect()->route('shippingType.index')->with('error', 'الرجاء تعبئة احدي الخانات علي الاقل');
        }
        $types = ShippingType::query(); // Start a new query
        if (request('name') != null) {
            $types->orWhere('name', 'like', '%' . request('name') . '%');
        }
        $types = $types->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.shippingType.index', compact('types', 'query','main_notifications'));
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
                'name' => 'required|string|unique:shipping_types',
            ],
            [
                'name.required' => 'يجب ادخال اسم توع الشحن',
                'name.unique' => 'اسم توع الشحن الذي قمت بإدخاله موجود',
            ]
        );

        DB::beginTransaction();
        try {
            $type = new ShippingType;
            $type->name = request('name');
            $type->created_id = auth()->user()->id;
            $type->save();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "shippingType";
            $log->title = " توع الشحن جديدة (" . request('name') . ")";
            $log->log = " تم اضافة توع الشحن جديدة (" . request('name') . ")";
            $log->save();

            DB::commit();
            return redirect()->back()->with('success', 'تــمــت إضــافــة نـوع الـشـحـن بــنــجــاح');
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
        $type = ShippingType::find($id);
        $charges = ShippingCharges::where('type_id', $id)->paginate(25);
            $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
        return view('admin.shippingType.show', compact('charges', 'type','main_notifications'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $type = ShippingType::find($id);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.shippingType.edit', compact('type','main_notifications'));
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
                'name.required' => 'يجب إدخال اسم التوع الشحن',
            ]
        );

        DB::beginTransaction();
        try {
            $type = ShippingType::find($id);

            if (request('name') != $type->name && ShippingType::where('name', request('name'))->exists()) {
                DB::rollback();
                return redirect()->back()->with('error', 'اسم التوع الشحن الذي قمت بإدخاله موجود مسبقآ');
            }
            $type->name = request('name');
            $type->update();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "shippingType";
            $log->title = " تعديل التوع الشحن (" . request('name') . ")";
            $log->log = " تم تعديل بيانات التوع الشحن (" . request('name') . ")";
            $log->save();

            DB::commit();
            return redirect()->route('shippingType.index')->with('success', 'تــم تـعـديـل بـيـانـات نـوع الـشـحـن بــنــجــاح');
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
            $type = ShippingType::find($id);
            if(!$type){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }
            if($type->shippingCharges->count() > 0){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف لايمكن حذف التوع الشحن وذلك لوجد مصروفات خاصة بالوصف');
            }
                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "shippingType";
                $log->title = " حذف التوع الشحن (" . $type->name . ") DYL";
                $log->log = " تم حذف التوع الشحن بتاريخ (" . now()->format('Y-m-d') . ") ";
                $log->save();
                $type->delete();
                DB::commit();
                return redirect()->route('shippingType.index')->with('success', 'تــم حــذف نـوع الـشـحـن بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }
}
