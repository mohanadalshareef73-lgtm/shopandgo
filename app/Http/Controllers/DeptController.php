<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Dept;
use App\Models\DeptHistory;
use App\Models\Log;
use App\Models\Notif_orgs;
use App\Models\Treasury;
use App\Models\TreasuryDollar;
use App\Models\TreasuryEuro;
use App\Models\TreasuryHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeptController extends Controller
{

    function __construct()
    {
        // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
        $this->middleware('permission:dept-list', ['only' => ['index', 'show', 'search']]);
        $this->middleware('permission:dept-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:dept-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:dept-delete', ['only' => ['destroy','destroy2']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */public function index()
{
    $customers = Customer::whereHas('depts')
        ->withCount('depts')
        ->withSum('depts', 'total')
        ->select('customers.*')
        ->selectSub(function ($query) {
            $query->from('dept_histories')
                ->join('depts', 'dept_histories.dept_id', '=', 'depts.id')
                ->whereColumn('depts.customer_id', 'customers.id')
                ->selectRaw('COALESCE(SUM(dept_histories.paid_price),0)');
        }, 'total_paid')
        ->latest()
        ->paginate(25);

    $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();

    $page = "all";

    $totalDay = Dept::whereDate('created_at', Carbon::today())->sum('total');

    $totalMonth = Dept::whereYear('created_at', Carbon::now()->year)
        ->whereMonth('created_at', Carbon::now()->month)
        ->sum('total');

    $totalYear = Dept::whereYear('created_at', Carbon::now()->year)->sum('total');

    $totalDay2 = DeptHistory::whereDate('created_at', Carbon::today())->sum('paid_price');

    $totalMonth2 = DeptHistory::whereYear('created_at', Carbon::now()->year)
        ->whereMonth('created_at', Carbon::now()->month)
        ->sum('paid_price');

    $totalYear2 = DeptHistory::whereYear('created_at', Carbon::now()->year)->sum('paid_price');

    return view('admin.dept.index', compact(
        'page',
        'totalDay',
        'totalMonth',
        'totalYear',
        'totalDay2',
        'totalMonth2',
        'totalYear2',
        'customers',
        'main_notifications'
    ));
}


    public function show($id)
    {
    $cus = Customer::find($id);
        $depts = Dept::where('customer_id', $id)->latest()->paginate(25);
        $customers = Customer::all();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        $page = "all";
        $totalDay = Dept::where('customer_id', $id)->whereDate('created_at', Carbon::today())->sum('total');
        $totalMonth = Dept::where('customer_id', $id)->whereYear('created_at', Carbon::now()->year)
                              ->whereMonth('created_at', Carbon::now()->month)
                              ->sum('total');
        $totalYear = Dept::where('customer_id', $id)->whereYear('created_at', Carbon::now()->year)->sum('total');

        $deptIds = Dept::where('customer_id', $id)->pluck('id');
        $totalDay2 = DeptHistory::whereIn('dept_id', $deptIds)->whereDate('created_at', Carbon::today())->sum('paid_price');
        $totalMonth2 = DeptHistory::whereIn('dept_id', $deptIds)->whereYear('created_at', Carbon::now()->year)
                              ->whereMonth('created_at', Carbon::now()->month)
                              ->sum('paid_price');
        $totalYear2 = DeptHistory::whereIn('dept_id', $deptIds)->whereYear('created_at', Carbon::now()->year)->sum('paid_price');
        return view('admin.dept.show', compact('cus','page','totalDay','totalMonth','totalYear','totalDay2','totalMonth2','totalYear2', 'customers', 'depts','main_notifications'));
    }

    public function unpaidDepts()
    {
        $depts = Dept::where('status', 'dept')
            ->with(['customer', 'createdUser'])
            ->withSum('paids', 'paid_price')
            ->paginate(25);
        $customers = Customer::all();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        $page = "depts";
        $total = (Dept::sum('total') - DeptHistory::sum('paid_price'));
        return view('admin.dept.index', compact('page', 'total', 'customers', 'depts','main_notifications'));
    }

    public function paidDepts()
    {
        $depts = Dept::where('status', 'paid')
            ->with(['customer', 'createdUser'])
            ->withSum('paids', 'paid_price')
            ->paginate(25);
        $customers = Customer::all();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        $page = "paid";
        $total = DeptHistory::sum('paid_price');
        return view('admin.dept.index', compact('page', 'total', 'customers', 'depts','main_notifications'));
    }

    public function paidhistory($id)
    {
        $dept = Dept::find($id);
        $paids = DeptHistory::where('dept_id', $id)->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.dept.paids', compact('dept', 'paids','main_notifications'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

    }

    public function print(Request $request)
    {
        $query = $request->all();
        if (!$request->filled('startDate') && !$request->filled('endDate') && !$request->filled('status')) {
            return redirect()->route('dept.index')->with('error', 'الرجاء تعبئة إحدى الخانات على الأقل');
        }
        $depts = Dept::query();
        if ($request->filled('startDate') && !$request->filled('endDate')) {
            $depts->whereDate('date', '=', $request->input('startDate'));
        }
        elseif ($request->filled('startDate') && $request->filled('endDate')) {
            $depts->whereBetween('date', [$request->input('startDate'), $request->input('endDate')]);
        }
        elseif (!$request->filled('startDate') && $request->filled('endDate')) {
            $depts->whereDate('date', '=', $request->input('endDate'));
        }
        if ($request->filled('status')) {
            $depts->where('status', $request->input('status'));
        }
        $depts = $depts->get();
        if($depts->count() == 0){
            return redirect()->route('dept.index')->with('error', 'لا يوجد اي سجلات بالبيانات المختارة');
        }
        return view('admin.dept.printDepts', compact('depts', 'query'));
    }
public function search(Request $request)
{
    // ❌ Prevent empty search
    if (
        !$request->filled('name') &&
        !$request->filled('status') &&
        !$request->filled('from') &&
        !$request->filled('to')
    ) {
        return redirect()->route('dept.index')
            ->with('error', 'الرجاء تعبئة احدي الخانات علي الاقل');
    }

    // Save query (for inputs refill)
    $query = $request->all();

    // Start query
    $depts = Dept::query();

    // 🔍 Search by name (ORG or Customer)
    if ($request->filled('name')) {
        $depts->where(function ($q) use ($request) {
            $q->where('org', 'like', '%' . $request->name . '%')
              ->orWhereHas('customer', function ($q2) use ($request) {
                  $q2->where('name', 'like', '%' . $request->name . '%');
              });
        });
    }

    // 📅 Date filters
    if ($request->filled('from') && !$request->filled('to')) {
        $depts->whereDate('date', '>=', $request->from);
    } elseif ($request->filled('from') && $request->filled('to')) {
        $depts->whereBetween('date', [$request->from, $request->to]);
    } elseif (!$request->filled('from') && $request->filled('to')) {
        $depts->whereDate('date', '<=', $request->to);
    }

    // 📌 Status filter
    if ($request->filled('status')) {
        $depts->where('status', $request->status);
    }

    // 📄 Get results
    $depts = $depts->with(['customer', 'createdUser'])
        ->withSum('paids', 'paid_price')
        ->latest()
        ->paginate(25)
        ->withQueryString();

    // 🔔 Notifications
    $main_notifications = Notif_orgs::where('user_id', auth()->id())
        ->latest()
        ->take(5)
        ->get();

    $customers = Customer::all();
    $page = "search";

    return view('admin.dept.index', compact(
        'page',
        'customers',
        'depts',
        'query',
        'main_notifications'
    ));
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
                'customer_id' => 'nullable',
                'name' => 'required_if:customer_id,new',
                'phone_1' => 'required_if:customer_id,new|max:20|unique:customers,phone_1',
                'phone_2' => 'nullable|string|max:20',
                'total'  => "required",
                'date'  => "required|string",
            ],
            [
                'name.required_if' => 'يجب إدخال الاسم',
                'phone_1.required_if' => 'يجب اضافة رقم الهاتف',
                'phone_1.unique' => 'رقم الهاتف الذي قمت بإدخاله موجود',
                'total.required' => 'يجب إدخال قيمة الدين',
                'date.required' => 'يجب إدخال تاريخ الدين',
            ]
        );

        DB::beginTransaction();
        try {
            // Check if a new customer needs to be created
            if ($request->customer_id == 'new') {
                $customer = new Customer;
                $customer->name = request('name');
                $customer->phone_1 = request('phone_1');
                $customer->phone_2 = request('phone_2');
                $customer->address = request('address');
                if (request('code') && Customer::where('code', request('code'))->exists()) {
                    DB::rollback();
                    return redirect()->back()->with('error', 'كود الزبون الذي قمت قمت بإدخاله موجود مسبقآ الرجاء التحقق');
                }
                if (request('code') && !Customer::where('code', request('code'))->exists()) {
                    $customer->code = request('code');
                } else {
                    $count = $this->generateUniqueCode($customer->phone_1);
                    $customer->code = substr($customer->phone_1, -$count);
                }
                $customer->save();
            } else {
                $customer = Customer::find($request->customer_id);
            }

            $dept = new Dept;
            $dept->customer_id = $customer->id;
            $name = $customer->name;

            $dept->total = request('total');
            $dept->status = "dept";
            $dept->date = request('date');
            $dept->org = request('org_name');
            $dept->note = request('note');
            $dept->created_id = auth()->user()->id;


            $dept->save();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "dept";
            $log->title = " دين جديد (" . $name . ")";
            $log->log = " تم اضافة دين جديد (" . $name . ") بقيمة (" . request('total') . ") ";
            $log->save();

            DB::commit();
            return redirect()->back()->with('success', 'تــمــت إضــافــةديــــن بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    function generateUniqueCode($phone)
    {
        $count = 3;
        do {
            $count++;
            $exists = $this->checkCodeExistsInDatabase(substr($phone, -$count));
        } while ($exists);
        return $count;
    }
    function checkCodeExistsInDatabase($code)
    {
        return Customer::where('code', $code)->exists();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $dept = Dept::find($id);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        $customers = Customer::all();
        return view('admin.dept.edit', compact('dept', 'customers','main_notifications'));
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
                'total'  => "required",
                'date'  => "required|string",
            ],
            [
                'total.required' => 'يجب إدخال قيمة الدين',
                'date.required' => 'يجب إدخال تاريخ الدين',
            ]
        );
        DB::beginTransaction();
        try {
            $dept = Dept::find($id);
            if(!request('customer_id') && !request('name')){
                DB::rollback();
                return redirect()->back()->with('error', 'يجب اختيار زبون معين او كتابة الجهة معينة');
            }

            $customer = Customer::find(request('customer_id'));
            $dept->customer_id = $customer->id;
            $name = $customer->name;
            $dept->org = request('org_name');

            $dept->total = request('total');
            $dept->date = request('date');
            $dept->note = request('note');

            $dept->save();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "dept";
            $log->title = " تعديل الدين (" . $name . ")";
            $log->log = " تم تعديل بيانات دين  (" . $name . ") بقيمة (" . request('total') . ") ";
            $log->save();

            $dept->update();
            DB::commit();
            return redirect()->route('dept.index')->with('success', 'تــم تـعـديـل بـيـانـات الـديــن بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    public function paidPrint($id)
    {
        $paid = DeptHistory::find($id);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        $customers = Customer::all();
        return view('admin.dept.print', ['paid' => $paid, 'main_notifications' => $main_notifications]);
    }

    public function payRest(Request $request, $id)
{
    $request->validate(
        [
            'paid_price' => 'required|numeric',
            'payment_id' => 'required|exists:payment_methods,id',
            'currency' => 'required|in:dinar,dollar euro'
        ],
        [
            'paid_price.required' => 'يجب إدخال القيمة المسددة من الزبون',
        ]
    );

    DB::beginTransaction();
    try {
        $dept = Dept::find($id);
        if ($dept->total <= $dept->paids->sum('paid_price')) {
            DB::rollback();
            return redirect()->back()->with('error', 'لقد تم دفع تسديد القيمة من الدين مسبقآ الرجاء التحقق');
        }

        if ($dept) {
            // Check if the last DeptHistory record was saved more than 10 minutes ago
            $lastDeptHistory = DeptHistory::where('dept_id', $dept->id)->latest()->first();
            if ($lastDeptHistory) {
                $now = now();
                $lastTime = $lastDeptHistory->created_at;
                $diffInMinutes = $now->diffInMinutes($lastTime);
                if ($diffInMinutes < 10) {
                    DB::rollback();
                    return redirect()->back()->with('error', 'لا يمكن تسديد دفعة أخرى قبل مرور 10 دقائق من آخر دفعة.');
                }
            }

            // Proceed with saving the payment

$totalPaid = $dept->paids->sum('paid_price');
$paymentAmount = $request->paid_price;

// update dept status
if (($totalPaid + $paymentAmount) >= $dept->total) {
    $dept->status = "paid";
} else {
    $dept->status = "dept";
}

if ($dept->customer) {
    $name = $dept->customer->name;
} else {
    $name = $dept->org;
}

$dept->save();

// currency sign
if ($request->currency == "dinar") {
    $currency_sign = 'د.ل';
} elseif ($request->currency == "dollar") {
    $currency_sign = '$';
} else {
    $currency_sign = '€';
}

// save dept history
$deptHistory = new DeptHistory;
$deptHistory->dept_id = $dept->id;
$deptHistory->paid_price = $paymentAmount;
$deptHistory->created_id = auth()->user()->id;
$deptHistory->currency = $request->currency;
$deptHistory->payment_id = $request->payment_id;
$deptHistory->currency_sign = $currency_sign;
$deptHistory->save();


// get treasury row based on currency + payment_id
if ($request->currency == "dinar") {
    $treasuryRow = Treasury::where('payment_id', $request->payment_id)->first();
} elseif ($request->currency == "dollar") {
    $treasuryRow = TreasuryDollar::where('payment_id', $request->payment_id)->first();
} else {
    $treasuryRow = TreasuryEuro::where('payment_id', $request->payment_id)->first();
}

// check if treasury row exists
if (!$treasuryRow) {
    return redirect()->back()->with('error', 'لا يوجد خزينة لهذه الطريقة');
}

$before = $treasuryRow->total;
$after = $before - $paymentAmount;

// save treasury history
$history = new TreasuryHistory;
$history->price = $paymentAmount;
$history->price_before = $before;
$history->price_after = $after;
$history->status = "pull";
$history->type = "dept";
$history->procedure_id = $dept->id;
$history->created_id = auth()->user()->id;
$history->currency = $request->currency;
$history->payment_id = $request->payment_id;
$history->currency_sign = $currency_sign;
$history->save();


// update treasury
$treasuryRow->total = $after;
$treasuryRow->save();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "dept";
            $log->title = " تسديد ديون الزبون (" . $name . ")";
            $log->log = " تم تسديد الدين للزبون (" . $name . ") بقيمة " . $paymentAmount . " DYL ";
            $log->save();
        } else {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }

        DB::commit();
        return redirect()->back()->with('success', 'تــم تـسـديـد الـديــن بــنــجــاح');

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

            $paid = deptHistory::find($id);
            if(!$paid){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }
            $dept = Dept::find($paid->dept_id);
            if($paid->dept->org){
                $name = $paid->dept->org;
            }else{
                $name = $paid->dept->customer->name;
            }

            if(($dept->paids->sum('paid_price') - $paid->paid_price) < $dept->total){
                $dept->status = "dept";
                $dept->update();
            }

                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "dept";
                $log->title = " حذف سجل دفع دين للزبون (" . $name . ") DYL";
                $log->log = " تم حذف سجل دفع ديون للزبون المسجلة بتاريخ (" . $paid->created_at->format('Y-m-d') . ") بقيمة (" . $paid->paid_price . ") ";
                $log->save();

                $paid->delete();

                DB::commit();
                return redirect()->route('dept.paidhistory',[$paid->dept_id])->with('success', 'تــم حــذف سـجـل دفـع الـديـن بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    public function destroy2($id)
    {
        DB::beginTransaction();
        try {

            $dept = Dept::find($id);
            if(!$dept){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }

            if($dept->paids->count() > 0){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف لايمكن حذف الدين وذلك لوجد سجلات دفع خاصة بالدين');
            }

            if($dept->org){
                $name = $dept->org;
            }else{
                $name = $dept->customer->name;
            }

                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "dept";
                $log->title = " حذف الدين (" . $name . ") DYL";
                $log->log = " تم حذف الدين المسجل بتاريخ (" . $dept->created_at->format('Y-m-d') . ") بقيمة " . $dept->total . " ";
                $log->save();

                $dept->delete();

                DB::commit();
                return redirect()->route('dept.index')->with('success', 'تــم حــذف الـديـن بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

}
