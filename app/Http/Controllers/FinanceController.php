<?php

namespace App\Http\Controllers;

use App\Models\CurrencyExchange;
use App\Models\Notif_orgs;
use App\Models\Partner;
use App\Services\FinanceReport;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:finance-dashboard');
    }

    public function index(Request $request)
    {
        $preset = $request->input('preset', $request->filled('from') ? 'custom' : 'month');
        [$from, $to] = match ($preset) {
            'today' => [now()->toDateString(), now()->toDateString()],
            'week' => [now()->startOfWeek(\Carbon\Carbon::SATURDAY)->toDateString(), now()->toDateString()],
            'year' => [now()->startOfYear()->toDateString(), now()->toDateString()],
            'custom' => [$request->input('from'), $request->input('to')],
            default => [now()->startOfMonth()->toDateString(), now()->toDateString()],
        };

        $report = FinanceReport::period($from, $to);
        $balances = FinanceReport::balances();
        $receivables = FinanceReport::receivables();
        $surplus = FinanceReport::undistributedSurplus();
        $pendingPurchases = FinanceReport::pendingPurchases();
        $arrivedUnpaid = FinanceReport::arrivedUnpaid();
        $partners = Partner::orderBy('id')->get()->keyBy('id');
        $latestRate = CurrencyExchange::latestRate();
        $main_notifications = Notif_orgs::where('user_id', auth()->id())->latest()->take(5)->get();

        return view('admin.finance.dashboard', compact(
            'preset', 'from', 'to', 'report', 'balances', 'receivables', 'surplus',
            'pendingPurchases', 'arrivedUnpaid', 'partners', 'latestRate', 'main_notifications'
        ));
    }
}
