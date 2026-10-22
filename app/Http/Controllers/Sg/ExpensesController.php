<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Log;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchaseDescription;
use App\Models\TripPurchase;
use App\Models\User;
use App\Services\Csv;
use App\Services\FinanceReport;
use App\Services\Period;
use App\Services\TreasuryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** مصروفات الشركة (جدول purchases القديم) */
class ExpensesController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:purchase-list', ['only' => ['index']]);
        $this->middleware('permission:purchase-create', ['only' => ['store', 'storeCategory', 'updateCategory']]);
        $this->middleware('permission:purchase-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $p = Period::make($request->input('p', 'month'));
        // الموظف (مش المدير) يشوف مصروفاته هو بس
        $mine = !auth()->user()->hasRole('Admin');
        $own = fn ($q) => $mine ? $q->where('created_id', auth()->id()) : $q;
        $base = $own(Purchase::whereBetween('date', [$p->from->toDateString(), $p->to->toDateTimeString()]));
        $total = (float) (clone $base)->where(fn ($q) => $q->whereNull('currency')->orWhere('currency', 'dinar'))->sum('cost');
        $prev = (float) $own(Purchase::query())->whereBetween('date', [$p->prevFrom->toDateString(), $p->prevTo->toDateTimeString()])->where(fn ($q) => $q->whereNull('currency')->orWhere('currency', 'dinar'))->sum('cost');
        $byCat = (clone $base)->with('desc')->get()->groupBy('desc_id')->map(fn ($g) => ['cat' => $g->first()->desc, 'total' => $g->sum('cost'), 'n' => $g->count()])->sortByDesc('total')->values();
        $days = max(1, $p->from->diffInDays(min(now(), $p->to)) + 1);
        $tripProfit = $mine ? 0 : TripPurchase::countable()->whereBetween('purchased_at', [$p->from->toDateString(), $p->to->toDateTimeString()])->get()->sum(fn ($t) => $t->summary()['profit_dinar']);

        // آخر 6 شهور
        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $six = $months->map(fn ($m) => (float) $own(Purchase::query())->whereBetween('date', [$m->toDateString(), $m->copy()->endOfMonth()->toDateTimeString()])->sum('cost'));

        $q = (clone $base)->with(['desc', 'payment']);
        if ($request->filled('q')) {
            $q->where('note', 'like', '%' . $request->q . '%');
        }
        foreach (['desc_id', 'payment_id', 'created_id'] as $k) {
            if ($request->filled($k)) {
                $q->where($k, $request->$k);
            }
        }
        if ($request->export === 'csv') {
            return Csv::download('expenses.csv', ['التاريخ', 'النوع', 'التفاصيل', 'الخزينة', 'المبلغ', 'العملة'],
                $q->orderByDesc('date')->get()->map(fn ($e) => [$e->date, optional($e->desc)->name, $e->note, optional($e->payment)->name, $e->cost, $e->currency ?: 'dinar']));
        }
        $list = $q->orderByDesc('date')->orderByDesc('id')->paginate(25)->withQueryString();

        $cats = PurchaseDescription::orderBy('id')->get();
        $thisMonth = Purchase::where('date', '>=', now()->startOfMonth()->toDateString())->pluck('desc_id')->unique();
        $fixed = $cats->filter(fn ($c) => $c->monthly_day)->map(fn ($c) => (object) [
            'cat' => $c, 'paid' => $thisMonth->contains($c->id),
            'left' => (int) now()->startOfDay()->diffInDays(now()->startOfMonth()->addDays(min(28, $c->monthly_day) - 1), false),
        ]);

        // 👥 مصروفات الموظفين: كل موظف كم صرف (سلفة/شخصي ينخصم من راتبه، أو مصروف شغل على الشركة)
        $tab = $request->input('tab') === 'staff' && !$mine && \Illuminate\Support\Facades\Schema::hasColumn('purchases', 'staff_kind') ? 'staff' : 'company';
        $staffCards = collect();
        $staffList = null;
        if ($tab === 'staff') {
            $inP = Purchase::whereIn('staff_kind', ['advance', 'work'])->whereBetween('date', [$p->from->toDateString(), $p->to->toDateTimeString()])->get();
            $open = Purchase::where('staff_kind', 'advance')->whereNull('settled_id')->get()->groupBy('staff_id');
            $ids = $inP->pluck('staff_id')->merge($open->keys())->filter()->unique();
            $staffCards = User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])->map(function ($u) use ($inP, $open) {
                $mineP = $inP->where('staff_id', $u->id);

                return (object) [
                    'user' => $u, 'n' => $mineP->count(),
                    'spent' => round((float) $mineP->sum('cost'), 2),
                    'work' => round((float) $mineP->where('staff_kind', 'work')->sum('cost'), 2),
                    'deducted' => round((float) $mineP->where('staff_kind', 'advance')->whereNotNull('settled_id')->sum('cost'), 2),
                    'open' => round((float) optional($open->get($u->id))->sum('cost'), 2),
                ];
            })->sortByDesc('open')->values();
            $sl = Purchase::with(['payment', 'staff'])->whereIn('staff_kind', ['advance', 'work'])->whereBetween('date', [$p->from->toDateString(), $p->to->toDateTimeString()]);
            if ($request->filled('staff')) {
                $sl->where('staff_id', (int) $request->staff);
            }
            $st = $request->input('st');
            if ($st === 'open') {
                $sl->where('staff_kind', 'advance')->whereNull('settled_id');
            } elseif ($st === 'done') {
                $sl->where('staff_kind', 'advance')->whereNotNull('settled_id');
            } elseif ($st === 'work') {
                $sl->where('staff_kind', 'work');
            }
            if ($request->filled('q')) {
                $sl->where('note', 'like', '%' . $request->q . '%');
            }
            $staffList = $sl->orderByDesc('date')->orderByDesc('id')->paginate(30)->withQueryString();
        }

        return view('sg.expenses.index', [
            'tab' => $tab, 'staffCards' => $staffCards, 'staffList' => $staffList,
            'nav' => 'exp', 'period' => $p, 'total' => $total, 'prev' => $prev, 'byCat' => $byCat, 'days' => $days,
            'tripProfit' => $tripProfit, 'six' => ['labels' => $months->map(fn ($m) => Period::MONTHS[$m->month - 1])->values(), 'values' => $six->values()],
            'list' => $list, 'cats' => $cats, 'fixed' => $fixed,
            'payments' => PaymentMethod::where('status', true)->orderBy('id')->get(), 'balances' => $mine ? [] : FinanceReport::balances(), 'mine' => $mine,
            'users' => User::whereIn('id', Purchase::distinct()->pluck('created_id'))->pluck('name', 'id'),
            'openAdv' => \Illuminate\Support\Facades\Schema::hasColumn('purchases', 'staff_kind') ? Purchase::where('staff_kind', 'advance')->whereNull('settled_id')->get()->groupBy('staff_id')->map(fn ($g) => round((float) $g->sum('cost'), 2)) : collect(),
            'cashId' => \App\Services\AppSettings::cashPaymentId(),
            'partners' => \App\Models\Partner::where('status', true)->orderBy('id')->get(),
            'staff' => User::where('status', true)->where(fn ($q) => $q->whereNull('type_id')->orWhere('type_id', '!=', 4))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cost' => 'required|numeric|min:0.01',
            'desc_id' => 'nullable|exists:purchase_descriptions,id',
            'new_cat' => 'nullable|string|max:60',
            'payment_id' => 'required|exists:payment_methods,id',
            'currency' => 'nullable|in:dinar,dollar,euro',
            'date' => 'nullable|date',
            'note' => 'nullable|string|max:500',
            'attachment' => 'nullable|file|max:6144|mimes:jpg,jpeg,png,webp,pdf',
        ], ['cost.required' => 'اكتب المبلغ']);
        if (empty($data['desc_id']) && empty($data['new_cat'])) {
            return back()->withInput()->with('error', 'اختر نوع المصروف');
        }
        $cur = $data['currency'] ?? 'dinar';
        DB::beginTransaction();
        try {
            if (!empty($data['new_cat'])) {
                $desc = PurchaseDescription::where('name', trim($data['new_cat']))->first();
                if (!$desc) {
                    $desc = new PurchaseDescription;
                    $desc->name = trim($data['new_cat']);
                    $desc->created_id = auth()->id();
                    $desc->save();
                }
            } else {
                $desc = PurchaseDescription::find($data['desc_id']);
            }
            $e = new Purchase;
            $e->desc_id = $desc->id;
            $e->cost = $data['cost'];
            $e->date = $data['date'] ?? now()->toDateString();
            $e->note = $data['note'] ?? null;
            $e->payment_id = $data['payment_id'];
            $e->currency = $cur;
            $e->currency_sign = TreasuryService::sign($cur);
            $e->created_id = auth()->id();
            if ($request->hasFile('attachment')) {
                $e->attachment = $request->file('attachment')->store('expenses', 'public');
            }
            $e->save();
            TreasuryService::withdraw((float) $data['cost'], $cur, (int) $data['payment_id'], 'purchase', $e->id, $desc->name . ($e->note ? ' — ' . $e->note : ''));
            Log::create(['user_id' => auth()->id(), 'type' => 'purchase', 'title' => ' مصروف جديد (' . $desc->name . ')', 'log' => ' تم تسجيل مصروف بقيمة (' . $data['cost'] . ')']);
            DB::commit();
        } catch (\Throwable $ex) {
            DB::rollBack();
            report($ex);

            return back()->withInput()->with('error', $ex instanceof \RuntimeException ? $ex->getMessage() : 'للأسف حدث خطأ، يرجى إعادة المحاولة');
        }

        return back()->with('success', 'سُجّل المصروف · ' . TreasuryService::fmt($data['cost']) . ' ' . TreasuryService::sign($cur) . ' (' . $desc->name . ')');
    }

    /** 💰 مرتب / 💸 سلفة لموظف (المدير بس) — المرتب ينوه على السلف ويخصمها لو اخترت */
    public function salary(Request $request)
    {
        abort_unless(auth()->user()->hasRole('Admin') || auth()->user()->can('user-edit') || ($request->input('kind') !== 'salary' && auth()->user()->can('purchase-create')), 403);
        $data = $request->validate([
            'kind' => 'required|in:salary,advance,work', 'staff_id' => 'required|exists:users,id', 'cost' => 'required|numeric|min:0.01',
            'payment_id' => 'required|exists:payment_methods,id', 'date' => 'nullable|date', 'note' => 'nullable|string|max:300', 'deduct' => 'nullable|boolean',
        ], ['staff_id.required' => 'اختار الموظف', 'cost.required' => 'اكتب المبلغ']);
        $u = User::find($data['staff_id']);
        $catName = ['salary' => 'مرتبات', 'advance' => 'سلف الموظفين', 'work' => 'مصروفات شغل الموظفين'][$data['kind']];
        DB::beginTransaction();
        try {
            $desc = PurchaseDescription::where('name', $catName)->first();
            if (!$desc) {
                $desc = new PurchaseDescription;
                $desc->name = $catName;
                $desc->created_id = auth()->id();
                $desc->save();
            }
            $gross = round((float) $data['cost'], 2);
            $advs = collect();
            $ded = 0.0;
            if ($data['kind'] === 'salary' && $request->boolean('deduct')) {
                $advs = Purchase::where('staff_id', $u->id)->where('staff_kind', 'advance')->whereNull('settled_id')->get();
                $ded = min($gross, round((float) $advs->sum('cost'), 2));
            }
            $net = round($gross - $ded, 2);
            $e = new Purchase;
            $e->desc_id = $desc->id;
            $e->cost = $net;
            $e->date = $data['date'] ?? now()->toDateString();
            $e->note = trim((['salary' => 'مرتب', 'advance' => 'سلفة', 'work' => 'مصروف شغل'][$data['kind']]) . ' — ' . $u->name . ($ded > 0 ? ' · المرتب ' . $gross . ' − سلف ' . $ded : '') . (!empty($data['note']) ? ' · ' . $data['note'] : ''));
            $e->payment_id = $data['payment_id'];
            $e->currency = 'dinar';
            $e->currency_sign = TreasuryService::sign('dinar');
            $e->created_id = auth()->id();
            $e->staff_id = $u->id;
            $e->staff_kind = $data['kind'];
            $e->save();
            if ($net > 0) {
                TreasuryService::withdraw($net, 'dinar', (int) $data['payment_id'], 'purchase', $e->id, $desc->name . ' — ' . $u->name);
            }
            foreach ($advs as $a) {
                $a->settled_id = $e->id;
                $a->save();
            }
            Log::create(['user_id' => auth()->id(), 'type' => 'purchase', 'title' => ' ' . (['salary' => 'مرتب', 'advance' => 'سلفة', 'work' => 'مصروف شغل'][$data['kind']]) . ' (' . $u->name . ')', 'log' => ' ' . $gross . ($ded > 0 ? ' · خصم سلف ' . $ded . ' · صافي ' . $net : '')]);
            DB::commit();
        } catch (\Throwable $ex) {
            DB::rollBack();
            report($ex);

            return back()->with('error', $ex instanceof \RuntimeException ? $ex->getMessage() : 'ما تسجلش: ' . mb_substr($ex->getMessage(), 0, 120));
        }

        return back()->with('success', $data['kind'] === 'salary'
            ? '✓ مرتب ' . $u->name . ': ' . TreasuryService::fmt($gross) . ' د.ل' . ($ded > 0 ? ' · تخصمت سلف ' . TreasuryService::fmt($ded) . ' · استلم ' . TreasuryService::fmt($net) : '')
            : ($data['kind'] === 'work' ? '✓ مصروف شغل على ' . $u->name . ': ' . TreasuryService::fmt($gross) . ' د.ل (على الشركة)' : '✓ سلفة ' . $u->name . ': ' . TreasuryService::fmt($gross) . ' د.ل (ينخصم من مرتبه)'));
    }

    public function destroy(Purchase $purchase)
    {
        DB::beginTransaction();
        try {
            TreasuryService::deposit((float) $purchase->cost, $purchase->currency ?: 'dinar', (int) $purchase->payment_id, 'purchase_reverse', $purchase->id, 'إلغاء مصروف ' . optional($purchase->desc)->name);
            if ($purchase->attachment) {
                Storage::disk('public')->delete($purchase->attachment);
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('purchases', 'settled_id')) {
                Purchase::where('settled_id', $purchase->id)->update(['settled_id' => null]); // السلف ترجع مفتوحة
            }
            $purchase->delete();
            Log::create(['user_id' => auth()->id(), 'type' => 'purchase', 'title' => ' حذف مصروف', 'log' => ' تم حذف مصروف بقيمة (' . $purchase->cost . ') ورجع للخزينة']);
            DB::commit();
        } catch (\Throwable $ex) {
            DB::rollBack();

            return back()->with('error', 'تعذّر علينا حذفه');
        }

        return back()->with('success', 'انحذف المصروف ورجع المبلغ للخزينة');
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:60|unique:purchase_descriptions,name', 'icon' => 'nullable|string|max:8', 'monthly_day' => 'nullable|integer|min:1|max:31']);
        $c = new PurchaseDescription;
        $c->name = $data['name'];
        $c->icon = $data['icon'] ?? null;
        $c->monthly_day = $data['monthly_day'] ?? null;
        $c->created_id = auth()->id();
        $c->save();

        return back()->with('success', 'انضاف النوع ' . $c->name);
    }

    public function updateCategory(Request $request, PurchaseDescription $cat)
    {
        $data = $request->validate(['name' => 'required|string|max:60|unique:purchase_descriptions,name,' . $cat->id, 'icon' => 'nullable|string|max:8', 'monthly_day' => 'nullable|integer|min:1|max:31']);
        $cat->name = $data['name'];
        $cat->icon = $data['icon'] ?? null;
        $cat->monthly_day = $data['monthly_day'] ?? null;
        $cat->save();

        return back()->with('success', 'تم الحفظ');
    }
}
