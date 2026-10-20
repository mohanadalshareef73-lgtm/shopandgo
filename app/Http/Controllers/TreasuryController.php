<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Notif_orgs;
use App\Models\PaymentMethod;
use App\Models\Treasury;
use App\Models\TreasuryDollar;
use App\Models\TreasuryEuro;
use App\Models\TreasuryHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SebastianBergmann\CodeCoverage\Report\Xml\Totals;

class TreasuryController extends Controller
{
    function __construct()
    {
        // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
        $this->middleware('permission:treasury-list', ['only' => ['index', 'show', 'download']]);
        $this->middleware('permission:treasury-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:treasury-edit', ['only' => ['edit', 'update', 'destroy2']]);
        $this->middleware('permission:treasury-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $history = TreasuryHistory::latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.treasury.index', compact('history','main_notifications'));
    }

    public function showSafe(Request $request, $currency){
        $history = TreasuryHistory::where('currency', $currency)->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();

        if($currency == "dinar"){
            $currencySign = "د.ل";
            $treasury = Treasury::all();
        }elseif($currency == "dollar"){
            $currencySign = "$";
            $treasury = TreasuryDollar::all();
        }else{
            $currencySign = "€";
            $treasury = TreasuryEuro::all();
        }
        return view('admin.treasury.index', compact('history','main_notifications','currency','currencySign','treasury'));
    }


    public function search(Request $request)
    {
        $query = $request->all();

        if (!$request->filled('from') && !$request->filled('to') && !$request->filled('status')) {
            return redirect()->route('safe.index')->with('error', 'الرجاء تعبئة إحدى الخانات على الأقل');
        }

        $history = TreasuryHistory::query();

        if ($request->filled('from') && !$request->filled('to')) {
            $history->whereDate('created_at', '>=', $request->input('from'));
        } elseif ($request->filled('from') && $request->filled('to')) {
            $history->whereBetween('created_at', [$request->input('from'), $request->input('to')]);
        } elseif (!$request->filled('from') && $request->filled('to')) {
            $history->whereDate('created_at', '<=', $request->input('to'));
        }
        if ($request->filled('status')) {
            $history->where('status', $request->input('status'));
        }
        $history = $history->latest()->paginate(25);

        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();

        return view('admin.treasury.index', compact('history', 'query', 'main_notifications'));
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */public function update(Request $request, $id)
{
    DB::beginTransaction();

    try {

        // تحديد جدول الخزينة حسب العملة
        if ($id == "dinar") {
            $model = Treasury::class;
            $currencySign = "د.ل";
        } elseif ($id == "dollar") {
            $model = TreasuryDollar::class;
            $currencySign = "$";
        } elseif ($id == "euro") {
            $model = TreasuryEuro::class;
            $currencySign = "€";
        } else {
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }

        // جلب طرق الدفع
        $payments = auth()->user()->payments();

        foreach ($payments as $payment) {

            $fieldName = "total" . $payment->id;

            // تحقق أن الحقل موجود
            $request->validate([
                $fieldName => "required|numeric"
            ], [
                $fieldName . ".required" => "يجب ادخال قيمة الخزينة لـ " . $payment->name,
            ]);

            // جلب الخزينة الخاصة بهذه الطريقة
            $charge = $model::where('payment_id', $payment->id)->first();

            if (!$charge) {
                continue; // أو يمكنك إنشاء سجل جديد
            }

            $before = $charge->total;

            // تحديث القيمة
            $charge->total = $request->$fieldName;
            $charge->update();

            // حفظ في التاريخ
            $history = new TreasuryHistory;
            $history->created_id = auth()->user()->id;
            $history->price = $charge->total;
            $history->price_before = $before;
            $history->price_after = $charge->total;
            $history->status = "update";
            $history->type = "user";
            $history->procedure_id = auth()->user()->id;
            $history->currency = $id;
            $history->currency_sign = $currencySign;
            $history->payment_id = $payment->id;
            $history->save();

            // log
            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "Treasury";
            $log->title = "تعديل قيمة الخزينة (" . $payment->name . ")";
            $log->log = "تم تعديل قيمة الخزينة لـ (" . $payment->name . ") من (" . $before . ") الي (" . $charge->total . ")";
            $log->save();
        }

        DB::commit();
        return redirect()->back()->with('success','تــم تـعـديـل قـيـمـة الـخـزيـنـة بــنــجــاح');

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

            request()->validate(
            [
                'currency' => 'required|in:dinar,dollar,euro',
            ],
            [
                'currency.required' => 'يجب ادخال نوع العمولة',
                'currency.in' => 'يجب ان تكون نوع العمولة من ضمن الموجود',
            ]);

        DB::beginTransaction();
        try {

            $history = TreasuryHistory::find($id);
            if(!$history || $history->currency != request('currency')){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }

            if (in_array($history->type, ['trip_purchase', 'trip_purchase_reverse', 'exchange', 'exchange_reverse', 'partner', 'partner_reverse', 'courier', 'transfer', 'purchase', 'purchase_reverse', 'shippingCharge', 'shippingCharge_reverse', 'shein_card', 'shein_card_reverse'])) {
                DB::rollback();
                return redirect()->back()->with('error', 'الحركة هذي مربوطة بعملية (رحلة شراء / شراء دولار / شريك). احذفها من شاشتها باش الحسابات تبقى مظبوطة');
            }

                $currencySign = \App\Services\TreasuryService::sign($history->currency);
                // نرجع الأثر على نفس الخزينة (نفس العملة ونفس طريقة الدفع)
                $treasury = \App\Services\TreasuryService::row($history->currency ?: 'dinar', (int) ($history->payment_id ?: 1));

                if($history->status == "pay"){
                    $treasury->total -= $history->price;
                }elseif($history->status == "pull"){
                    $treasury->total += $history->price;
                }
                $treasury->update();

                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "TreasuryHistory";
                $log->title = " حذف سجل الخزينة (" . $history->price. ") ".$currencySign."";
                $log->log = " تم حذف سجل الخزينة بتاريخ (" . now()->format('Y-m-d') . ") بقيمة " . $history->price. " ".$currencySign." ";
                $log->save();

                $history->delete();

                DB::commit();
                return redirect()->route('safe.index')->with('success', 'تــم حــذف سـجـل مـن الـخـزيـنـة بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }
}
