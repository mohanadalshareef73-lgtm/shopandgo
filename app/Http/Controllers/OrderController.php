<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Log;
use App\Models\Notif_orgs;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNotes;
use App\Models\PaidHistory;
use App\Models\PaymentMethod;
use App\Models\Platform;
use App\Models\Purchase;
use App\Models\PurchaseDescription;
use App\Models\Status;
use App\Models\Treasury;
use App\Models\TreasuryDollar;
use App\Models\TreasuryEuro;
use App\Models\TreasuryHistory;
use App\Models\Trip;
use App\Models\whatsappMessage;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    function __construct()
    {
        // $this->middleware('permission:role-list|role-create|role-edit|role-delete', ['only' => ['index','store']]);
        $this->middleware('permission:order-list', ['only' => ['index', 'show', 'download']]);
        $this->middleware('permission:order-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:order-edit', ['only' => ['edit', 'update', 'destroy2']]);
        $this->middleware('permission:order-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $orders = Order::latest()->paginate(25);
        $customers = Customer::all();
        $platforms = Platform::all();
        // $trips = Trip::where('arrived', false)->get();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
        $page = "all";
        session()->forget('search_results_url');

        $totalDay = Order::whereDate('created_at', Carbon::today())
            ->where('currency', 'dinar')->sum('total_price', 'ship_price');
        $totalDayDollar = Order::whereDate('created_at', Carbon::today())
            ->where('currency', 'dollar')->sum('total_price', 'ship_price');
        $totalDayEuro = Order::whereDate('created_at', Carbon::today())
            ->where('currency', 'euro')->sum('total_price', 'ship_price');

        $totalMonth = Order::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dinar')->whereMonth('created_at', Carbon::now()->month)
            ->sum('total_price', 'ship_price');
        $totalMonthDollar = Order::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dollar')->whereMonth('created_at', Carbon::now()->month)
            ->sum('total_price', 'ship_price');
        $totalMonthEuro = Order::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'euro')->whereMonth('created_at', Carbon::now()->month)
            ->sum('total_price', 'ship_price');

        $totalYear = Order::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dinar')->sum('total_price', 'ship_price');
        $totalYearDollar = Order::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'dollar')->sum('total_price', 'ship_price');
        $totalYearEuro = Order::whereYear('created_at', Carbon::now()->year)
            ->where('currency', 'euro')->sum('total_price', 'ship_price');


        $totalDay2 = Order::whereDate('created_at', Carbon::today())->sum('ship_price');
        $totalMonth2 = Order::whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('ship_price');
        $totalYear2 = Order::whereYear('created_at', Carbon::now()->year)->sum('ship_price');

        $status = Status::get();
        $payments = PaymentMethod::all();
        return view('admin.order.index', compact('orders', 'payments', 'status', 'totalDay', 'totalDayDollar', 'totalDayEuro', 'totalMonth', 'totalMonthDollar', 'totalMonthEuro', 'totalYear', 'totalYearDollar', 'totalYearEuro', 'totalDay2', 'totalMonth2', 'totalYear2', 'customers', 'platforms', 'main_notifications', 'page'));
    }


    public function orderPaidsHistory($id)
    {
        $order = Order::find($id);
        $paids = PaidHistory::where('order_id', $id)->latest()->paginate(25);
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
        return view('admin.order.paids', compact('order', 'paids', 'main_notifications'));
    }


    public function ready()
    {
        $orders = Order::where([['arrived', true], ['delivered', false]])->orderBy('arrived_at', 'desc')->paginate(25);
        $customers = Customer::all();
        // $trips = Trip::where('arrived', false)->get();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)->latest()->take(5)->get();
        $page = "ready";
        $platforms = Platform::all();
        $status = Status::get();
        $payments = PaymentMethod::all();
        return view('admin.order.index', compact('orders', 'payments', 'status', 'platforms', 'customers', 'main_notifications', 'page'));
    }

    public function getDelivered()
    {
        $orders = Order::where([['delivered', true], ['delivered_at', '!=', null]])->orderBy('delivered_at', 'desc')->paginate(25);
        $customers = Customer::all();
        // $trips = Trip::where('arrived', false)->get();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)->latest()->take(5)->get();
        $page = "delivered";
        $platforms = Platform::all();
        $status = Status::get();
        $payments = PaymentMethod::all();

        return view('admin.order.index', compact('orders', 'payments', 'status', 'platforms', 'customers', 'main_notifications', 'page'));
    }

    public function notDelivered()
    {
        $orders = Order::where('delivered', false)->orderBy('delivered_at', 'desc')->paginate(25);
        $customers = Customer::all();
        // $trips = Trip::where('arrived', false)->get();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)->latest()->take(5)->get();
        $page = "notDelivered";
        $platforms = Platform::all();
        $status = Status::get();
        $payments = PaymentMethod::all();

        return view('admin.order.index', compact('orders', 'payments', 'status', 'platforms', 'customers', 'main_notifications', 'page'));
    }

    public function unpaid()
    {
        $orders = Order::where('status', 'unpaid')
            ->orWhere('status', 'rest')->latest()->paginate(25);
        $customers = Customer::all();
        // $trips = Trip::where('arrived', false)->get();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
        $page = "unpaid";
        $platforms = Platform::all();
        $status = Status::get();
        $payments = PaymentMethod::all();

        return view('admin.order.index', compact('orders', 'payments', 'status', 'platforms', 'customers', 'main_notifications', 'page'));
    }

    public function no_trip()
    {
        $orders = Order::where('trip_id', null)
            ->where('updated_at', '<', Carbon::now()->subHours(12))
            ->latest()->paginate(25);

        $customers = Customer::all();
        // $trips = Trip::where('arrived', false)->get();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
        $page = "no_trip";
        $platforms = Platform::all();
        $status = Status::get();
        $payments = PaymentMethod::all();

        return view('admin.order.index', compact('orders', 'payments', 'status', 'platforms', 'customers', 'main_notifications', 'page'));
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

    public function printDate(Request $request)
    {
        $query = $request->all();
        if (!$request->filled('startDate') && !$request->filled('endDate') && !$request->filled('status')) {
            return redirect()->route('order.index')->with('error', 'الرجاء تعبئة إحدى الخانات على الأقل');
        }
        $orders = Order::query();
        if ($request->filled('startDate') && !$request->filled('endDate')) {
            $orders->whereDate('created_at', '=', $request->input('startDate'));
        } elseif ($request->filled('startDate') && $request->filled('endDate')) {
            $orders->whereBetween('created_at', [$request->input('startDate'), $request->input('endDate')]);
        } elseif (!$request->filled('startDate') && $request->filled('endDate')) {
            $orders->whereDate('created_at', '=', $request->input('endDate'));
        }
        if ($request->filled('status')) {
            $orders->where('status', $request->input('status'));
        }
        $orders = $orders->get();
        if ($orders->count() == 0) {
            return redirect()->route('order.index')->with('error', 'لا يوجد اي سجلات بالبيانات المختارة');
        }
        return view('admin.order.printDate', compact('orders', 'query'));
    }

    public function search(Request $request)
    {
        if (!empty($request)) {
            $query = $request->all();
        }
        if (!request('customer_name') && !request('code') && !request('status') && !request('trip_code')) {
            return redirect()->route('order.index')->with('error', 'الرجاء تعبئة احدي الخانات علي الاقل');
        }
        $orders = Order::query(); // Start a new query
        // خانات النص (الزبون / رقم الطلب / الرحلة) بينهم "أو"، والحالة تفلتر فوقهم "و"
        if (request('customer_name') != null || request('code') != null || request('trip_code') != null) {
            $orders->where(function ($w) {
                if (request('customer_name') != null) {
                    $term = trim((string) request('customer_name'));
                    // لو المكتوب هو كود زبون بالضبط: نجيب هذا الزبون فقط وكل طلباته
                    $ids = Customer::where('code', $term)->pluck('id')->toArray();
                    if (empty($ids)) {
                        $ids = Customer::where('name', 'like', '%' . $term . '%')
                            ->orWhere('code', 'like', '%' . $term . '%')->pluck('id')->toArray();
                    }
                    $w->orWhereIn('customer_id', $ids ?: [0]);
                }
                if (request('code') != null) {
                    $term = trim((string) request('code'));
                    $w->orWhere('code', 'like', '%' . $term . '%');
                    // لو المكتوب كود زبون بالضبط: نضيف كل طلبات هذا الزبون
                    $custIds = Customer::where('code', $term)->pluck('id')->toArray();
                    if (!empty($custIds)) {
                        $w->orWhereIn('customer_id', $custIds);
                    }
                }
                if (request('trip_code') != null) {
                    $w->orWhere('trip_details', 'like', '%' . request('trip_code') . '%');
                }
            });
        }

        if (request('page') == "ready") {
            $orders->where('status', 'paid')
                ->whereHas('trip', function ($query) {
                    $query->where('arrived', true);
                });
        } elseif (request('page') == "unpaid") {
            $orders->where('status', 'unpaid');
        } elseif (request('status') != null) {
            $orders->where('status', request('status'));
        }

        session(['search_results_url' => url()->current() . (request()->getQueryString() ? '?' . request()->getQueryString() : '')]);

        $orders = $orders->latest()->paginate(25);
        $customers = Customer::all();
        $trips = Trip::where('arrived', false)->get();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
        $page = request('page');
        $platforms = Platform::all();
        $status = Status::get();
        $payments = PaymentMethod::all();

        return view('admin.order.index', compact('orders', 'payments', 'status', 'platforms', 'query', 'customers', 'trips', 'main_notifications', 'page'));
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
                'address' => 'nullable|string|max:50',
                'city' => 'required_if:customer_id,new|nullable|string|max:80',
                'area' => 'required_if:customer_id,new|nullable|string|max:120',
                'qty' => 'required|integer',
                'platform_id' => 'required|string',
                'bags_qty' => 'nullable|integer',
                'bags_num' => 'nullable|integer',
                'total_price' => 'required|numeric',
                // 'cost_price' => 'required|numeric',
                'paid_price' => 'required|numeric',
                'ship_price' => 'nullable|numeric',
                'kilo' => 'nullable|numeric',
                'note' => 'nullable|string|max:500',
                'payment_id' => 'required|exists:payment_methods,id',
            ],
            [
                'name.required_if' => 'يجب إدخال الاسم',
                'phone_1.required_if' => 'يجب اضافة رقم الهاتف',
                'city.required_if' => 'اكتب مدينة العميل', 'area.required_if' => 'اكتب منطقة العميل',
                'phone_1.unique' => 'رقم الهاتف الذي قمت بإدخاله موجود',
                'qty.required' => 'يجب إدخال الكمية',
                'platform_id.required' => 'يجب اختيار المنصة',
                // 'cost_price.required' => 'يجب إدخال اجمالي سعر التكلفة',
                'total_price.required' => 'يجب إدخال اجمالي سعر البضاعة',
                'paid_price.required' => 'يجب إدخال القيمة المدفوعة',
            ]
        );

        // ⚠ إيصال مكرر؟ (نفس العميل + نفس القيمة + نفس الكمية، اليوم ولا أمس)
        $dup = \App\Support\DupCheck::find(is_numeric($request->customer_id) ? (int) $request->customer_id : null, $request->customer_id === 'new' ? (string) $request->phone_1 : null, (float) $request->total_price, (int) $request->qty);
        if ($dup && !$request->boolean('confirm_dup')) {
            return redirect()->back()->withInput()->with('dup_warn', \App\Support\DupCheck::message($dup));
        }
        DB::beginTransaction();
        try {

            // Check if a new customer needs to be created
            if ($request->customer_id == 'new') {
                $customer = new Customer;
                $customer->name = request('name');
                $customer->phone_1 = request('phone_1');
                $customer->phone_2 = request('phone_2');
                $customer->address = request('address');
                if (\App\Support\Fast::hasColumn('customers', 'city')) {
                    $customer->city = trim((string) request('city')) ?: null;
                    $customer->area = trim((string) request('area')) ?: null;
                }
                if (request('code') && Customer::where('code', request('code'))->exists()) {
                    DB::rollback();
                    return redirect()->back()->withInput()->with('error', 'كود الزبون ' . request('code') . ' موجود مسبقآ — جرب: ' . implode('، ', \App\Http\Controllers\Sg\ClientsController::codeSuggestions((string) request('code'), preg_replace('/\D/', '', (string) request('phone_1')))));
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

            if ($request->paid_price == 0 || $request->total_price > $request->paid_price) {
                $status = "unpaid";
            } elseif (($request->total_price + $request->ship_price) == $request->paid_price) {
                $status = "paid_full";
            } elseif ($request->total_price <= $request->paid_price) {
                $status = "paid";
            } else {
                $status = "unpaid";
            }

            $code = \App\Services\OrderNumber::next(); // ترقيم موحد مع الواجهة الجديدة

            $order = new Order;
            $order->code = $code;
            $order->customer_id = $customer->id;

            if (request('items') && count(request('items')['name']) > 0) {
                $order->qty = array_sum(request('items')['quantity']);
                $order->total_price = array_sum(request('items')['price']);
            } else {
                $order->qty = request('qty');
                $order->total_price = request('total_price');
            }

            $order->status = $status;
            $order->trip_details = request('trip_details');
            $order->bags_qty = request('bags_qty');
            $order->order_type = request('order_type');
            $order->storage_num = request('storage_num');
            $order->payment_id = request('payment_id');

            if (request('currency') == "dinar") {
                $order->currency = request('currency');
                $order->currency_sign = "د.ل";
            } elseif (request('currency') == "dollar") {
                $order->currency = request('currency');
                $order->currency_sign = "$";
            } elseif (request('currency') == "euro") {
                $order->currency = request('currency');
                $order->currency_sign = "€";
            } else {
                $order->currency = request('currency');
                $order->currency_sign = "د.ل";
            }


            if (request('trip_id')) {
                $trip = Trip::find(request('trip_id'));
                if ($trip->arrived) {
                    $order->arrived = true;
                }
                $order->trip_id = request('trip_id');
            } else {
                $order->trip_id = request('trip_id');
            }
            $order->bags_num = request('bags_num');
            // $order->cost_price = request('cost_price');
            $order->paid_price = request('paid_price');
            $order->ship_price = request('ship_price');
            $order->kilo = request('kilo');
            $order->note = request('note');
            $order->platform_id = request('platform_id');
            $order->created_id = auth()->user()->id;
            $order->save();
            if (!empty($dup)) {
                \App\Support\DupCheck::logOverride($order, $dup);
            }

            if (request('items') && count(request('items')['name']) > 0) {
                $arr = [];
                foreach (request('items')['name'] as $index => $name) {
                    $arr[] = [
                        'order_id' => $order->id,
                        'name' => $name,
                        'quantity' => request('items')['quantity'][$index],
                        'price' => request('items')['price'][$index],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                OrderItem::insert($arr);
            }

            if (request('paid_price')) {
                $paymentAmount = $request->paid_price;

                $pay = new PaidHistory;
                $pay->customer_id = $order->customer_id;
                $pay->paid_price = $paymentAmount;
                $pay->order_id = $order->id;
                $pay->payment_id = request('payment_id');
                $pay->save();

                $paymentAmount = request('paid_price');
                $paymentId = request('payment_id');

                // get treasury row by currency + payment_id
                if ($order->currency == "dinar") {
                    $treasuryRow = Treasury::where('payment_id', $paymentId)->first();
                } elseif ($order->currency == "dollar") {
                    $treasuryRow = TreasuryDollar::where('payment_id', $paymentId)->first();
                } else {
                    $treasuryRow = TreasuryEuro::where('payment_id', $paymentId)->first();
                }

                // check if treasury exists
                if (!$treasuryRow) {
                    return redirect()->back()->with('error', 'لا يوجد خزينة لهذه الطريقة');
                }

                $before = $treasuryRow->total;
                $after = $before + $paymentAmount;

                // save history
                $history = new TreasuryHistory;
                $history->price = $paymentAmount;
                $history->price_before = $before;
                $history->price_after = $after;
                $history->status = "pay";
                $history->type = "order";
                $history->procedure_id = $order->id;
                $history->created_id = auth()->user()->id;
                $history->currency = $order->currency;
                $history->currency_sign = $order->currency_sign;
                $history->payment_id = $paymentId;
                $history->save();

                // update treasury
                $treasuryRow->total = $after;
                $treasuryRow->save();

                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "customer";
                $log->title = " مستحقات الزبون (" . $order->customer->name . ")";
                $log->log = " تم دفع مستحقات الزبون (" . $order->customer->name . ") بقية " . $paymentAmount . " DYL للطلب " . $order->code . " ";
                $log->save();
            }

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "order";
            $log->title = " طلب جديد (" . $code . ")";
            $log->log = " تم اضافة طلب جديد للزبون (" . $customer->name . ") بقيمة " . $request->total_price . " ";
            $log->save();
            $message = whatsappMessage::where('type', 'new')->first();
            // 📨 بيانات العميل من المنظومة: الاسم، الكود، رقم الطلب، الرابط ودخول الحساب (كل خانات القالب)
            $messageNew = \App\Services\MessageService::render((string) optional($message)->message, \App\Services\MessageService::orderVars($order->fresh(['customer']) ?: $order));
            DB::commit();

            //  رسالة واتساب للعميل بعد إضافة الطلب بنجاح: الإيصال PDF + الرسالة تحته
            $status = \App\Support\OrderPdf::sendNew($order->fresh(['customer', 'items']));

            if ($status) {
                return redirect()->back()->with('success', 'تــمــت إضــافــة الــطــلــب بــنــجــاح مـع إرســال رســالــة واتســاب للـزبـون');
            } else {
                if ($customer->phone_2) {
                    // $message = "*رسالة إضافة الطلب*\n\nاهلا عميلنا العزيز (" . $customer->name . ") \nرقم الكود الخاص بك: (" . $customer->code . ")\n\nتم إضافة الطلب الخاص بك في منظومة تتبع الطلبات \nلأي استفسار يمكنك الدخول عبر الرابط \n\n https://shopandgo.ly \n\n*اسم المستخدم:* رقم هاتفك\n*كلمة المرور:* رقم هاتفك\n\nشكرآ لاختياركم شركتنا ونتمنى لكم تجربة ممتعة ";
                    // send to all available phones
                    $phones = array_filter([$customer->phone_2]);
                    $send2 = Bus::dispatchSync(new SendWhatsAppMessageJob($phones, $messageNew));
                    $status = session('whatsapp_status', false);
                    if ($status) {
                        return redirect()->back()->with('success', 'تــمــت إضــافــة الــطــلــب بــنــجــاح مـع إرســال رســالــة واتســاب للـزبـون على الرقم الثاني');
                    }
                }
                return redirect()->back()->with('success', 'تــمــت إضــافــة الــطــلــب بــنــجــاح');
            }
            // all good
        } catch (\Exception $e) {
            report($e); // كان dd($e) يطلع كود للمستخدم ويمنع الرجوع
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }


    public function store2(Request $request)
    {
        request()->validate(
            [
                'customer_id' => 'nullable',
                'name' => 'required_if:customer_id,new',
                'phone_1' => 'required_if:customer_id,new|max:20|unique:customers,phone_1',
                'phone_2' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:50',
                'qty' => 'required|integer',
                'platform_id' => 'required|string',
                'bags_qty' => 'nullable|integer',
                'bags_num' => 'nullable|integer',
                // 'cost_price' => 'required|numeric',
                'paid_price' => 'required|numeric',
                'ship_price' => 'nullable|numeric',
                'kilo' => 'nullable|numeric',
                'note' => 'nullable|string|max:500',
                'payment_id' => 'required|exists:payment_methods,id',
            ],
            [
                'name.required_if' => 'يجب إدخال الاسم',
                'phone_1.required_if' => 'يجب اضافة رقم الهاتف',
                'phone_1.unique' => 'رقم الهاتف الذي قمت بإدخاله موجود',
                'qty.required' => 'يجب إدخال الكمية',
                'platform_id.required' => 'يجب اختيار المنصة',
                // 'cost_price.required' => 'يجب إدخال اجمالي سعر التكلفة',
                'total_price.required' => 'يجب إدخال اجمالي سعر البضاعة',
                'paid_price.required' => 'يجب إدخال القيمة المدفوعة',
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
                    return redirect()->back()->withInput()->with('error', 'كود الزبون ' . request('code') . ' موجود مسبقآ — جرب: ' . implode('، ', \App\Http\Controllers\Sg\ClientsController::codeSuggestions((string) request('code'), preg_replace('/\D/', '', (string) request('phone_1')))));
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

            if ($request->paid_price == 0 || $request->total_price > $request->paid_price) {
                $status = "unpaid";
            } elseif (($request->total_price + $request->ship_price) == $request->paid_price) {
                $status = "paid_full";
            } elseif ($request->total_price <= $request->paid_price) {
                $status = "paid";
            } else {
                $status = "unpaid";
            }

            $code = \App\Services\OrderNumber::next(); // ترقيم موحد مع الواجهة الجديدة

            $order = new Order;
            $order->code = $code;
            $order->customer_id = $customer->id;

            if (request('items') && count(request('items')['name']) > 0) {
                $order->qty = array_sum(request('items')['quantity']);
                $order->total_price = array_sum(request('items')['price']);
            } else {
                $order->qty = request('qty');
                $order->total_price = request('total_price');
            }

            $order->status = $status;
            $order->trip_details = request('trip_details');
            $order->bags_qty = request('bags_qty');
            $order->order_type = request('order_type');
            $order->storage_num = request('storage_num');
            $order->payment_id = request('payment_id');

            if (request('currency') == "dinar") {
                $order->currency = request('currency');
                $order->currency_sign = "د.ل";
            } elseif (request('currency') == "dollar") {
                $order->currency = request('currency');
                $order->currency_sign = "$";
            } elseif (request('currency') == "euro") {
                $order->currency = request('currency');
                $order->currency_sign = "€";
            } else {
                $order->currency = request('currency');
                $order->currency_sign = "د.ل";
            }


            if (request('trip_id')) {
                $trip = Trip::find(request('trip_id'));
                if ($trip->arrived) {
                    $order->arrived = true;
                }
                $order->trip_id = request('trip_id');
            } else {
                $order->trip_id = request('trip_id');
            }
            $order->bags_num = request('bags_num');
            // $order->cost_price = request('cost_price');
            $order->paid_price = request('paid_price');
            $order->ship_price = request('ship_price');
            $order->kilo = request('kilo');
            $order->note = request('note');
            $order->platform_id = request('platform_id');
            $order->created_id = auth()->user()->id;
            $order->save();

            if (request('items') && count(request('items')['name']) > 0) {
                $arr = [];
                foreach (request('items')['name'] as $index => $name) {
                    $arr[] = [
                        'order_id' => $order->id,
                        'name' => $name,
                        'quantity' => request('items')['quantity'][$index],
                        'price' => request('items')['price'][$index],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                OrderItem::insert($arr);
            }

            if (request('paid_price')) {
                $paymentAmount = $request->paid_price;

                $pay = new PaidHistory;
                $pay->customer_id = $order->customer_id;
                $pay->paid_price = $paymentAmount;
                $pay->order_id = $order->id;
                $pay->payment_id = request('payment_id');
                $pay->save();

                $paymentAmount = request('paid_price');
                $paymentId = request('payment_id');

                // get treasury row by currency + payment_id
                if ($order->currency == "dinar") {
                    $treasuryRow = Treasury::where('payment_id', $paymentId)->first();
                } elseif ($order->currency == "dollar") {
                    $treasuryRow = TreasuryDollar::where('payment_id', $paymentId)->first();
                } else {
                    $treasuryRow = TreasuryEuro::where('payment_id', $paymentId)->first();
                }

                // check if treasury exists
                if (!$treasuryRow) {
                    return redirect()->back()->with('error', 'لا يوجد خزينة لهذه الطريقة');
                }

                $before = $treasuryRow->total;
                $after = $before + $paymentAmount;

                // save history
                $history = new TreasuryHistory;
                $history->price = $paymentAmount;
                $history->price_before = $before;
                $history->price_after = $after;
                $history->status = "pay";
                $history->type = "order";
                $history->procedure_id = $order->id;
                $history->created_id = auth()->user()->id;
                $history->currency = $order->currency;
                $history->currency_sign = $order->currency_sign;
                $history->payment_id = $paymentId;
                $history->save();

                // update treasury
                $treasuryRow->total = $after;
                $treasuryRow->save();

                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "customer";
                $log->title = " مستحقات الزبون (" . $order->customer->name . ")";
                $log->log = " تم دفع مستحقات الزبون (" . $order->customer->name . ") بقية " . $paymentAmount . " DYL للطلب " . $order->code . " ";
                $log->save();
            }

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "order";
            $log->title = " طلب جديد (" . $code . ")";
            $log->log = " تم اضافة طلب جديد للزبون (" . $customer->name . ") بقيمة " . $request->total_price . " ";
            $log->save();
            $message = whatsappMessage::where('type', 'new')->first();
            // 📨 بيانات العميل من المنظومة: الاسم، الكود، رقم الطلب، الرابط ودخول الحساب (كل خانات القالب)
            $messageNew = \App\Services\MessageService::render((string) optional($message)->message, \App\Services\MessageService::orderVars($order->fresh(['customer']) ?: $order));
            DB::commit();

            //  رسالة واتساب للعميل بعد إضافة الطلب بنجاح: الإيصال PDF + الرسالة تحته
            $status = \App\Support\OrderPdf::sendNew($order->fresh(['customer', 'items']));

            if ($status) {
                return redirect()->back()->with('success', 'تــمــت إضــافــة الــطــلــب بــنــجــاح مـع إرســال رســالــة واتســاب للـزبـون');
            } else {
                if ($customer->phone_2) {
                    // $message = "*رسالة إضافة الطلب*\n\nاهلا عميلنا العزيز (" . $customer->name . ") \nرقم الكود الخاص بك: (" . $customer->code . ")\n\nتم إضافة الطلب الخاص بك في منظومة تتبع الطلبات \nلأي استفسار يمكنك الدخول عبر الرابط \n\n https://shopandgo.ly \n\n*اسم المستخدم:* رقم هاتفك\n*كلمة المرور:* رقم هاتفك\n\nشكرآ لاختياركم شركتنا ونتمنى لكم تجربة ممتعة ";
                    // send to all available phones
                    $phones = array_filter([$customer->phone_2]);
                    $send2 = Bus::dispatchSync(new SendWhatsAppMessageJob($phones, $messageNew));
                    $status = session('whatsapp_status', false);
                    if ($status) {
                        return redirect()->back()->with('success', 'تــمــت إضــافــة الــطــلــب بــنــجــاح مـع إرســال رســالــة واتســاب للـزبـون على الرقم الثاني');
                    }
                }
                return redirect()->back()->with('success', 'تــمــت إضــافــة الــطــلــب بــنــجــاح');
            }
            // all good
        } catch (\Exception $e) {
            report($e); // كان dd($e) يطلع كود للمستخدم ويمنع الرجوع
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

    function generateUniqueCode2($length = 6)
    {
        $characters = '0123456789';
        $charactersLength = strlen($characters);
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[random_int(0, $charactersLength - 1)];
            }
            // Check if the code already exists in the database
            $exists = $this->checkCodeExistsInDatabase2($code);
        } while ($exists);
        return $code;
    }
    function checkCodeExistsInDatabase2($code)
    {
        return Order::where('code', $code)->exists();
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
        $order = Order::find($id);
        $trips = Trip::where('arrived', false)->get();
        $main_notifications = Notif_orgs::where('user_id', auth()->user()->id)
            ->latest()
            ->take(5)
            ->get();
        session(['search_results_url' => url()->previous() . (request()->getQueryString() ? '?' . request()->getQueryString() : '')]);
        $platforms = Platform::all();
        $payments = PaymentMethod::all();
        return view('admin.order.edit', compact('order', 'platforms', 'trips', 'main_notifications', 'payments'));
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
                'trip_id' => 'nullable|exists:trips,id',
                'qty' => 'required|integer',
                'bags_qty' => 'nullable|integer',
                'bags_num' => 'nullable|integer',
                'total_price' => 'required|numeric',
                // 'cost_price' => 'required|numeric',
                'ship_price' => 'nullable|numeric',
                'kilo' => 'nullable|numeric',
                'note' => 'nullable|string|max:500',
                'platform_id' => 'required|integer',

            ],
            [
                'qty.required' => 'يجب إدخال الكمية',
                'total_price.required' => 'يجب إدخال اجمالي سعر البضاعة',
                'platform_id.required' => 'يجب اختيار المنصة',
                // 'cost_price.required' => 'يجب إدخال اجمالي سعر التكلفة',
            ]
        );

        DB::beginTransaction();
        try {

            // if ($request->paid_price == 0 || $request->total_price > $request->paid_price) {
            //     $status = "unpaid";
            // } elseif (($request->total_price + $request->ship_price) == $request->paid_price) {
            //     $status = "paid_full";
            // } elseif ($request->total_price <= $request->paid_price) {
            //     $status = "paid";
            // } else {
            //     $status = "unpaid";
            // }
            $order = Order::find($id);
            if (request('items') && count(request('items')['name']) > 0) {
                $order->qty = array_sum(request('items')['quantity']);
                $order->total_price = array_sum(request('items')['price']);
            } else {
                $order->qty = request('qty');
                $order->total_price = request('total_price');
            }

            // $order->status = $status;

            $order->order_type = request('order_type');
            $order->storage_num = request('storage_num');

            $order->bags_qty = request('bags_qty');
            $order->bags_num = request('bags_num');
            // $order->cost_price = request('cost_price');
            // if (request('paid_price') && $order->paid_price != request('paid_price')) {
            //     if ($order->paid_price > request('paid_price')) {
            //         $onlyPaid = $order->paid_price - request('paid_price');
            //     } elseif ($order->paid_price) {
            //         $onlyPaid = request('paid_price') - $order->paid_price;
            //     } else {
            //         $onlyPaid = request('paid_price');
            //     }
            //     $treasury = Treasury::first();
            //     $history = new TreasuryHistory;
            //     $history->price = $onlyPaid;
            //     $history->price_before = $treasury->total;
            //     $history->price_after = $treasury->total + $onlyPaid;
            //     $history->status = "pay";
            //     $history->type = "order";
            //     $history->procedure_id = $order->id;
            //     $history->created_id = auth()->user()->id;
            //     $history->is_update = true;
            //     $history->save();
            //     $treasury->total += $onlyPaid;
            //     $treasury->update();
            // }
            // $order->paid_price = request('paid_price');
            $order->ship_price = request('ship_price');
            $order->kilo = request('kilo');
            $order->note = request('note');
            $order->platform_id = request('platform_id');
            $order->trip_details = request('trip_details');

            if (request('currency') == "dinar") {
                $order->currency = request('currency');
                $order->currency_sign = "د.ل";
            } elseif (request('currency') == "dollar") {
                $order->currency = request('currency');
                $order->currency_sign = "$";
            } elseif (request('currency') == "euro") {
                $order->currency = request('currency');
                $order->currency_sign = "€";
            } else {
                $order->currency = request('currency');
                $order->currency_sign = "د.ل";
            }

            if (request('trip_id')) {
                $trip = Trip::find(request('trip_id'));
                if ($trip->arrived) {
                    $order->arrived = true;
                    $order->arrived_at = $trip->arrived_at;
                }
                $order->trip_id = request('trip_id');
            } else {
                $order->trip_id = request('trip_id');
            }
            $order->update();

            if (request('items') && count(request('items')['name']) > 0) {
                OrderItem::where('order_id', $order->id)->delete();
                $arr = [];
                foreach (request('items')['name'] as $index => $name) {
                    $arr[] = [
                        'order_id' => $order->id,
                        'name' => $name,
                        'quantity' => request('items')['quantity'][$index],
                        'price' => request('items')['price'][$index],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                OrderItem::insert($arr);
            } else {
                OrderItem::where('order_id', $order->id)->delete();
            }

            // 💵 تعديل المدفوع من صفحة التعديل: الزيادة دفعة جديدة · النقص يتخصم من آخر الدفعات (الخزينة تتعدل بالفرق)
            if ($request->filled('paid_new') && is_numeric($request->paid_new)) {
                $order = $order->fresh();
                $diff = round((float) $request->paid_new - (float) $order->paid_price, 2);
                if ($diff > 0.009) {
                    $pid = (int) ($request->paid_method ?: \App\Services\AppSettings::cashPaymentId());
                    \App\Services\OrderPaymentService::pay($order, $diff, $pid, 'تعديل المدفوع');
                } elseif ($diff < -0.009) {
                    $left = -$diff;
                    foreach (\App\Models\PaidHistory::where('order_id', $order->id)->latest('id')->get() as $p) {
                        if ($left <= 0.009) {
                            break;
                        }
                        $cut = round(min($left, (float) $p->paid_price), 2);
                        \App\Services\OrderPaymentService::edit($p, round((float) $p->paid_price - $cut, 2), (int) $p->payment_id);
                        $left -= $cut;
                    }
                    if ($left > 0.009) { // مدفوع قديم بدون سجل دفعات
                        $order = $order->fresh();
                        $order->paid_price = max(0, round((float) $order->paid_price - $left, 2));
                        $order->refreshPaymentStatus();
                        $order->save();
                    }
                }
            }

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "order";
            $log->title = " تعديل الطلب (" . $order->code . ")";
            $log->log = " تم تعديل بيانات الطلب (" . $order->code . ")";
            $log->save();

            DB::commit();
            return redirect(session('search_results_url', route('order.index')))->with('success', 'تــم تـعـديـل بـيـانـات الــطــلــب بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }


    public function payRest(Request $request, $id)
    {
        $request->validate(
            [
                'paid_price' => 'required|numeric',
                'payment_id' => 'required|exists:payment_methods,id',
            ],
            [
                'paid_price.required' => 'يجب إدخال القيمة المسددة من الزبون',
                'payment_id.required' => 'يجب اختيار طريقة الدفع',
            ]
        );

        DB::beginTransaction();
        try {

            $order = Order::find($id);
            if ($order->ship_price && ($order->total_price + $order->ship_price) <= $order->paid_price) {
                DB::rollback();
                return redirect()->back()->with('error', 'لقد تم دفع القيمة المستحقة من الطلب مسبقآ الرجاء التحقق');
            } elseif (!$order->ship_price && $order->total_price == $order->paid_price) {
                DB::rollback();
                return redirect()->back()->with('error', 'لقد تم دفع القيمة المستحقة من الطلب مسبقآ بدون سعر الشحن الرجاء التحقق');
            }

            if ($order) {
                $paymentAmount = $request->paid_price;
                $order->paid_price += $paymentAmount;
                if ($order->ship_price && ($order->total_price + $order->ship_price) <= $order->paid_price) {
                    $order->status = "paid_full";
                } elseif ($order->total_price <= $order->paid_price) {
                    $order->status = "paid";
                } elseif ($order->total_price > $order->paid_price) {
                    $order->status = "unpaid";
                }

                if (request('discount') && !$order->discount) {
                    $order->discount = request('discount');
                    $order->discount_reason = request('discount_reason');
                    $order->discount_reason_note = request('discount_reason_note');
                    $description = PurchaseDescription::where('name', 'تخفيضات')->first();
                    if (!$description) {
                        DB::rollback();
                        return redirect()->back()->with('error', 'الرجاء اضافة التخفيضات في شاشة اعدادات اوصاف المصروفات وان يكون نفس الاسم (تخفيضات)');
                    }
                    $purchase = new Purchase;
                    $purchase->desc_id = $description->id;
                    $purchase->cost = request('discount');
                    $purchase->date = now();
                    $purchase->note = ' سبب التخفيض ' . request('discount_reason') . " - " . request('discount_reason_note') . ' للطلب رقم ' . $order->code;
                    $purchase->created_id = auth()->user()->id;
                    $purchase->save();
                }
                if (request('discount') && !request('discount_reason')) {
                    DB::rollback();
                    return redirect()->back()->with('error', 'الرجاء اختيار سبب التخفيض عند كتابة قيمة التخفيض');
                }

                $order->update();
                $pay = new PaidHistory;
                $pay->customer_id = $order->customer_id;
                $pay->paid_price = $paymentAmount;
                $pay->order_id = $order->id;
                $pay->payment_id = request('payment_id');
                $pay->save();
                $paymentId = request('payment_id');

                // get treasury row based on currency + payment_id
                if ($order->currency == "dinar") {
                    $treasuryRow = Treasury::where('payment_id', $paymentId)->first();
                } elseif ($order->currency == "dollar") {
                    $treasuryRow = TreasuryDollar::where('payment_id', $paymentId)->first();
                } else {
                    $treasuryRow = TreasuryEuro::where('payment_id', $paymentId)->first();
                }

                // check if treasury exists
                if (!$treasuryRow) {
                    return redirect()->back()->with('error', 'لا يوجد خزينة لهذه الطريقة');
                }

                $before = $treasuryRow->total;
                $after  = $before + $paymentAmount;

                // save treasury history
                $history = new TreasuryHistory;
                $history->price = $paymentAmount;
                $history->price_before = $before;
                $history->price_after = $after;
                $history->status = "pay";
                $history->type = "order";
                $history->procedure_id = $order->id;
                $history->created_id = auth()->user()->id;
                $history->currency = $order->currency;
                $history->currency_sign = $order->currency_sign;
                $history->payment_id = $paymentId;
                $history->save();

                // update treasury total
                $treasuryRow->total = $after;
                $treasuryRow->save();

                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "customer";
                $log->title = " مستحقات الزبون (" . $order->customer->name . ")";
                $log->log = " تم دفع مستحقات الزبون (" . $order->customer->name . ") بقية " . $paymentAmount . " DYL للطلب " . $order->code . " ";
                $log->save();
            } else {
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }

            DB::commit();
            return redirect()->back()->with('success', 'تــم دفــع مـسـتـحـقـات الـزبـون للـطـلـب بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    public function print($id)
    {
        $order = Order::find($id);
        $invoice = Invoice::first();
        return view('admin.order.print', ['order' => $order, 'invoice' => $invoice]);
    }

    public function ready_print(Request $request)
    {
        if (request('from') && request('to')) {
            $orders = Order::where([['arrived', true], ['delivered', false]])
                ->whereBetween('arrived_at', [request('from'), request('to')])
                ->latest()
                ->get();
        } elseif (request('from')) {
            $orders = Order::where([['arrived', true], ['delivered', false], ['arrived_at', request('from')]])
                ->latest()
                ->get();
        } else {
            $orders = Order::where([['arrived', true], ['delivered', false]])
                ->latest()
                ->get();
        }
        $from = request('from');
        $to = request('to');
        return view('admin.order.print-orders', ['orders' => $orders, 'from' => $from, 'to' => $to]);
    }

    public function delivered(Request $request, $id)
    {
        $request->validate(
            [
                'bags_num' => 'required|numeric',
                'bags_qty' => 'required|numeric',
            ],
            [
                'bags_num.required' => 'يجب إدخال عدد الاكياس',
                'bags_qty.required' => 'يجب إدخال الكمية في الاكياس',
            ]
        );

        DB::beginTransaction();
        try {
            $order = Order::find($id);

            if ($order->delivered == true) {
                DB::rollback();
                return redirect()->back()->with('error', 'لقد تم تسليم الطلبية مسبقآ الرجاء التحقق');
            } elseif ($order->status == "canceld") {
                DB::rollback();
                return redirect()->back()->with('error', 'لقد تم الغاء الطلبية مسبقآ الرجاء التحقق');
            }

            if ($order) {
                $order->bags_num = $request->bags_num;
                $order->bags_qty = $request->bags_qty;
                $order->delivered = true;
                $order->delivery_type = $request->delivery_type;
                $order->delivery_name = $request->delivery_name;
                $order->delivered_at = now();
                if (request('paid_price')) {
                    if ($order->ship_price && ($order->total_price + $order->ship_price) > $order->paid_price || !$order->ship_price && $order->total_price > $order->paid_price) {
                        $paymentAmount = $request->paid_price;
                        $order->paid_price += $paymentAmount;
                        if ($order->ship_price && ($order->total_price + $order->ship_price) <= $order->paid_price) {
                            $order->status = "paid_full";
                        } elseif ($order->total_price <= $order->paid_price) {
                            $order->status = "paid";
                        } elseif ($order->total_price > $order->paid_price) {
                            $order->status = "unpaid";
                        }
                        $pay = new PaidHistory;
                        $pay->customer_id = $order->customer_id;
                        $pay->paid_price = $paymentAmount;
                        $pay->order_id = $order->id;
                        $pay->payment_id = request('payment_id');
                        $pay->save();
                        $paymentId = request('payment_id');

                        // get treasury row by currency + payment_id
                        if ($order->currency == "dinar") {
                            $treasuryRow = Treasury::where('payment_id', $paymentId)->first();
                        } elseif ($order->currency == "dollar") {
                            $treasuryRow = TreasuryDollar::where('payment_id', $paymentId)->first();
                        } else {
                            $treasuryRow = TreasuryEuro::where('payment_id', $paymentId)->first();
                        }

                        // check treasury exists
                        if (!$treasuryRow) {
                            return redirect()->back()->with('error', 'لا يوجد خزينة لهذه الطريقة');
                        }

                        $before = $treasuryRow->total;
                        $after  = $before + $paymentAmount;

                        // save history
                        $history = new TreasuryHistory;
                        $history->price = $paymentAmount;
                        $history->price_before = $before;
                        $history->price_after = $after;
                        $history->status = "pay";
                        $history->type = "order";
                        $history->procedure_id = $order->id;
                        $history->created_id = auth()->user()->id;
                        $history->currency = $order->currency;
                        $history->currency_sign = $order->currency_sign;
                        $history->payment_id = $paymentId;
                        $history->save();

                        // update treasury
                        $treasuryRow->total = $after;
                        $treasuryRow->save();
                        $log = new Log;
                        $log->user_id = auth()->user()->id;
                        $log->type = "customer";
                        $log->title = " مستحقات الزبون (" . $order->customer->name . ")";
                        $log->log = " تم دفع مستحقات الزبون (" . $order->customer->name . ") بقية " . $paymentAmount . " DYL للطلب " . $order->code . " ";
                        $log->save();
                    }
                }


                if (request('discount') && !$order->discount) {
                    $order->discount = request('discount');
                    $order->discount_reason = request('discount_reason');
                    $order->discount_reason_note = request('discount_reason_note');

                    $description = PurchaseDescription::where('name', 'تخفيضات')->first();
                    if (!$description) {
                        DB::rollback();
                        return redirect()->back()->with('error', 'الرجاء اضافة التخفيضات في شاشة اعدادات اوصاف المصروفات وان يكون نفس الاسم (تخفيضات)');
                    }
                    $purchase = new Purchase;
                    $purchase->desc_id = $description->id;
                    $purchase->cost = request('discount');
                    $purchase->date = now();
                    $purchase->note = ' سبب التخفيض ' . request('discount_reason') . " - " . request('discount_reason_note') . ' للطلب رقم ' . $order->code;
                    $purchase->created_id = auth()->user()->id;
                    $purchase->save();
                }
                if (request('discount') && !request('discount_reason')) {
                    DB::rollback();
                    return redirect()->back()->with('error', 'الرجاء اختيار سبب التخفيض عند كتابة قيمة التخفيض');
                }

                $order->update();
                $log = new Log;
                $log->user_id = auth()->user()->id;
                $log->type = "order";
                $log->title = " تسليم طلب للزبون (" . $order->customer->name . ")";
                $log->log = " تم تسليم الطلب (" . $order->code . ") للزبون " . $order->customer->name . " ";
                $log->save();
            } else {
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }

            DB::commit();
            return redirect()->back()->with('success', 'تــم دفــع مـسـتـحـقـات الـزبـون للـطـلـب بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    public function arrived(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $order = Order::find($id);
            if ($order) {
                $order->arrived = true;
                $order->arrived_at = now()->format('Y-m-d');
                $order->update();
            } else {
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }
            DB::commit();
            return redirect()->back()->with('success', 'تــم وصـــول الـطـلـبـيـة بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }


    public function trip_details(Request $request, $id)
    {
        DB::beginTransaction();

        try {

            $order = Order::find($id);

            if (!$order) {
                DB::rollBack();

                return redirect()->back()->with(
                    'error',
                    'الطلب غير موجود'
                );
            }

            $oldTripDetails = $order->trip_details;
            $oldTripStatus = $order->trip_status;

            $ordersSent = 0;
            $whatsappFailedList = [];

            /*
        |--------------------------------------------------------------------------
        | Update Order
        |--------------------------------------------------------------------------
        */

            if ($order->trip_status != request('trip_status')) {

                if (request('trip_details')) {

                    $order->trip_details = request('trip_details');
                    $order->trip_status = request('trip_status');
                    $order->trip_note = request('trip_note');

                    $order->update();
                } else {

                    $order->trip_details = null;
                    $order->trip_status = null;
                    $order->trip_note = null;

                    $order->update();
                }

                if ($order->trip_details) {

                    Order::where('trip_details', $order->trip_details)
                        ->where('id', '!=', $order->id)
                        ->update([
                            'trip_status' => $order->trip_status,
                            'trip_note'   => $order->trip_note
                        ]);

                    $orders = Order::where(
                        'trip_details',
                        $order->trip_details
                    )->get();
                } else {

                    $orders = collect();
                }
            } elseif ($order && request('trip_details')) {

                if (request('trip_details')) {

                    $order->trip_details = request('trip_details');
                    $order->trip_status = request('trip_status');
                    $order->trip_note = request('trip_note');

                    $order->update();
                } else {

                    $order->trip_details = null;
                    $order->trip_status = null;
                    $order->trip_note = null;

                    $order->update();
                }

                if ($order->trip_details) {

                    Order::where('trip_details', $order->trip_details)
                        ->where('id', '!=', $order->id)
                        ->update([
                            'trip_status' => $order->trip_status,
                            'trip_note'   => $order->trip_note
                        ]);

                    $orders = Order::where(
                        'trip_details',
                        $order->trip_details
                    )->get();
                } else {

                    $orders = collect();
                }
            } else {

                DB::rollBack();

                return redirect()->back()->with(
                    'error',
                    'للاسف حدث خطأ ما الرجاء اعادة المحاولة'
                );
            }


            /*
        |--------------------------------------------------------------------------
        | WhatsApp Messages
        |--------------------------------------------------------------------------
        */

            $messageChange1 = whatsappMessage::where('type', 'change')->first();
            $messageReady1 = whatsappMessage::where('type', 'ready')->first();


            /*
        |--------------------------------------------------------------------------
        | Commit Database Changes
        |--------------------------------------------------------------------------
        */

            DB::commit();


            /*
        |--------------------------------------------------------------------------
        | Send WhatsApp Messages
        |--------------------------------------------------------------------------
        */

            if (
                $order->trip_status != $oldTripStatus &&
                $order->trip_status &&
                $order->trip_details
            ) {

                foreach ($orders as $order1) {

                    /*
                |--------------------------------------------------------------------------
                | IMPORTANT:
                | Everything related to ONE customer is inside its own try/catch.
                |
                | If customer #2 fails:
                | customer #3, #4, #5... will still be processed.
                |--------------------------------------------------------------------------
                */

                    try {

                        /*
                    |--------------------------------------------------------------------------
                    | Check Customer
                    |--------------------------------------------------------------------------
                    */

                        if (!$order1->customer) {

                            $whatsappFailedList[] = [
                                'order' => $order1->code,
                                'phone' => 'غير موجود',
                                'reason' => 'الزبون غير موجود'
                            ];

                            continue;
                        }


                        /*
                    |--------------------------------------------------------------------------
                    | Check Phone
                    |--------------------------------------------------------------------------
                    */

                        if (!$order1->customer->phone_1) {

                            $whatsappFailedList[] = [
                                'order' => $order1->code,
                                'phone' => 'غير موجود',
                                'reason' => 'رقم الهاتف غير موجود'
                            ];

                            continue;
                        }


                        /*
                    |--------------------------------------------------------------------------
                    | Calculate Order Information
                    |--------------------------------------------------------------------------
                    */

                        $total = $order1->total_price
                            + $order1->ship_price
                            - $order1->paid_price;

                        $shipPrice = $order1->ship_price == 0
                            ? 'لم تحدد بعد'
                            : $order1->ship_price;


                        /*
                    |--------------------------------------------------------------------------
                    | Create WhatsApp Message
                    |--------------------------------------------------------------------------
                    */

                        if ($order1->trip_status == "جاهز للاستلام") {

                            $message = str_replace(
                                [
                                    '{{$order1->customer->name}}',
                                    '{{$order1->customer->code}}',
                                    '{{$order1->trip_status}}',
                                    '{{$shipPrice}}',
                                    '{{$total}}',
                                    '{{$currency}}',
                                    '{{$order1->code}}',
                                    '{{$order1->trip_details}}',
                                    '{{$app_url}}'
                                ],
                                [
                                    $order1->customer->name,
                                    $order1->customer->code,
                                    $order1->trip_status,
                                    $shipPrice,
                                    $total,
                                    $order1->currency_sign,
                                    $order1->code,
                                    $order1->trip_details,
                                    env('APP_URL')
                                ],
                                $messageReady1->message
                            );
                        } else {

                            $message = str_replace(
                                [
                                    '{{$order1->customer->name}}',
                                    '{{$order1->customer->code}}',
                                    '{{$order1->trip_status}}',
                                    '{{$order1->code}}',
                                    '{{$order1->trip_details}}',
                                    '{{$app_url}}'
                                ],
                                [
                                    $order1->customer->name,
                                    $order1->customer->code,
                                    $order1->trip_status,
                                    $order1->code,
                                    $order1->trip_details,
                                    env('APP_URL')
                                ],
                                $messageChange1->message
                            );
                        }


                        /*
                    |--------------------------------------------------------------------------
                    | Normalize Phone Number
                    |--------------------------------------------------------------------------
                    */

                        $originalPhone = $order1->customer->phone_1;

                        // Remove all non-numeric characters
                        $phone = preg_replace('/\D/', '', $originalPhone);

                        // Handle numbers beginning with 00
                        if (str_starts_with($phone, '00')) {
                            $phone = substr($phone, 2);
                        }

                        // Handle Libyan local numbers beginning with 0
                        if (str_starts_with($phone, '0')) {
                            $phone = '218' . substr($phone, 1);
                        }

                        // Add Libya country code if missing
                        if (!str_starts_with($phone, '218')) {
                            $phone = '218' . $phone;
                        }


                        /*
                    |--------------------------------------------------------------------------
                    | Validate Phone Number
                    |--------------------------------------------------------------------------
                    |
                    | Libya international number:
                    |
                    | 218 + 9 digits = 12 digits
                    |
                    */

                        if (!preg_match('/^218\d{9}$/', $phone)) {

                            $whatsappFailedList[] = [
                                'order' => $order1->code,
                                'phone' => $originalPhone,
                                'reason' => 'رقم الهاتف غير صحيح: ' . $phone
                            ];

                            // Skip this customer
                            continue;
                        }


                        /*
                    |--------------------------------------------------------------------------
                    | Send WhatsApp
                    |--------------------------------------------------------------------------
                    */

                        $response = app(WhatsAppService::class)->send([
                            'phone' => [$phone],
                            'message' => $message,
                        ], attempts: 3);


                        /*
                    |--------------------------------------------------------------------------
                    | Check Response
                    |--------------------------------------------------------------------------
                    */

                        if ($response->successful()) {

                            $ordersSent++;
                        } else {

                            /*
                        |--------------------------------------------------------------------------
                        | WhatsApp returned 400 / 404 / 500 etc.
                        |
                        | Don't stop the loop.
                        |--------------------------------------------------------------------------
                        */

                            $errorMessage = $response->json('error')
                                ?? $response->body();

                            $whatsappFailedList[] = [
                                'order' => $order1->code,
                                'phone' => $phone,
                                'reason' => $errorMessage
                            ];

                            \Log::error(
                                'WhatsApp API returned an error',
                                [
                                    'order' => $order1->code,
                                    'phone' => $phone,
                                    'status' => $response->status(),
                                    'response' => $response->body(),
                                ]
                            );

                            // Continue with next customer
                            continue;
                        }
                    } catch (\Illuminate\Http\Client\ConnectionException $e) {

                        /*
                    |--------------------------------------------------------------------------
                    | Connection error for ONE customer
                    |--------------------------------------------------------------------------
                    */

                        \Log::error(
                            'WhatsApp API connection error',
                            [
                                'order' => $order1->code,
                                'phone' => $order1->customer->phone_1 ?? null,
                                'error' => $e->getMessage(),
                            ]
                        );

                        $whatsappFailedList[] = [
                            'order' => $order1->code,
                            'phone' => $order1->customer->phone_1 ?? 'غير موجود',
                            'reason' => 'تعذر الاتصال بخادم الواتساب'
                        ];

                        // IMPORTANT:
                        // Do not return here.
                        // Continue to the next customer.
                        continue;
                    } catch (\Throwable $e) {

                        /*
                    |--------------------------------------------------------------------------
                    | Any other error for ONE customer
                    |--------------------------------------------------------------------------
                    */

                        \Log::error(
                            'WhatsApp unexpected error',
                            [
                                'order' => $order1->code,
                                'phone' => $order1->customer->phone_1 ?? null,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]
                        );

                        $whatsappFailedList[] = [
                            'order' => $order1->code,
                            'phone' => $order1->customer->phone_1 ?? 'غير موجود',
                            'reason' => $e->getMessage()
                        ];

                        // Continue to next customer
                        continue;
                    }
                }
            }


            /*
        |--------------------------------------------------------------------------
        | Final Response
        |--------------------------------------------------------------------------
        */

            $totalOrders = $orders->count();


            if ($ordersSent == $totalOrders && $ordersSent != 0) {

                return redirect()->back()->with(
                    'success',
                    'تــم تــعديــل بــيــانــات لـكـل الـطـلـبـيـات الـمـرتـبـطـة بالـرحـلـة بــنــجــاح مـع إرســال رســالــة واتســاب للـزبــائــن'
                );
            }


            if ($ordersSent > 0 && $ordersSent < $totalOrders) {

                $failedCount = count($whatsappFailedList);

                return redirect()->back()->with(
                    'warning',
                    'تــم تــعديــل بــيــانــات لـكـل الـطـلـبـيـات بـنــجــاح، وتم إرسال رسائل واتساب إلى '
                        . $ordersSent
                        . ' زبون من أصل '
                        . $totalOrders
                        . '، بينما فشل الإرسال إلى '
                        . $failedCount
                        . ' زبائن.'
                );
            }


            if ($totalOrders > 0 && count($whatsappFailedList) > 0) {

                return redirect()->back()->with(
                    'warning',
                    'تــم تــعديــل بــيــانــات لـكـل الـطـلـبـيـات بـنــجــاح، ولكن تعذر إرسال رسائل واتساب للزبائن.'
                );
            }


            return redirect()->back()->with(
                'success',
                'تــم تــعديــل بــيــانــات لـكـل الـطـلـبـيـات الـمـرتـبـطـة بالـرحـلـة بــنــجــاح'
            );
        } catch (\Throwable $e) {

            DB::rollBack();

            \Log::error(
                'trip_details error',
                [
                    'order_id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return redirect()->back()->with(
                'error',
                'للاسف حدث خطأ ما الرجاء اعادة المحاولة'
            );
        }
    }

   public function changeNote(Request $request, $id)
{
    // Validation
    $request->validate([
        'note' => 'required|string',
        'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
    ]);

    DB::beginTransaction();

    try {
        $orderSent = 0;

        $order = Order::find($id);

        if (!$order) {
            DB::rollBack();

            return redirect()->back()->with(
                'error',
                'للاسف حدث خطأ ما الرجاء اعادة المحاولة'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Update Delivered Status
        |--------------------------------------------------------------------------
        */

        if ($request->boolean('delivered')) {
            $order->delivered = true;
            $order->delivered_at = now();
        } else {
            $order->delivered = false;
        }

        $order->save();

        /*
        |--------------------------------------------------------------------------
        | Check if the same note already exists for this order today
        |--------------------------------------------------------------------------
        */

        $note = null;

$noteHash = hash('sha256', $request->input('note'));

$noteExists = OrderNotes::where('order_id', $order->id)
    ->where('note_hash', $noteHash)
    ->whereDate('created_at', today())
    ->exists();

if (!$noteExists) {

            $note = new OrderNotes();

            $note->order_id = $order->id;
            $note->note = $request->input('note');
            $note->note_hash = $noteHash;
            $note->note_date = today();
            $note->user_id = auth()->user()->id;

            /*
            |--------------------------------------------------------------------------
            | Upload Image
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $uploadPath = public_path('uploads/order_notes');
                // Create directory if it doesn't exist
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                $imageName = time() . '_' . uniqid() . '.' . $image->extension();
                $image->move($uploadPath, $imageName);
                $note->image = $imageName;
            }
            $note->save();
        }

        /*
        |--------------------------------------------------------------------------
        | WhatsApp Message
        |--------------------------------------------------------------------------
        */

        $messageNote1 = whatsappMessage::where('type', 'note')->first();

        if ($messageNote1 && $note) {


            $message = str_replace(
                [
                    '{{$order1->customer->name}}',
                    '{{$order1->customer->code}}',
                    '{{$order1->trip_status}}',
                    '{{$order1->code}}',
                    '{{$order1->trip_details}}',
                    '{{$order1->note}}',
                    '{{$app_url}}'
                ],
                [
                    $order->customer->name,
                    $order->customer->code,
                    $order->trip_status,
                    $order->code,
                    $order->trip_details,
                    $note->note,
                    config('app.url')
                ],
                $messageNote1->message
            );

            /*
            |--------------------------------------------------------------------------
            | Format Libyan Phone Number
            |--------------------------------------------------------------------------
            */

            $phone = preg_replace('/\D/', '', $order->customer->phone_1);

            $phone = ltrim($phone, '0+');

            if (!str_starts_with($phone, '218')) {
                $phone = '218' . $phone;
            }

            // Ensure maximum 12 digits
            $phone = substr($phone, 0, 12);

            /*
            |--------------------------------------------------------------------------
            | Send WhatsApp
            |--------------------------------------------------------------------------
            */

            $response = app(WhatsAppService::class)->send([
                'phone' => [$phone],
                'message' => $message,
            ], attempts: 3);

            if ($response->successful()) {
                $orderSent = 1;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Commit Database Changes
        |--------------------------------------------------------------------------
        */

        DB::commit();

        /*
        |--------------------------------------------------------------------------
        | Return Message
        |--------------------------------------------------------------------------
        */

        if ($orderSent != 0) {

            return redirect()->back()->with(
                'success',
                'تــم تــعديــل مــلاحــظــات الـطـلـبـيـة بــنــجــاح مـع إرســال رســالــة واتســاب للـزبـون'
            );

        }

        return redirect()->back()->with(
            'success',
            'تــم تــعديــل مــلاحــظــات الـطـلـبـيـة بــنــجــاح'
        );

    } catch (\Exception $e) {

        DB::rollBack();

        return redirect()->back()->with(
            'error',
            'للاسف حدث خطأ ما الرجاء اعادة المحاولة: ' . $e->getMessage()
        );
    }
}


public function deleteNote($id)
{
    DB::beginTransaction();

    try {

        $note = OrderNotes::find($id);

        if (!$note) {
            DB::rollBack();

            return redirect()->back()->with(
                'error',
                'الملاحظة غير موجودة'
            );
        }

        $log = new Log;
        $log->user_id = auth()->user()->id;
        $log->type = "order";
        $log->title = " حذف ملاحظة الطلب (" . $note->order->code . ")";
        $log->log = " تم حذف الملاحظة (" . $note->note . ")";
        $log->save();

        // Keep the image name before deleting the database record
        $imageName = $note->image;

        // Delete the note from database
        $note->delete();

        DB::commit();

        // Delete image from files AFTER successful database deletion
        if ($imageName) {

            $imagePath = public_path(
                'uploads/order_notes/' . $imageName
            );

            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        return redirect()->back()->with(
            'success',
            'تــم حــذف الملاحظة بــنــجــاح'
        );

    } catch (\Exception $e) {

        DB::rollBack();

        return redirect()->back()->with(
            'error',
            'للاسف حدث خطأ ما الرجاء اعادة المحاولة'
        );
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
            $order = Order::find($id);
            $order->status = "canceld";
            $order->update();
            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "order";
            $log->title = " إلغاء الطلب (" . $order->code . ")";
            $log->log = " تم إلغاء الطلب (" . $order->code . ")";
            $log->save();

            DB::commit();
            return redirect()->route('customer.index')->with('success', 'تــم تـعـديـل بـيـانـات الــطــلــب بــنــجــاح');
            // all good
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    public function destroy2($id)
    {
        DB::beginTransaction();
        try {
            $order = Order::find($id);
            if (!$order) {
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
            }
            if ($order->paids->count() > 0) {
                DB::rollback();
                return redirect()->back()->with('error', 'للاسف لايمكن حذف الطلب وذلك لوجد تسديد مستحقات داخل الطلب');
            }

            $log = new Log;
            $log->user_id = auth()->user()->id;
            $log->type = "order";
            $log->title = " حذف الطلب (" . $order->code . ") DYL";
            $log->log = " تم حذف الطلب بتاريخ (" . now()->format('Y-m-d') . ") الخاص بالزبون " . $order->customer->name . " بالقيمة " . $order->total_price . " ";
            $log->save();
            $order->delete();
            DB::commit();
            return redirect()->back()->with('success', 'تــم حــذف الــطــلــب بــنــجــاح');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }
}
