<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Notif_orgs;
use App\Models\Order;
use App\Models\PaidHistory;
use App\Models\PaymentExpenses;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\ShippingCharges;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat\Wizard\Currency;

class ReportController extends Controller
{


    function __construct()
    {
        $this->middleware('permission:reports-list', ['only' => ['index', 'search']]);
    }

    /**
     * Display a listing of the resource.
     */public function index(Request $request)
{
    $query = $request->all();
    $fromDate = $request->input('fromDate');
    $toDate = $request->input('toDate');

    $main_notifications = Notif_orgs::where('user_id', auth()->id())
        ->latest()
        ->take(5)
        ->get();

    // ❌ Require at least fromDate
    if (!$fromDate) {
        return view('reports.index', compact('main_notifications', 'query'))
            ->with('error', 'يرجى تحديد التاريخ');
    }

    // 📅 Handle single date or range
    if ($fromDate && !$toDate) {

        $ordersget = Order::whereDate('created_at', $fromDate)->get();

        $income = PaidHistory::with('order')
            ->whereDate('created_at', $fromDate)
            ->get();

        $expensesPurchase = Purchase::whereDate('created_at', $fromDate)->get();
        $expensesShippingCharges = ShippingCharges::whereDate('created_at', $fromDate)->get();
        $expensesPaymentExpenses = PaymentExpenses::whereDate('created_at', $fromDate)->get();

        $ordersDelivered = Order::whereDate('delivered_at', $fromDate)
            ->where('delivered', true)
            ->get();

        $ordersUpdatedget = Log::where('type', 'order')
            ->where('title', 'like', '%تعديل الطلب%')
            ->whereDate('created_at', $fromDate)
            ->get();

        $ordersDeletedget = Log::where('type', 'order')
            ->where('title', 'like', '%حذف الطلب%')
            ->whereDate('created_at', $fromDate)
            ->get();

    } else {

        $ordersget = Order::whereBetween('created_at', [$fromDate, $toDate])->get();

        $income = PaidHistory::with('order')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->get();

        $expensesPurchase = Purchase::whereBetween('created_at', [$fromDate, $toDate])->get();
        $expensesShippingCharges = ShippingCharges::whereBetween('created_at', [$fromDate, $toDate])->get();
        $expensesPaymentExpenses = PaymentExpenses::whereBetween('created_at', [$fromDate, $toDate])->get();

        $ordersDelivered = Order::whereBetween('delivered_at', [$fromDate, $toDate])
            ->where('delivered', true)
            ->get();

        $ordersUpdatedget = Log::where('type', 'order')
            ->where('title', 'like', '%تعديل الطلب%')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->get();

        $ordersDeletedget = Log::where('type', 'order')
            ->where('title', 'like', '%حذف الطلب%')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->get();
    }

    // ================================
    // 💰 TOTALS PER PAYMENT + CURRENCY
    // ================================

    $paymentMethods = PaymentMethod::all();

    $totals = [];

    foreach ($paymentMethods as $method) {
        $totals[$method->id] = [
            'name' => $method->name,
            'currencies' => [
                'dinar' => 0,
                'dollar' => 0,
                'euro' => 0,
            ]
        ];
    }

    foreach ($income as $pay) {
        if (!$pay->order) continue;

        $currency = $pay->order->currency; // dinar / dollar / euro
        $paymentId = $pay->payment_id;

        if (isset($totals[$paymentId]['currencies'][$currency])) {
$totals[$paymentId]['currencies'][$currency] += $pay->paid_price;        }
    }

    // ================================
    // 📊 OPTIONAL: TOTAL PER CURRENCY (ALL METHODS)
    // ================================

    $currencyTotals = [
        'dinar' => 0,
        'dollar' => 0,
        'euro' => 0,
    ];

    foreach ($totals as $method) {
        foreach ($method['currencies'] as $currency => $value) {
            $currencyTotals[$currency] += $value;
        }
    }

    // ================================
    // 📦 RETURN VIEW
    // ================================

    return view('reports.index', compact(
        'main_notifications',
        'fromDate',
        'toDate',
        'query',
        'income',
        'expensesPurchase',
        'expensesShippingCharges',
        'expensesPaymentExpenses',
        'ordersget',
        'ordersDelivered',
        'ordersUpdatedget',
        'ordersDeletedget',
        'totals',           // 👈 per payment + currency
        'currencyTotals'    // 👈 overall currency totals
    ));
}

}
