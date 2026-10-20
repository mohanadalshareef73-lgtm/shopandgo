<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Log;
use App\Models\Platform;
use App\Models\Treasury;
use App\Models\Notif_orgs;
use Illuminate\Http\Request;
use App\Models\PaymentExpenses;
use App\Models\TreasuryHistory;
use Illuminate\Support\Facades\DB;

class paymentExpensesController extends Controller
{
    function __construct()
    {
         // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
         $this->middleware('permission:paymentExpenses-list', ['only' => ['index','show','download']]);
         $this->middleware('permission:paymentExpenses-create', ['only' => ['create','store']]);
         $this->middleware('permission:paymentExpenses-edit', ['only' => ['edit','update']]);
         $this->middleware('permission:paymentExpenses-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $expenses= PaymentExpenses::latest()->paginate(25);
        $platforms = Platform::all();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();

        $totalDay = PaymentExpenses::where('status', true)->whereDate('date', Carbon::today())->sum('cost');
        $totalMonth = PaymentExpenses::where('status', true)->whereYear('date', Carbon::now()->year)
                              ->where('status', true)->whereMonth('date', Carbon::now()->month)
                              ->sum('cost');
        $totalYear = PaymentExpenses::where('status', true)->whereYear('date', Carbon::now()->year)->sum('cost');

        $totalDay2 = PaymentExpenses::where('status', false)->whereDate('date', Carbon::today())->sum('cost');
        $totalMonth2 = PaymentExpenses::where('status', false)->whereYear('date', Carbon::now()->year)
                              ->where('status', false)->whereMonth('date', Carbon::now()->month)
                              ->sum('cost');
        $totalYear2 = PaymentExpenses::where('status', false)->whereYear('date', Carbon::now()->year)->sum('cost');
        return view('admin.paymenExpenses.index',compact('expenses','totalDay','totalMonth','totalYear','totalDay2','totalMonth2','totalYear2','platforms','main_notifications'));
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

    public function print(Request $request)
    {
        $query = $request->all();
        if (!$request->filled('startDate') && !$request->filled('endDate') && !$request->filled('platform_id')) {
            return redirect()->route('paymentExpenses.index')->with('error', 'الرجاء تعبئة إحدى الخانات على الأقل');
        }
        $expenses = PaymentExpenses::query();
        if ($request->filled('startDate') && !$request->filled('endDate')) {
            $expenses->whereDate('date', '=', $request->input('startDate'));
        }
        elseif ($request->filled('startDate') && $request->filled('endDate')) {
            $expenses->whereBetween('date', [$request->input('startDate'), $request->input('endDate')]);
        }
        elseif (!$request->filled('startDate') && $request->filled('endDate')) {
            $expenses->whereDate('date', '=', $request->input('endDate'));
        }
        if ($request->filled('platform_id')) {
            $expenses->where('platform_id', $request->input('platform_id'));
        }
        $expenses = $expenses->latest()->get();
        if($expenses->count() == 0){
            return redirect()->route('paymentExpenses.index')->with('error', 'لا يوجد اي سجلات بالبيانات المختارة');
        }
        return view('admin.paymenExpenses.print', compact('expenses', 'query'));
    }

    public function search(Request $request)
    {
        if (!empty($request)) {
            $query = $request->all();
        }
        if (!request('from_to') && !request('platform_id') && !request('cost') && !request('date') && !request('note')) {
            return redirect()->route('customer.index')->with('error', 'الرجاء تعبئة احدي الخانات علي الاقل');
        }
        $expenses= PaymentExpenses::query(); // Start a new query
        if (request('from_to') != null) {
            $expenses->orWhere('from', request('from_to'));
            $expenses->orWhere('to', request('from_to'));
        }
        if (request('platform_id') != null) {
            $expenses->orWhere('platform_id', request('platform_id'));
        }
        if (request('cost') != null) {
            $expenses->orWhere('cost', 'like', '%' . request('cost') . '%');
        }
        if (request('date') != null) {
            $expenses->orWhere('date', 'like', '%' . request('date') . '%');
        }
        if (request('note') != null) {
            $expenses->orWhere('note', 'like', '%' . request('note') . '%');
        }
        $expenses= $expenses->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        $platforms = Platform::all();
        return view('admin.paymenExpenses.index', compact('expenses','platforms', 'query','main_notifications'));
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
            'from' => 'required|numeric',
            'to' => 'required|numeric',
            'cost' => 'required|numeric',
            'date' => 'required|date',
        ],
        [
            'from.required' => 'يجب إدخال رقم بداية الرحلة',
            'to.required' => 'يجب إدخال رقم نهاية الرحلة',
            'cost.required' => 'يجب إدخال قيمة مصروفات الدفع',
            'date.required' => 'يجب إدخال تاريخ مصروفات الدفع',
        ]);

        DB::beginTransaction();
        try {

            if(!request('platform_id') && !request('name')){
                DB::rollback();
                return redirect()->back()->with('error', 'الرجاء تحديد نوع المنصة من القائمة او كتابة نوع المنصة جديد');
            }elseif(request('name')){
                if(Platform::where('name')->exists()){
                    DB::rollback();
                    return redirect()->back()->with('error', 'اسم نوع المنصة مصروفات الدفع الذي قمت بإدخاله موجود قم بتحديده من القائمة');
                }
                $platform = new Platform;
                $platform->name = request('name');
                $platform->save();
            }elseif(request('platform_id')){
                $platform = Platform::find(request('platform_id'));
                if(!$platform){
                    DB::rollback();
                    return redirect()->back()->with('error', 'الرجاء تحديد نوع المنصة من القائمة او كتابة نوع المنصة جديد');
                }
            }
            $purchase = new PaymentExpenses;
            $purchase->platform_id = $platform->id;
            $purchase->from = request('from');
            $purchase->to = request('to');
            $purchase->cost = request('cost');
            $purchase->date = request('date');
            $purchase->note = request('note');
            if(request('status')){
                $purchase->status = true;
            }else{
                $purchase->status = false;
            }
            $purchase->created_id = auth()->user()->id;

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "paymentExpenses";
            $log->title = " مصروفات الدفع جديدة (".$platform->name.")";
            $log->log = " تم اضافة مصروفات الدفع جديدة بقيمة (".request('cost').")";
            $log->save();

            $purchase->save();

            DB::commit();
            return redirect()->back()->with('success','تــمــت إضــافــة مـصـروفـات الـدفـع بــنــجــاح');
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
        DB::beginTransaction();
        try {
            $expense = PaymentExpenses::find($id);
            $expense->status = true;
            $expense->update();
            DB::commit();
            return redirect()->back()->with('success','تــم تـفـعـيـل مـصـروفـات الـدفـع بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
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
        $expense = PaymentExpenses::find($id);
        $platforms = Platform::all();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.paymenExpenses.edit',compact('expense','platforms','main_notifications'));
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
                'from' => 'required|numeric',
                'to' => 'required|numeric',
                'date' => 'required|date',
            ],
            [
                'from.required' => 'يجب إدخال رقم بداية الرحلة',
                'to.required' => 'يجب إدخال رقم نهاية الرحلة',
                'date.required' => 'يجب إدخال تاريخ مصروفات الدفع',
            ]);

            DB::beginTransaction();
            try {
                if(!request('platform_id') && !request('name')){
                    DB::rollback();
                    return redirect()->back()->with('error', 'الرجاء تحديد نوع المنصة من القائمة او كتابة نوع المنصة جديد');
                }elseif(request('name')){
                    if(Platform::where('name')->exists()){
                        DB::rollback();
                        return redirect()->back()->with('error', 'اسم نوع المنصة الذي قمت بإدخاله موجود قم بتحديده من القائمة');
                    }
                    $platform = new Platform;
                    $platform->name = request('name');
                    $platform->save();
                }elseif(request('platform_id')){
                    $platform = Platform::find(request('platform_id'));
                    if(!$platform){
                        DB::rollback();
                        return redirect()->back()->with('error', 'الرجاء تحديد نوع المنصة من القائمة او كتابة نوع المنصة جديد');
                    }
                }
                $purchase = PaymentExpenses::find($id);
                $purchase->platform_id = $platform->id;
                $purchase->from = request('from');
                $purchase->to = request('to');
                $purchase->date = request('date');
                $purchase->note = request('note');
                if(request('status')){
                    $purchase->status = true;
                }else{
                    $purchase->status = false;
                }
                $purchase->update();

                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "paymentExpenses";
                $log->title = " تعديل مصروفات الدفع (".$platform->name.")";
                $log->log = " تم تعديل بيانات مصروفات الدفع (".request('cost').")";
                $log->save();
            DB::commit();
            return redirect()->route('paymentExpenses.index')->with('success','تــم تـعـديـل بـيـانـات المـصـروفـات الـدفـع بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }


    public function pay(Request $request, $id)
    {
            DB::beginTransaction();
            try {
                $purchase = PaymentExpenses::find($id);
                $purchase->status = true;
                $purchase->update();

                if($purchase->cost){
                    $treasury = Treasury::first();
                    $history = new TreasuryHistory;
                    $history->price= $purchase->cost;
                    $history->price_before = $treasury->total;
                    $history->price_after = $treasury->total - $purchase->cost;
                    $history->status = "pull";
                    $history->type = "paymentExpenses";
                    $history->procedure_id = $purchase->id;
                    $history->created_id = auth()->user()->id;
                    $history->save();
                    $treasury->total -= $purchase->cost;
                    $treasury->update();
                }

                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "paymentExpenses";
                $log->title = " تم دفع المصروفات الدفع (".$purchase->platform->name.")";
                $log->log = " تم دفع مصروفات الدفع (".$purchase->cost.")";
                $log->save();
            DB::commit();
            return redirect()->back()->with('success','تــم دفـــع المـصـروفـات الـدفـع بــنــجــاح');
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
            $purchase = PaymentExpenses::find($id);
            if(!$purchase){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }


                                $onlyPaid = $purchase->cost;
                    $treasury = Treasury::first();
                    $history = new TreasuryHistory;
                    $history->price= $onlyPaid;
                    $history->price_before = $treasury->total;
                    $history->price_after = $treasury->total - $onlyPaid;
                    $history->status = "pull";
                    $history->type = "paymentExpenses";
                    $history->procedure_id = $purchase->id;
                    $history->created_id = auth()->user()->id;
                    $history->is_update = true;
                    $history->save();
                    $treasury->total -= $onlyPaid;
                    $treasury->update();


                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "paymentExpenses";
                $log->title = " حذف مصروفات الدفع (" . $purchase->desc->name . ")";
                $log->log = " تم حذف مصروفات الدفع بتاريخ (" . now()->format('Y-m-d') . ") بقيمة ".$purchase->cost." ";
                $log->save();
                $purchase->delete();
                DB::commit();
                return redirect()->route('paymenExpenses.index')->with('success', 'تــم حــذف المـصـروفـات الـدفـع بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }
}
