<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Notif_orgs;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\PaymentMethod;
use App\Services\FinanceReport;
use App\Services\TreasuryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * الشركاء: سحب يومي من الخزينة، وتوزيع الفائض بمبلغ حر (مش نسبة ثابتة).
 */
class PartnerController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:partner-list', ['only' => ['index']]);
        $this->middleware('permission:partner-create', ['only' => ['store', 'update', 'storeTransaction', 'distribute', 'surplusSettings']]);
        $this->middleware('permission:partner-delete', ['only' => ['destroyTransaction']]);
    }

    public function index(Request $request)
    {
        $partners = Partner::orderBy('id')->get();
        $payments = PaymentMethod::where('status', true)->get();

        $totals = PartnerTransaction::selectRaw('partner_id, type, currency, SUM(amount) as total')
            ->groupBy('partner_id', 'type', 'currency')->get();
        $todayTotals = PartnerTransaction::selectRaw('partner_id, currency, SUM(amount) as total')
            ->where('type', 'withdrawal')->whereDate('date', now()->toDateString())
            ->groupBy('partner_id', 'currency')->get();

        $transactions = PartnerTransaction::with(['partner', 'payment', 'createdUser'])
            ->when($request->filled('partner_id'), fn ($q) => $q->where('partner_id', $request->partner_id))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->to))
            ->latest('date')->latest('id')->paginate(30)->withQueryString();

        // الفائض = الربح الصافي من البداية - كل اللي سحبه الشركاء
        $surplus = FinanceReport::undistributedSurplus();

        $main_notifications = Notif_orgs::where('user_id', auth()->id())->latest()->take(5)->get();

        return view('admin.finance.partners', compact('partners', 'payments', 'totals', 'todayTotals', 'transactions', 'surplus', 'main_notifications'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:30',
            'note' => 'nullable|string|max:500',
        ], ['name.required' => 'اكتب اسم الشريك']);

        Partner::create($data);

        return back()->with('success', 'تمت إضافة الشريك');
    }

    public function update(Request $request, Partner $partner)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:30',
            'status' => 'nullable|boolean',
        ]);
        $data['status'] = $request->boolean('status', true);
        $partner->update($data);

        return back()->with('success', 'تم تعديل بيانات الشريك');
    }

    public function storeTransaction(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:' . implode(',', array_keys(PartnerTransaction::TYPES)),
            'currency' => 'required|in:dinar,dollar,euro',
            'payment_id' => 'required|exists:payment_methods,id',
            'date' => 'required|date',
            'note' => 'nullable|string|max:500',
            'amounts' => 'required|array',
            'amounts.*' => 'nullable|numeric|min:0',
        ], ['amounts.required' => 'اكتب المبلغ']);

        $amounts = collect($data['amounts'])->filter(fn ($v) => (float) $v > 0);
        if ($amounts->isEmpty()) {
            return back()->withInput()->with('error', 'اكتب مبلغ لشريك واحد على الأقل');
        }

        DB::beginTransaction();
        try {
            foreach ($amounts as $partnerId => $amount) {
                $partner = Partner::findOrFail($partnerId);
                $tx = PartnerTransaction::create([
                    'partner_id' => $partner->id,
                    'type' => $data['type'],
                    'amount' => $amount,
                    'currency' => $data['currency'],
                    'payment_id' => $data['payment_id'],
                    'date' => $data['date'],
                    'note' => $data['note'] ?? null,
                    'created_id' => auth()->id(),
                ]);
                if ($data['type'] === 'settle' && (float) $amount > PartnerTransaction::debtOf($partner->id, $data['currency']) + 0.009) {
                    throw new \RuntimeException('مبلغ التسديد أكبر من دين ' . $partner->name . ' (' . TreasuryService::fmt(PartnerTransaction::debtOf($partner->id, $data['currency'])) . ')');
                }
                $label = PartnerTransaction::TYPES[$data['type']] . ' - ' . $partner->name . (!empty($data['note']) ? ' (' . $data['note'] . ')' : '');
                if ($tx->isOutflow()) {
                    TreasuryService::withdraw((float) $amount, $data['currency'], (int) $data['payment_id'], 'partner', $tx->id, $label);
                } else {
                    TreasuryService::deposit((float) $amount, $data['currency'], (int) $data['payment_id'], 'partner', $tx->id, $label);
                }
                Log::create([
                    'user_id' => auth()->id(),
                    'type' => 'Treasury',
                    'title' => ' ' . $label,
                    'log' => ' ' . $label . ' بقيمة ' . $amount . ' ' . TreasuryService::sign($data['currency']),
                ]);
            }
            DB::commit();

            return back()->with('success', 'تم تسجيل ' . PartnerTransaction::TYPES[$data['type']]);
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->withInput()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }

    /** المدير يحدد من وين يبدا حساب الفائض ورصيد افتتاحي */
    public function surplusSettings(Request $request)
    {
        $data = $request->validate(['surplus_from' => 'nullable|date', 'surplus_opening' => 'nullable|numeric']);
        \App\Services\AppSettings::put(['surplus_from' => $data['surplus_from'] ?? null, 'surplus_opening' => (float) ($data['surplus_opening'] ?? 0)]);
        Log::create(['user_id' => auth()->id(), 'type' => 'Treasury', 'title' => ' إعداد الفائض', 'log' => ' من ' . ($data['surplus_from'] ?? 'تلقائي') . ' · افتتاحي ' . ($data['surplus_opening'] ?? 0)]);

        return back()->with('success', 'تعدّل حساب الفائض');
    }

    /** توزيع الفائض: لكل شريك نصيبه، ويمكن خصم دينه (مصروفاته الشخصية) من نصيبه */
    public function distribute(Request $request)
    {
        $data = $request->validate([
            'payment_id' => 'required|exists:payment_methods,id', 'date' => 'required|date', 'note' => 'nullable|string|max:500',
            'amounts' => 'required|array', 'amounts.*' => 'nullable|numeric|min:0', 'deduct' => 'nullable|array',
            'currency' => 'nullable|in:dinar,dollar',
        ]);
        $cur = $data['currency'] ?? 'dinar';
        DB::beginTransaction();
        try {
            $done = [];
            foreach ($data['amounts'] as $pid => $share) {
                $share = round((float) $share, 2);
                if ($share <= 0) {
                    continue;
                }
                $partner = Partner::findOrFail($pid);
                $debt = max(0, PartnerTransaction::debtOf($partner->id));
                $ded = $cur === 'dinar' && !empty($data['deduct'][$pid]) ? round(min($debt, $share), 2) : 0;
                $cash = round($share - $ded, 2);
                if ($ded > 0) {
                    PartnerTransaction::create(['partner_id' => $partner->id, 'type' => 'settle', 'via' => 'distribution', 'amount' => $ded, 'currency' => 'dinar',
                        'payment_id' => $data['payment_id'], 'date' => $data['date'], 'note' => 'خُصم من نصيبه في توزيع الفائض', 'created_id' => auth()->id()]);
                }
                if ($cash > 0) {
                    $tx = PartnerTransaction::create(['partner_id' => $partner->id, 'type' => 'distribution', 'amount' => $cash, 'currency' => $cur,
                        'payment_id' => $data['payment_id'], 'date' => $data['date'], 'note' => $data['note'] ?? ($ded > 0 ? 'نصيبه ' . $share . ' − دين ' . $ded : null), 'created_id' => auth()->id()]);
                    TreasuryService::withdraw($cash, $cur, (int) $data['payment_id'], 'partner', $tx->id, 'توزيع فائض - ' . $partner->name . (!empty($data['note']) ? ' (' . $data['note'] . ')' : ''));
                }
                Log::create(['user_id' => auth()->id(), 'type' => 'Treasury', 'title' => ' توزيع فائض - ' . $partner->name, 'log' => ' نصيبه ' . $share . ($ded > 0 ? ' · خُصم دين ' . $ded . ' · صُرف ' . $cash : '')]);
                $done[] = $partner->name . ' ' . TreasuryService::fmt($cash) . ' ' . TreasuryService::sign($cur) . ($ded > 0 ? ' (بعد خصم ' . TreasuryService::fmt($ded) . ')' : '');
            }
            if (!$done) {
                throw new \RuntimeException('اكتب نصيب شريك واحد على الأقل');
            }
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم توزيع الفائض: ' . implode('، ', $done));
    }

    public function destroyTransaction(PartnerTransaction $transaction)
    {
        DB::beginTransaction();
        try {
            $label = 'حذف ' . PartnerTransaction::TYPES[$transaction->type] . ' - ' . $transaction->partner->name;
            if (!$transaction->movesTreasury()) {
                // لا حركة خزينة
            } elseif ($transaction->isOutflow()) {
                TreasuryService::deposit((float) $transaction->amount, $transaction->currency, (int) $transaction->payment_id, 'partner_reverse', $transaction->id, $label);
            } else {
                TreasuryService::withdraw((float) $transaction->amount, $transaction->currency, (int) $transaction->payment_id, 'partner_reverse', $transaction->id, $label);
            }
            Log::create(['user_id' => auth()->id(), 'type' => 'Treasury', 'title' => ' ' . $label, 'log' => ' ' . $label . ' بقيمة ' . $transaction->amount]);
            $transaction->delete();
            DB::commit();

            return back()->with('success', 'تم الحذف ورجع الرصيد للخزينة');
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'للاسف حدث خطأ ما الرجاء اعادة المحاولة');
        }
    }
}
