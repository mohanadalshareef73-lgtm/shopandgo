<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Notif_orgs;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchaseDescription;
use App\Models\ShippingCharges;
use App\Models\Treasury;
use App\Models\TreasuryDollar;
use App\Models\TreasuryEuro;
use App\Models\TreasuryHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function Symfony\Component\VarDumper\Dumper\esc;

class PurchaseController extends Controller
{
    function __construct()
    {
         // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
         $this->middleware('permission:purchase-list', ['only' => ['index','show','download']]);
         $this->middleware('permission:purchase-create', ['only' => ['create','store']]);
         $this->middleware('permission:purchase-edit', ['only' => ['edit','update']]);
         $this->middleware('permission:purchase-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $purchases = Purchase::latest()->paginate(25);
        $descriptions = PurchaseDescription::all();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        $totalDay = Purchase::whereDate('date', Carbon::today())->sum('cost');
        $totalMonth = Purchase::whereYear('date', Carbon::now()->year)
                              ->whereMonth('date', Carbon::now()->month)
                              ->sum('cost');
        $totalYear = Purchase::whereYear('date', Carbon::now()->year)->sum('cost');
                 $payments = PaymentMethod::all();


                $totalDay = Purchase::whereDate('created_at', Carbon::today())
        ->where('currency', 'dinar')->sum('cost');
        $totalDayDollar = Purchase::whereDate('created_at', Carbon::today())
        ->where('currency', 'dollar')->sum('cost');
        $totalDayEuro = Purchase::whereDate('created_at', Carbon::today())
        ->where('currency', 'euro')->sum('cost');

        $totalMonth = Purchase::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dinar')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');
        $totalMonthDollar = Purchase::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dollar')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');
        $totalMonthEuro = Purchase::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'euro')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');

        $totalYear = Purchase::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'dinar')->sum('cost');
        $totalYearDollar = Purchase::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'dollar')->sum('cost');
        $totalYearEuro = Purchase::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'euro')->sum('cost');

        return view('admin.purchase.index',compact('totalDay','totalDayDollar','totalDayEuro', 'totalMonth','totalMonthDollar','totalMonthEuro', 'totalYear','totalYearDollar','totalYearEuro','purchases','payments','totalDay','totalMonth','totalYear','descriptions','main_notifications'));
    }

    public function print(Request $request)
    {
        $query = $request->all();
        if (!$request->filled('startDate') && !$request->filled('endDate') && !$request->filled('desc_id')) {
            return redirect()->route('purchase.index')->with('error', 'الرجاء تعبئة إحدى الخانات على الأقل');
        }
        $purchases = Purchase::query();
        if ($request->filled('startDate') && !$request->filled('endDate')) {
            $purchases->whereDate('date', '=', $request->input('startDate'));
        }
        elseif ($request->filled('startDate') && $request->filled('endDate')) {
            $purchases->whereBetween('date', [$request->input('startDate'), $request->input('endDate')]);
        }
        elseif (!$request->filled('startDate') && $request->filled('endDate')) {
            $purchases->whereDate('date', '=', $request->input('endDate'));
        }
        if ($request->filled('desc_id')) {
            $purchases->where('desc_id', $request->input('desc_id'));
        }
        $purchases = $purchases->latest()->get();
        if($purchases->count() == 0){
            return redirect()->route('purchase.index')->with('error', 'لا يوجد اي سجلات بالبيانات المختارة');
        }
                 $payments = PaymentMethod::all();

        return view('admin.purchase.print', compact('purchases', 'query','payments'));
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
        if (!request('desc_id') && !request('cost') && !request('date') && !request('note')) {
            return redirect()->route('customer.index')->with('error', 'الرجاء تعبئة احدي الخانات علي الاقل');
        }

        $purchases = Purchase::query(); // Start a new query
        if (request('desc_id') != null) {
            $purchases->orWhere('desc_id', request('desc_id'));
        }
        if (request('cost') != null) {
            $purchases->orWhere('cost', 'like', '%' . request('cost') . '%');
        }
        if (request('date') != null) {
            $purchases->orWhere('date', 'like', '%' . request('date') . '%');
        }
        if (request('note') != null) {
            $purchases->orWhere('note', 'like', '%' . request('note') . '%');
        }
        $descriptions = PurchaseDescription::all();
        $purchases = $purchases->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
                 $payments = PaymentMethod::all();

                $totalDay = Purchase::whereDate('created_at', Carbon::today())
        ->where('currency', 'dinar')->sum('cost');
        $totalDayDollar = Purchase::whereDate('created_at', Carbon::today())
        ->where('currency', 'dollar')->sum('cost');
        $totalDayEuro = Purchase::whereDate('created_at', Carbon::today())
        ->where('currency', 'euro')->sum('cost');

        $totalMonth = Purchase::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dinar')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');
        $totalMonthDollar = Purchase::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dollar')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');
        $totalMonthEuro = Purchase::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'euro')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');

        $totalYear = Purchase::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'dinar')->sum('cost');
        $totalYearDollar = Purchase::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'dollar')->sum('cost');
        $totalYearEuro = Purchase::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'euro')->sum('cost');
        return view('admin.purchase.index', compact('totalDay','totalDayDollar','totalDayEuro', 'totalMonth','totalMonthDollar','totalMonthEuro', 'totalYear','totalYearDollar','totalYearEuro','purchases', 'descriptions', 'query','main_notifications','payments'));
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
            'cost' => 'required|numeric',
            'date' => 'required|date',
            'payment_id' => 'required|exists:payment_methods,id',
            'currency' => 'required|in:dinar,dollar,euro',        ],
        [
            'cost.required' => 'يجب إدخال قيمة المشتريات',
            'date.required' => 'يجب إدخال تاريخ المشتريات',
            'payment_id.required' => 'يجب إدخال طريقة الدفع',
            'currency.required' => 'يجب إدخال العملة',
            'payment_id.exists' => 'طريقة الدفع المختارة غير موجودة',
            'currency.in' => 'العملة المختارة غير صحيحة',        ]);

        DB::beginTransaction();
        try {

            if(!request('desc_id') && !request('name')){
                DB::rollback();
                return redirect()->back()->with('error', 'الرجاء تحديد الوصف من القائمة او كتابة وصف جديد');
            }elseif(request('name')){
                if(PurchaseDescription::where('name')->exists()){
                    DB::rollback();
                    return redirect()->back()->with('error', 'اسم وصف المشتريات الذي قمت بإدخاله موجود قم بتحديده من القائمة');
                }
                $description = new PurchaseDescription;
                $description->name = request('name');
                $description->created_id = auth()->user()->id;
                $description->save();
            }elseif(request('desc_id')){
                $description = PurchaseDescription::find(request('desc_id'));
                if(!$description){
                    DB::rollback();
                    return redirect()->back()->with('error', 'الرجاء تحديد الوصف من القائمة او كتابة وصف جديد');
                }
            }
            $purchase = new Purchase;
            $purchase->desc_id = $description->id;
            $purchase->cost = request('cost');
            $purchase->date = request('date');
            $purchase->note = request('note');
            $purchase->payment_id = request('payment_id');

            if (request('currency') == "dinar") {
                $purchase->currency = request('currency');
                $currency_sign = "د.ل";
            } elseif (request('currency') == "dollar") {
                $purchase->currency = request('currency');
                $currency_sign = "$";
            } elseif (request('currency') == "euro") {
                $purchase->currency = request('currency');
                $currency_sign = "€";
            } else {
                $purchase->currency = request('currency');
                $currency_sign = "د.ل";
            }
                $purchase->currency_sign = $currency_sign;

            $purchase->created_id = auth()->user()->id;

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "purchase";
            $log->title = " مشتريات جديدة (".$description->name.")";
            $log->log = " تم اضافة مشتريات جديدة بقيمة (".request('cost').")";
            $log->save();

            $purchase->save();

            if(request('cost')){
$cost = request('cost');
$paymentId = request('payment_id');

// get treasury row by currency + payment_id
if ($purchase->currency == "dinar") {
    $treasuryRow = Treasury::where('payment_id', $paymentId)->first();
} elseif ($purchase->currency == "dollar") {
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

// save treasury history
$history = new TreasuryHistory;
$history->price = $cost;
$history->price_before = $before;
$history->price_after = $after;
$history->status = "pull";
$history->type = "purchase";
$history->procedure_id = $purchase->id;
$history->created_id = auth()->user()->id;
$history->currency = request('currency');
$history->currency_sign = $currency_sign;
$history->payment_id = $paymentId;
$history->save();

// update treasury total
$treasuryRow->total = $after;
$treasuryRow->save();

            }

            DB::commit();
            return redirect()->back()->with('success','تــمــت إضــافــة مـشـتـريـات بــنــجــاح');
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
        $purchase = Purchase::find($id);
        $descriptions = PurchaseDescription::all();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
                 $payments = PaymentMethod::all();
        return view('admin.purchase.edit',compact('purchase','descriptions','main_notifications','payments'));
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
                'date' => 'required|date',
            'payment_id' => 'required|exists:payment_methods,id',
            'currency' => 'required|in:dinar,dollar,euro',            ],
            [
                'date.required' => 'يجب إدخال تاريخ المشتريات',
            'payment_id.required' => 'يجب إدخال طريقة الدفع',
            'currency.required' => 'يجب إدخال العملة',
            'payment_id.exists' => 'طريقة الدفع المختارة غير موجودة',
            'currency.in' => 'العملة المختارة غير صحيحة',
            ]);

            DB::beginTransaction();
            try {
                if(!request('desc_id') && !request('name')){
                    DB::rollback();
                    return redirect()->back()->with('error', 'الرجاء تحديد الوصف من القائمة او كتابة وصف جديد');
                }elseif(request('name')){
                    if(PurchaseDescription::where('name')->exists()){
                        DB::rollback();
                        return redirect()->back()->with('error', 'اسم وصف المشتريات الذي قمت بإدخاله موجود قم بتحديده من القائمة');
                    }
                    $description = new PurchaseDescription;
                    $description->name = request('name');
                    $description->created_id = auth()->user()->id;
                    $description->save();
                }elseif(request('desc_id')){
                    $description = PurchaseDescription::find(request('desc_id'));
                    if(!$description){
                        DB::rollback();
                        return redirect()->back()->with('error', 'الرجاء تحديد الوصف من القائمة او كتابة وصف جديد');
                    }
                }
                $purchase = Purchase::find($id);
                $purchase->desc_id = $description->id;

                $purchase->date = request('date');
                $purchase->note = request('note');

                $purchase->update();

                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "purchase";
                $log->title = " تعديل المشتريات (".$description->name.")";
                $log->log = " تم تعديل بيانات المشتريات (".request('cost').")";
                $log->save();
            DB::commit();
            return redirect()->route('purchase.index')->with('success','تــم تـعـديـل بـيـانـات المـشـتـريـات بــنــجــاح');
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
            $purchase = Purchase::find($id);
            if(!$purchase){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }
$onlyPaid = $purchase->cost;
$paymentId = $purchase->payment_id;

// get treasury row by currency + payment_id
if ($purchase->currency == "dinar") {
    $treasuryRow = Treasury::where('payment_id', $paymentId)->first();
} elseif ($purchase->currency == "dollar") {
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

// save treasury history
$history = new TreasuryHistory;
$history->price = $onlyPaid;
$history->price_before = $before;
$history->price_after = $after;
$history->status = "pull";
$history->type = "purchase";
$history->procedure_id = $purchase->id;
$history->created_id = auth()->user()->id;
$history->is_update = true;
$history->currency = $purchase->currency;
$history->currency_sign = $purchase->currency_sign;
$history->payment_id = $paymentId;
$history->save();

// update treasury
$treasuryRow->total = $after;
$treasuryRow->save();


                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "purchase";
                $log->title = " حذف المشتريات (" . $purchase->desc->name . ")";
                $log->log = " تم حذف المشتريات بتاريخ (" . now()->format('Y-m-d') . ") بقيمة ".$purchase->cost." ";
                $log->save();
                $purchase->delete();
                DB::commit();
                return redirect()->route('purchase.index')->with('success', 'تــم حــذف المـشـتـريـات بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }



public function report(Request $request)
{
    $query = $request->all();
    $fromDate = $request->input('fromDate');
    $toDate   = $request->input('toDate');
    $type     = $request->input('type');

    $main_notifications = Notif_orgs::where('user_id', auth()->id())
        ->latest()
        ->take(5)
        ->get();

    // ❌ Require at least fromDate
    if (!$fromDate) {
        return view('purchase.report', compact('main_notifications', 'query'))
            ->with('error', 'يرجى تحديد التاريخ');
    }

    $paymentMethods = PaymentMethod::all();

    // ================================
    // 📅 Handle single date or range
    // ================================
    $purchases = collect();
    $charges   = collect();

    if ($fromDate && !$toDate) {

        if ($type == "all") {
            $purchases = Purchase::whereDate('created_at', $fromDate)->get();
            $charges   = ShippingCharges::whereDate('created_at', $fromDate)->get();

        } elseif ($type == "ShippingCharges") {
            $charges = ShippingCharges::whereDate('created_at', $fromDate)->get();

        } elseif ($type == "Purchase") {
            $purchases = Purchase::whereDate('created_at', $fromDate)->get();

        } else {
            $purchases = Purchase::whereDate('created_at', $fromDate)->get();
            $charges   = ShippingCharges::whereDate('created_at', $fromDate)->get();
        }

    } else {

        if ($type == "all") {
            $purchases = Purchase::whereBetween('created_at', [$fromDate, $toDate])->get();
            $charges   = ShippingCharges::whereBetween('created_at', [$fromDate, $toDate])->get();

        } elseif ($type == "ShippingCharges") {
            $charges = ShippingCharges::whereBetween('created_at', [$fromDate, $toDate])->get();

        } elseif ($type == "Purchase") {
            $purchases = Purchase::whereBetween('created_at', [$fromDate, $toDate])->get();

        } else {
            $purchases = Purchase::whereBetween('created_at', [$fromDate, $toDate])->get();
            $charges   = ShippingCharges::whereBetween('created_at', [$fromDate, $toDate])->get();
        }
    }

    // ================================
    // 💰 TOTALS PER PAYMENT + CURRENCY
    // ================================
    $totals = [];

    foreach ($paymentMethods as $method) {
        $totals[$method->id] = [
            'name' => $method->name,
            'currencies' => [
                'dinar'  => 0,
                'dollar' => 0,
                'euro'   => 0,
            ]
        ];
    }

    // merge all expenses into one collection
    $allExpenses = collect()
        ->merge($purchases)
        ->merge($charges);

    foreach ($allExpenses as $expense) {

        if (!$expense->payment_id) continue;

        $paymentId = $expense->payment_id;

        // currency column in your table
        $currency = $expense->currency ?? 'dinar';

        // normalize currency
        if (!in_array($currency, ['dinar', 'dollar', 'euro'])) {
            $currency = 'dinar';
        }

        // amount
        $amount = $expense->cost ?? 0;

        if (isset($totals[$paymentId]['currencies'][$currency])) {
            $totals[$paymentId]['currencies'][$currency] += $amount;
        }
    }

    // ================================
    // 📊 TOTAL PER CURRENCY (ALL METHODS)
    // ================================
    $currencyTotals = [
        'dinar'  => 0,
        'dollar' => 0,
        'euro'   => 0,
    ];

    foreach ($totals as $method) {
        foreach ($method['currencies'] as $currency => $value) {
            $currencyTotals[$currency] += $value;
        }
    }

    // ================================
    // 📦 RETURN VIEW
    // ================================
    return view('purchase.report', compact(
        'main_notifications',
        'fromDate',
        'toDate',
        'query',
        'purchases',
        'charges',
        'totals',
        'currencyTotals'
    ));
}
}
