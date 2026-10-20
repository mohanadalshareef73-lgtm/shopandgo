<?php

namespace App\Http\Controllers;

use App\Models\Log;
use Illuminate\Http\Request;
use App\Models\Profit;
use App\Models\Notif_orgs;
use App\Models\Platform;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProfitController extends Controller
{

    function __construct()
    {
        $this->middleware('permission:profits-list', ['only' => ['index', 'search']]);
        $this->middleware('permission:profits-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:profits-edit', ['only' => ['edit', 'show', 'update']]);
        $this->middleware('permission:profits-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $profits = Profit::latest()->paginate(25);
        $profitsDinar = Profit::where('currency', 'dinar')->sum('cost');
        $profitsDollar = Profit::where('currency', 'dollar')->sum('cost');
        $profitsEuro = Profit::where('currency', 'euro')->sum('cost');
                $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        $platforms = Platform::all();


                $totalDay = Profit::whereDate('created_at', Carbon::today())
        ->where('currency', 'dinar')->sum('cost');
        $totalDayDollar = Profit::whereDate('created_at', Carbon::today())
        ->where('currency', 'dollar')->sum('cost');
        $totalDayEuro = Profit::whereDate('created_at', Carbon::today())
        ->where('currency', 'euro')->sum('cost');

        $totalMonth = Profit::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dinar')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');
        $totalMonthDollar = Profit::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dollar')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');
        $totalMonthEuro = Profit::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'euro')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');

        $totalYear = Profit::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'dinar')->sum('cost');
        $totalYearDollar = Profit::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'dollar')->sum('cost');
        $totalYearEuro = Profit::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'euro')->sum('cost');


        return view('admin.profits.index', compact('totalDay','totalDayDollar','totalDayEuro', 'totalMonth','totalMonthDollar','totalMonthEuro', 'totalYear','totalYearDollar','totalYearEuro','profits','profitsDinar','profitsDollar','profitsEuro', 'main_notifications','platforms'));
    }

    public function search(Request $request)
    {
        if (!empty($request)) {
            $query = $request->all();
        }
        if (!request('fromDate') && !request('platform_id')) {
            return redirect()->back()->with('error', 'الرجاء تعبئة احدي الخانات علي الاقل');
        }

        $profits = Profit::query(); // Start a new query
        if ($request->filled('toDate') && $request->filled('fromDate')) {
            $profits = $profits->whereBetween('date', [request('fromDate'), request('toDate')]);
        } elseif ($request->filled('fromDate')) {
            $profits = $profits->whereDate('date', '>=', request('fromDate'));
        }
        if ($request->filled('platform_id')) {
            $profits = $profits->where('platform_id', request('platform_id'));
        }

        $profits = $profits->latest()->paginate(25);
                        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        $platforms = Platform::all();


                $totalDay = Profit::whereDate('created_at', Carbon::today())
        ->where('currency', 'dinar')->sum('cost');
        $totalDayDollar = Profit::whereDate('created_at', Carbon::today())
        ->where('currency', 'dollar')->sum('cost');
        $totalDayEuro = Profit::whereDate('created_at', Carbon::today())
        ->where('currency', 'euro')->sum('cost');

        $totalMonth = Profit::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dinar')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');
        $totalMonthDollar = Profit::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dollar')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');
        $totalMonthEuro = Profit::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'euro')->whereMonth('created_at', Carbon::now()->month)
            ->sum('cost');

        $totalYear = Profit::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'dinar')->sum('cost');
        $totalYearDollar = Profit::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'dollar')->sum('cost');
        $totalYearEuro = Profit::whereYear('created_at', Carbon::now()->year)
        ->where('currency', 'euro')->sum('cost');

        return view('admin.profits.index', compact('totalDay','totalDayDollar','totalDayEuro', 'totalMonth','totalMonthDollar','totalMonthEuro', 'totalYear','totalYearDollar','totalYearEuro','profits', 'query', 'main_notifications', 'platforms'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        request()->validate(
            [
                'platform_id'  => "required|string|exists:platforms,id",
                'cost'  => "required|numeric|min:0",
                'date'  => "required|date",
                'currency'  => "required|string",
            ],
            [
                'platform_id.required' => 'يجب اختيار المنصة',
                'platform_id.exists' => 'المنصة المختارة غير موجودة',
                'cost.required' => 'يجب إدخال تكلفة الربح',
                'cost.numeric' => 'يجب أن تكون تكلفة الربح رقم',
                'cost.min' => 'يجب أن تكون تكلفة الربح أكبر من أو تساوي 0',
                'date.required' => 'يجب إدخال تاريخ الربح',
                'date.date' => 'يجب أن يكون تاريخ الربح تاريخ صالح',
                'currency.required' => 'يجب إدخال العملة',
                'currency.string' => 'يجب أن تكون العملة نص',
            ]
        );

        DB::beginTransaction();
        try {
            $profit = new Profit;
            $profit->platform_id = request('platform_id');
            $profit->cost = request('cost');
            $profit->date = request('date');
            $profit->currency = request('currency');
            $profit->notes = request('notes');
            $profit->created_id = auth()->user()->id;
            $profit->save();

            Log::create([
                'type' => 'profits',
                'title' => ' تم اضافة طريق جديدة (' . $profit->name . ')',
                'log' => 'تم اضافة أربـاح بنسبة  (' . $profit->rate . ')',
                'user_id' => auth()->user()->id,
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'تــمــت إضــافــة أربـــاح بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            report($e); // كان dd($e) يطلع كود للمستخدم ويمنع الرجوع
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $profit = Profit::find($id);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        $platforms = Platform::all();
        return view('admin.profits.edit', compact('profit', 'main_notifications', 'platforms'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $profit = Profit::findOrFail($id);
                request()->validate(
            [
                'platform_id'  => "required|string|exists:platforms,id",
                'cost'  => "required|numeric|min:0",
                'date'  => "required|date",
                'currency'  => "required|string",
            ],
            [
                'platform_id.required' => 'يجب اختيار المنصة',
                'platform_id.exists' => 'المنصة المختارة غير موجودة',
                'cost.required' => 'يجب إدخال تكلفة الربح',
                'cost.numeric' => 'يجب أن تكون تكلفة الربح رقم',
                'cost.min' => 'يجب أن تكون تكلفة الربح أكبر من أو تساوي 0',
                'date.required' => 'يجب إدخال تاريخ الربح',
                'date.date' => 'يجب أن يكون تاريخ الربح تاريخ صالح',
                'currency.required' => 'يجب إدخال العملة',
                'currency.string' => 'يجب أن تكون العملة نص',
            ]
        );


        DB::beginTransaction();
        try {
            $oldCost = $profit->cost;
            $oldate = $profit->date;
            $profit->platform_id = request('platform_id');
            $profit->cost = request('cost');
            $profit->date = request('date');
            $profit->currency = request('currency');
            $profit->notes = request('notes');
            $profit->save();
            Log::create([
                'type' => 'profits',
                'title' => ' تم تعديل أربـاح من  (' . $oldCost . ') الي (' . $profit->cost . ')',
                'log' => 'تم تعديل أربـاح من تاريخ  (' . $oldate . ') الي (' . $profit->date . ')',
                'user_id' => auth()->user()->id,
            ]);

            DB::commit();
            return redirect()->route('profits.index')->with('success', 'تــمــت تـعـديـل أربـــاح بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            report($e); // كان dd($e) يطلع كود للمستخدم ويمنع الرجوع
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {

            $profit = Profit::find($id);
            Log::create([
                'type' => 'profits',
                'title' => ' تم حذف أربـاح (' . $profit->cost . ')',
                'log' => 'تم حذف أربـاح بنسبة (' . $profit->platform->name . ') بتاريخ (' . $profit->date . ')',
                'user_id' => auth()->user()->id,
            ]);
            $profit->delete();

            DB::commit();
            return redirect()->back()->with('success', 'تــمــت حــذفــ أربـــاح بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            report($e); // كان dd($e) يطلع كود للمستخدم ويمنع الرجوع
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }


}
