<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Log;
use App\Models\Notif_orgs;
use App\Models\Order;
use App\Models\PaidHistory;
use App\Models\PaymentMethod;
use App\Models\Treasury;
use App\Models\TreasuryDollar;
use App\Models\TreasuryEuro;
use App\Models\TreasuryHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{

    function __construct()
    {
        // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
        $this->middleware('permission:customer-list', ['only' => ['index', 'show', 'download']]);
        $this->middleware('permission:customer-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:customer-edit', ['only' => ['edit', 'update', 'destroy2']]);
        $this->middleware('permission:customer-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $customers = Customer::latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.customer.index', compact('customers','main_notifications'));
    }

    public function paidhistory($id)
    {
        $customer = Customer::find($id);
        $paids = PaidHistory::where('customer_id', $id)->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.customer.paids', compact('customer','paids','main_notifications'));
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
        if (!request('name') && !request('phone_1')) {
            return redirect()->route('customer.index')->with('error', 'الرجاء تعبئة احدي الخانات علي الاقل');
        }
        $customers = Customer::query(); // Start a new query
        if (request('name') != null) {
            $customers->orWhere('name', 'like', '%' . request('name') . '%');
            $customers->orWhere('code', 'like', '%' . request('name') . '%');
        }
        if (request('phone_1') != null) {
            $customers->orWhere('phone_1', 'like', '%' . request('phone_1') . '%');
            $customers->orWhere('phone_2', 'like', '%' . request('phone_1') . '%');
        }
        $customers = $customers->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.customer.index', compact('customers', 'query','main_notifications'));
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
                'name'  => "required|string",
                'phone_1' => 'required|string|unique:customers',
            ],
            [
                'name.required' => 'يجب إدخال الاسم',
                'phone_1.required' => 'يجب اضافة البريد الالكتروني',
                'phone_1.unique' => 'البريد الالكتروني الذي قمت بإدخاله موجود',
            ]
        );

        DB::beginTransaction();
        try {
            $customer = new Customer;
            $customer->name = request('name');
            $customer->phone_1 = request('phone_1');
            $customer->phone_2 = request('phone_2');
            $customer->address = request('address');
            if(request('code') && Customer::where('code', request('code'))->exists()){
                DB::rollback();
                return redirect()->back()->with('error', 'كود الزبون الذي قمت قمت بإدخاله موجود مسبقآ الرجاء التحقق');
            }
            if(request('code') && !Customer::where('code', request('code'))->exists()){
                $customer->code = request('code');
            }else{
                $count = $this->generateUniqueCode($customer->phone_1);
                $customer->code = substr($customer->phone_1, -$count);
            }
            $customer->save();

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "customer";
            $log->title = " زبون جديد (" . request('name') . ")";
            $log->log = " تم اضافة زبون جديد (" . request('name') . ")";
            $log->save();

            DB::commit();
            return redirect()->back()->with('success', 'تــمــت إضــافــة الــزبــون بــنــجــاح');
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
    public function show($id)
    {
        $customer = Customer::find($id);
            $orders = Order::where('customer_id', $id)
            ->where(function ($query) {
                $query->where('status', 'unpaid')
                      ->orWhere(function ($query) {
                          $query->where('status', 'paid')
                                ->where('ship_price', '>', 0);
                      });
            })
            ->get();
            $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
            $payments = PaymentMethod::all();
        return view('admin.customer.show', compact('orders', 'customer','main_notifications', 'payments'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $customer = Customer::find($id);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.customer.edit', compact('customer','main_notifications'));
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
                'phone_1' => 'required|string',
                'code' => 'required|string',
            ],
            [
                'name.required' => 'يجب إدخال الاسم',
                'phone_1.required' => 'يجب اضافة البريد الالكتروني',
                'code.required' => 'يجب ادخال كود الزبون اجباري',
            ]
        );

        DB::beginTransaction();
        try {
            $customer = Customer::find($id);

            if (request('phone_1') != $customer->phone_1 && Customer::where('phone_1', request('phone_1'))->exists()) {
                DB::rollback();
                return redirect()->back()->with('error', 'رقم الهاتف الذي قمت بإدخاله موجود لدي زبون اخر');
            }
            $customer->name = request('name');
            $customer->phone_1 = request('phone_1');
            $customer->phone_2 = request('phone_2');
            $customer->address = request('address');

            if(request('code') && $customer->code != request('code') && Customer::where('code', request('code'))->exists()){
                DB::rollback();
                return redirect()->back()->with('error', 'كود الزبون الذي قمت قمت بإدخاله موجود مسبقآ الرجاء التحقق');
            }
            if(request('code') && $customer->code != request('code') && !Customer::where('code', request('code'))->exists()){
                $customer->code = request('code');
            }

            if ($request->has('reset_password')) {
                $customer->password = Hash::make($customer->phone_1);
            }

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "customer";
            $log->title = " تعديل الزبون (" . request('name') . ")";
            $log->log = " تم تعديل بيانات الزبون (" . request('name') . ")";
            $log->save();

            $customer->update();
            DB::commit();
            return redirect()->route('customer.index')->with('success', 'تــم تـعـديـل بـيـانـات الـزبـون بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    public function paidPrint($id)
    {
        $paid = PaidHistory::find($id);
        $invoice = Invoice::find(2);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
        ->latest()
        ->take(5)
        ->get();
        return view('admin.customer.print', ['paid' => $paid, 'invoice' => $invoice, 'main_notifications' => $main_notifications]);
    }

    public function print()
    {
        $customers = Customer::all();
        $page = "all";
        return view('admin.customer.print-customers', ['customers' => $customers, 'page' => $page]);
    }

    public function print_Depts()
    {
        $customers = Customer::whereHas('unpaidOrders')->get();
        $page = "depts";
        return view('admin.customer.print-customers', ['customers' => $customers, 'page' => $page]);
    }

    public function print_No_Depts()
    {
        $customers = Customer::whereDoesntHave('unpaidOrders')->get();
        $page = "noDepts";
        return view('admin.customer.print-customers', ['customers' => $customers, 'page' => $page]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function pay(Request $request, $id)
    {
        // dd($request->input('checked_order_ids')); // This should print the checked order IDs
        // $request->validate(
        //     [
        //         'price.*' => 'required|numeric|min:1',
        //         'checked_order_ids' => 'required|string',
        //     ],
        //     [
        //         'price.*.required' => 'يجب إدخال قيمة لكل طلبية محددة',
        //         'price.*.numeric' => 'يجب أن تكون القيمة عددية',
        //         'price.*.min' => 'يجب إدخال قيمة أكبر من الصفر لكل طلبية',
        //         'checked_order_ids.required' => 'يجب تحديد على الأقل طلبية واحدة',
        //     ]
        // );

        if (request('checked_order_ids') && is_array($request->input('price'))) {
            $valid = true;
        } elseif (request('delivered_order_ids')){
            $valid = true;
        } else {
            $valid = false;
        }
        if (!$valid) {
                return redirect()->back()->with('error', 'الرجاء تحديد طلب واحد علي الاقل');
        }


        DB::beginTransaction();
        try {
            $customer = Customer::find($id);
            $checkedOrderIds = $request->input('checked_order_ids'); // Get the checked order IDs
            $deliveredOrderIds = $request->input('delivered_order_ids'); // Get the checked order IDs
$prices = $request->input('price');
$paymentIds = $request->input('payment_id');

if ($checkedOrderIds) {

    $orderIds = explode(',', $checkedOrderIds);

    foreach ($orderIds as $orderId) {

        $order = Order::find($orderId);

        if ($order && $order->paid_price < ($order->total_price + $order->ship_price)) {

            $paymentAmount = $prices[$orderId];
            $paymentMethodId = $paymentIds[$orderId];

            // تحديث الطلب
            $order->paid_price += $paymentAmount;

            if ($order->ship_price && ($order->total_price + $order->ship_price) <= $order->paid_price) {
                $order->status = "paid_full";
            } elseif ($order->total_price <= $order->paid_price) {
                $order->status = "paid";
            } else {
                $order->status = "unpaid";
            }

            $order->save();

            // اختيار جدول الخزينة حسب العملة
            if ($order->currency == "dinar") {
                $treasuryRow = Treasury::where('payment_id', $paymentMethodId)->first();
            } elseif ($order->currency == "dollar") {
                $treasuryRow = TreasuryDollar::where('payment_id', $paymentMethodId)->first();
            } else {
                $treasuryRow = TreasuryEuro::where('payment_id', $paymentMethodId)->first();
            }

            // لو ما لقى سجل بالخزينة
            if (!$treasuryRow) {
                continue; // أو تعمل create بدل continue
            }

            $before = $treasuryRow->total;

            // تحديث الخزينة
            $treasuryRow->total = $before + $paymentAmount;
            $treasuryRow->save();

            // حفظ التاريخ
            $history = new TreasuryHistory;
            $history->price = $paymentAmount;
            $history->price_before = $before;
            $history->price_after = $treasuryRow->total;
            $history->status = "pay";
            $history->type = "order";
            $history->procedure_id = $order->id;
            $history->created_id = auth()->user()->id;
            $history->currency = $order->currency;
            $history->currency_sign = $order->currency_sign;
            $history->payment_id = $paymentMethodId;
            $history->save();

            // Paid History
            $pay = new PaidHistory;
            $pay->customer_id = $id;
            $pay->paid_price = $paymentAmount;
            $pay->order_id = $orderId;
            $pay->payment_id = $paymentMethodId;
            $pay->save();
        }
    }

    $log = new Log;
    $log->user_id = auth()->user()->id;
    $log->type = "customer";
    $log->title = "مستحقات الزبون (" . $customer->name . ")";
    $log->log = "تم دفع مستحقات الزبون (" . $customer->name . ")";
    $log->save();
}

            if($deliveredOrderIds){
                $orderIds2 = explode(',', $deliveredOrderIds); // Convert comma-separated IDs to an array
                Order::where('delivered', false)->whereIn('id', $orderIds2)->update(['delivered'=>true, 'delivered_at'=>now()]);
            }

            DB::commit();
            if($checkedOrderIds && $deliveredOrderIds){
                return redirect()->route('customer.index')->with('success', 'تــم دفــع مـسـتـحـقـات وتــســلــيــم الــطــلــبــات الـزبـون بــنــجــاح');
            }elseif($checkedOrderIds){
                return redirect()->route('customer.index')->with('success', 'تــم دفــع مـسـتـحـقـات الـزبـون بــنــجــاح');
            }elseif($deliveredOrderIds){
                return redirect()->route('customer.index')->with('success', 'تــم تــســلــيــم الــطــلــبــات الـزبـون بــنــجــاح');
            }else{
                return redirect()->route('customer.index')->with('success', 'تــم دفــع مـسـتـحـقـات الـزبـون بــنــجــاح');
            }
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
            $paid = PaidHistory::find($id);
            if(!$paid){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }
                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "customer";
                $log->title = " حذف سجل تسديد الزبون (" . $paid->paid_price . ") DYL";
                $log->log = " تم حذف سجل تسديد الزبون بتاريخ (" . $paid->created_at->format('Y-m-d') . ") للطلبية " . $paid->order->code . " ";
                $log->save();
                $paid->delete();
                DB::commit();
                return redirect()->route('customer.index')->with('success', 'تــم دفــع مـسـتـحـقـات الـزبـون بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    public function destroy2($id)
    {
        DB::beginTransaction();
        try {

            $customer = Customer::find($id);
            if(!$customer){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }

            if($customer->orders->count() > 0){
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف لايمكن حذف الزبون وذلك لوجد طلبات خاصة بالزبون');
            }

                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "customer";
                $log->title = " حذف زبون (" . $customer->name . ") DYL";
                $log->log = " تم حذف الزبون بتاريخ (" . now()->format('Y-m-d') . ") تحت الكود " . $customer->code . " ";
                $log->save();

                $customer->delete();

                DB::commit();
                return redirect()->route('customer.index')->with('success', 'تــم حــذف زبـون بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }
}
