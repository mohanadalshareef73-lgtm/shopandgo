<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Notif_orgs;
use App\Models\PaymentMethod;
use App\Models\ShippingCharges;
use App\Models\ShippingType;
use App\Models\Treasury;
use App\Models\TreasuryDollar;
use App\Models\TreasuryEuro;
use App\Models\TreasuryHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShippingChargesController extends Controller
{
    function __construct()
    {
         // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
         $this->middleware('permission:shippingCharge-list', ['only' => ['index','show','download']]);
         $this->middleware('permission:shippingCharge-create', ['only' => ['create','store']]);
         $this->middleware('permission:shippingCharge-edit', ['only' => ['edit','update']]);
         $this->middleware('permission:shippingCharge-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $charges = ShippingCharges::latest()->paginate(25);
        $types = ShippingType::all();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        $totalDay = ShippingCharges::whereDate('date', Carbon::today())->sum('cost');
        $totalMonth = ShippingCharges::whereYear('date', Carbon::now()->year)
                              ->whereMonth('date', Carbon::now()->month)
                              ->sum('cost');
        $totalYear = ShippingCharges::whereYear('date', Carbon::now()->year)->sum('cost');
         $payments = PaymentMethod::all();

                $totalDay = ShippingCharges::whereDate('created_at', Carbon::today())
        ->where('currency', 'dinar')->sum('cost');
        $totalDayDollar = ShippingCharges::whereDate('created_at', Carbon::today())
        ->where('currency', 'dollar')->sum('cost');
        $totalDayEuro = ShippingCharges::whereDate('created_at', Carbon::today())
        ->where('currency', 'euro')->sum('cost');

        $totalMonth = ShippingCharges::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dinar')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');
        $totalMonthDollar = ShippingCharges::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dollar')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');
        $totalMonthEuro = ShippingCharges::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'euro')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');

        $totalYear = ShippingCharges::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'dinar')->sum('cost');
        $totalYearDollar = ShippingCharges::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'dollar')->sum('cost');
        $totalYearEuro = ShippingCharges::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'euro')->sum('cost');


        return view('admin.shippingCharge.index',compact('totalDay','totalDayDollar','totalDayEuro', 'totalMonth','totalMonthDollar','totalMonthEuro', 'totalYear','totalYearDollar','totalYearEuro','charges','payments','totalDay','totalMonth','totalYear','types','main_notifications'));
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
        if (!$request->filled('startDate') && !$request->filled('endDate') && !$request->filled('type_id')) {
            return redirect()->route('shippingCharges.index')->with('error', 'الرجاء تعبئة إحدى الخانات على الأقل');
        }
        $charges = ShippingCharges::query();
        if ($request->filled('startDate') && !$request->filled('endDate')) {
            $charges->whereDate('date', '=', $request->input('startDate'));
        }
        elseif ($request->filled('startDate') && $request->filled('endDate')) {
            $charges->whereBetween('date', [$request->input('startDate'), $request->input('endDate')]);
        }
        elseif (!$request->filled('startDate') && $request->filled('endDate')) {
            $charges->whereDate('date', '=', $request->input('endDate'));
        }
        if ($request->filled('type_id')) {
            $charges->where('type_id', $request->input('type_id'));
        }
        $charges = $charges->latest()->get();
        if($charges->count() == 0){
            return redirect()->route('shippingCharges.index')->with('error', 'لا يوجد اي سجلات بالبيانات المختارة');
        }
                 $payments = PaymentMethod::all();




        return view('admin.shippingCharge.print', compact('charges', 'query','payments'));
    }

    public function search(Request $request)
    {
        if (!empty($request)) {
            $query = $request->all();
        }
        if (!request('type_id') && !request('weight') && !request('cost') && !request('date') && !request('note')) {
            return redirect()->route('customer.index')->with('error', 'الرجاء تعبئة احدي الخانات علي الاقل');
        }

        $charges = ShippingCharges::query(); // Start a new query
        if (request('type_id') != null) {
            $charges->orWhere('type_id', request('type_id'));
        }
        if (request('cost') != null) {
            $charges->orWhere('cost', 'like', '%' . request('cost') . '%');
        }
        if (request('weight') != null) {
            $charges->orWhere('weight', 'like', '%' . request('weight') . '%');
        }
        if (request('date') != null) {
            $charges->orWhere('date', 'like', '%' . request('date') . '%');
        }
        if (request('note') != null) {
            $charges->orWhere('note', 'like', '%' . request('note') . '%');
        }

        $charges = $charges->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        $types = ShippingType::all();
                 $payments = PaymentMethod::all();

                $totalDay = ShippingCharges::whereDate('created_at', Carbon::today())
        ->where('currency', 'dinar')->sum('cost');
        $totalDayDollar = ShippingCharges::whereDate('created_at', Carbon::today())
        ->where('currency', 'dollar')->sum('cost');
        $totalDayEuro = ShippingCharges::whereDate('created_at', Carbon::today())
        ->where('currency', 'euro')->sum('cost');

        $totalMonth = ShippingCharges::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dinar')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');
        $totalMonthDollar = ShippingCharges::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dollar')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');
        $totalMonthEuro = ShippingCharges::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'euro')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');

        $totalYear = ShippingCharges::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'dinar')->sum('cost');
        $totalYearDollar = ShippingCharges::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'dollar')->sum('cost');
        $totalYearEuro = ShippingCharges::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'euro')->sum('cost');
        return view('admin.shippingCharge.index', compact('totalDay','totalDayDollar','totalDayEuro', 'totalMonth','totalMonthDollar','totalMonthEuro', 'totalYear','totalYearDollar','totalYearEuro','charges', 'types', 'query','main_notifications','payments'));
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
            'weight' => 'required|numeric',
            'cost' => 'required|numeric',
            'date' => 'required|date',
            'payment_id' => 'required|exists:payment_methods,id',
            'currency' => 'required|in:dinar,dollar,euro',
        ],
        [
            'weight.required' => 'يجب إدخال وزن الشحنة',
            'cost.required' => 'يجب إدخال قيمة المصروفات الشحن',
            'date.required' => 'يجب إدخال تاريخ المصروفات الشحن',
            'payment_id.required' => 'يجب إدخال طريقة الدفع',
            'currency.required' => 'يجب إدخال العملة',
            'payment_id.exists' => 'طريقة الدفع المختارة غير موجودة',
            'currency.in' => 'العملة المختارة غير صحيحة',
        ]);

        DB::beginTransaction();
        try {
            if(!request('type_id') && !request('name')){
                DB::rollback();
                return redirect()->back()->with('error', 'الرجاء تحديد النوع الشحن من القائمة او كتابة نوع الشحن جديد');
            }elseif(request('name')){
                if(ShippingType::where('name')->exists()){
                    DB::rollback();
                    return redirect()->back()->with('error', 'اسم نوع الشحن الذي قمت بإدخاله موجود قم بتحديده من القائمة');
                }
                $type = new ShippingType;
                $type->name = request('name');
                $type->created_id = auth()->user()->id;
                $type->save();
            }elseif(request('type_id')){
                $type = ShippingType::find(request('type_id'));
                if(!$type){
                    DB::rollback();
                    return redirect()->back()->with('error', 'الرجاء تحديد النوع الشحن من القائمة او كتابة نوع الشحن جديد');
                }
            }
            $charge = new ShippingCharges;
            $charge->type_id = $type->id;
            $charge->cost = request('cost');
            $charge->weight = request('weight');
            $charge->parcels_count = request('parcels_count');
            $charge->company = request('company');
            $charge->trip_id = request('trip_id') ?: null;
            $charge->date = request('date');
            $charge->note = request('note');
            $charge->payment_id = request('payment_id');

            if (request('currency') == "dinar") {
                $charge->currency = request('currency');
                $charge->currency_sign = "د.ل";
            } elseif (request('currency') == "dollar") {
                $charge->currency = request('currency');
                $charge->currency_sign = "$";
            } elseif (request('currency') == "euro") {
                $charge->currency = request('currency');
                $charge->currency_sign = "€";
            } else {
                $charge->currency = request('currency');
                $charge->currency_sign = "د.ل";
            }


            $charge->created_id = auth()->user()->id;

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "shippingCharge";
            $log->title = " مصروفات الشحن جديدة (".$type->name.")";
            $log->log = " تم اضافة مصروفات الشحن جديدة بقيمة (".request('cost').")";
            $log->save();

            $charge->save();

            if(request('cost')){
                $cost = request('cost');
$paymentId = $charge->payment_id;

// get treasury row by currency + payment_id
if ($charge->currency == "dinar") {
    $treasuryRow = Treasury::where('payment_id', $paymentId)->first();
} elseif ($charge->currency == "dollar") {
    $treasuryRow = TreasuryDollar::where('payment_id', $paymentId)->first();
} else {
    $treasuryRow = TreasuryEuro::where('payment_id', $paymentId)->first();
}

// check treasury exists
if (!$treasuryRow) {
    return redirect()->back()->with('error', 'لا يوجد خزينة لهذه الطريقة');
}

$before = $treasuryRow->total;
$after  = $before - $cost;

// save history
$history = new TreasuryHistory;
$history->price = $cost;
$history->price_before = $before;
$history->price_after = $after;
$history->status = "pull";
$history->type = "shippingCharge";
$history->procedure_id = $charge->id;
$history->created_id = auth()->user()->id;
$history->currency = $charge->currency;
$history->currency_sign = $charge->currency_sign;
$history->payment_id = $paymentId;
$history->save();

// update treasury
$treasuryRow->total = $after;
$treasuryRow->save();
            }


            DB::commit();
            return redirect()->back()->with('success','تــمــت إضــافــة مصروفـات الشحـن بــنــجــاح');
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
        $charge = ShippingCharges::find($id);
        $types = ShippingType::all();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
                 $payments = PaymentMethod::all();

        return view('admin.shippingCharge.edit',compact('charge','types','main_notifications','payments'));
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
                'weight' => 'required|numeric',
                'date' => 'required|date',
            'payment_id' => 'required|exists:payment_methods,id',
            'currency' => 'required|in:dinar,dollar,euro',            ],
            [
                'weight.required' => 'يجب إدخال وزن الشحنة',
                'date.required' => 'يجب إدخال تاريخ المصروفات الشحن',
            'payment_id.required' => 'يجب إدخال طريقة الدفع',
            'currency.required' => 'يجب إدخال العملة',
            'payment_id.exists' => 'طريقة الدفع المختارة غير موجودة',
            'currency.in' => 'العملة المختارة غير صحيحة',            ]);

            DB::beginTransaction();
            try {
                if(!request('type_id') && !request('name')){
                    DB::rollback();
                    return redirect()->back()->with('error', 'الرجاء تحديد النوع الشحن من القائمة او كتابة نوع الشحن جديد');
                }elseif(request('name')){
                    if(ShippingType::where('name')->exists()){
                        DB::rollback();
                        return redirect()->back()->with('error', 'اسم نوع الشحن الذي قمت بإدخاله موجود قم بتحديده من القائمة');
                    }
                    $type = new ShippingType;
                    $type->name = request('name');
                    $type->created_id = auth()->user()->id;
                    $type->save();
                }elseif(request('type_id')){
                    $type = ShippingType::find(request('type_id'));
                    if(!$type){
                        DB::rollback();
                        return redirect()->back()->with('error', 'الرجاء تحديد النوع الشحن من القائمة او كتابة نوع الشحن جديد');
                    }
                }
                $charge = ShippingCharges::find($id);
                $charge->type_id = $type->id;

                $charge->date = request('date');
                $charge->note = request('note');
                $charge->weight = request('weight');
            $charge->parcels_count = request('parcels_count');
            $charge->company = request('company');
            $charge->trip_id = request('trip_id') ?: null;

                $charge->update();

                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "shippingCharge";
                $log->title = " تعديل المصروفات الشحن (".$type->name.")";
                $log->log = " تم تعديل بيانات المصروفات الشحن (".request('cost').")";
                $log->save();
            DB::commit();
            return redirect()->route('shippingCharges.index')->with('success','تــم تـعـديـل بـيـانـات مـصـروفـات الـشـحـن بــنــجــاح');
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
            $charge = ShippingCharges::find($id);
            if(!$charge){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }
$onlyPaid = $charge->cost;
$paymentId = $charge->payment_id;

// get treasury row by currency + payment_id
if ($charge->currency == "dinar") {
    $treasuryRow = Treasury::where('payment_id', $paymentId)->first();
} elseif ($charge->currency == "dollar") {
    $treasuryRow = TreasuryDollar::where('payment_id', $paymentId)->first();
} else {
    $treasuryRow = TreasuryEuro::where('payment_id', $paymentId)->first();
}

// check treasury exists
if (!$treasuryRow) {
    return redirect()->back()->with('error', 'لا يوجد خزينة لهذه الطريقة');
}

$before = $treasuryRow->total;
$after  = $before - $onlyPaid;

// save history
$history = new TreasuryHistory;
$history->price = $onlyPaid;
$history->price_before = $before;
$history->price_after = $after;
$history->status = "pull";
$history->type = "shippingCharge";
$history->procedure_id = $charge->id;
$history->created_id = auth()->user()->id;
$history->is_update = true;
$history->currency = $charge->currency;
$history->currency_sign = $charge->currency_sign;
$history->payment_id = $paymentId;
$history->save();

// update treasury
$treasuryRow->total = $after;
$treasuryRow->save();

                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "shippingCharge";
                $log->title = " حذف المصروفات الشحن (" . $charge->type->name . ")";
                $log->log = " تم حذف المصروفات الشحن بتاريخ (" . now()->format('Y-m-d') . ") بقيمة ".$charge->cost." ";
                $log->save();
                $charge->delete();
                DB::commit();
                return redirect()->route('shippingCharge.index')->with('success', 'تــم حــذف مـصـروفـات الـشـحـن بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }
}
