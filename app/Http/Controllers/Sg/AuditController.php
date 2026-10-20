<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Log;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Csv;
use App\Services\Period;
use Illuminate\Http\Request;

/** سجل التعديلات: من غيّر ماذا ومتى، مع فلاتر */
class AuditController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:system-edit');
    }

    public function index(Request $request)
    {
        $p = Period::make($request->input('p', 'week'));
        $tab = $request->input('tab') === 'old' ? 'old' : 'audit';

        if ($tab === 'old') {
            $q = Log::with('user')->whereBetween('created_at', [$p->from, $p->to]);
            if ($request->filled('user')) {
                $q->where('user_id', $request->user);
            }
            if ($request->filled('type')) {
                $q->where('type', $request->type);
            }
            if ($request->filled('q')) {
                $t = trim($request->q);
                $q->where(fn ($w) => $w->where('title', 'like', "%$t%")->orWhere('log', 'like', "%$t%"));
            }
            $rows = $q->latest()->paginate(50)->withQueryString();

            return view('sg.audit.index', [
                'nav' => 'audit', 'tab' => $tab, 'period' => $p, 'rows' => $rows,
                'users' => User::orderBy('name')->get(['id', 'name']), 'types' => Log::query()->distinct()->orderBy('type')->pluck('type')->filter(),
            ]);
        }

        $q = AuditLog::query()->whereBetween('created_at', [$p->from, $p->to]);
        if ($request->filled('user')) {
            $request->user === 'system' ? $q->where('user_type', 'system') : $q->where('user_id', $request->user)->where('user_type', '!=', 'customer');
        }
        if ($request->filled('model')) {
            $q->where('model', $request->model);
        }
        if ($request->filled('action')) {
            $q->where('action', $request->action);
        }
        if ($request->filled('field')) {
            $q->where('action', 'updated')->where('changes', 'like', '%"' . $request->field . '"%');
        }
        if ($request->filled('q')) {
            $t = trim($request->q);
            $q->where(fn ($w) => $w->where('label', 'like', "%$t%")->orWhere('changes', 'like', '%' . $t . '%')->when(ctype_digit($t), fn ($x) => $x->orWhere('model_id', (int) $t)));
        }
        if ($request->boolean('money')) {
            // فقط التعديلات على المبلغ
            $q->where(fn ($w) => $w->whereIn('model', ['PaidHistory', 'TreasuryHistory', 'Purchase', 'ShippingCharges', 'CourierSettlement', 'Dept', 'DeptHistory'])
                ->orWhere(fn ($x) => $x->where('model', 'Order')->where(fn ($y) => $y->where('changes', 'like', '%"total_price"%')->orWhere('changes', 'like', '%"paid_price"%')->orWhere('changes', 'like', '%"discount"%')->orWhere('changes', 'like', '%"ship_price"%'))));
        }
        if ($request->export === 'csv') {
            return Csv::download('audit.csv', ['الوقت', 'من', 'العملية', 'القسم', 'السجل', 'التفاصيل', 'IP'],
                (clone $q)->latest('id')->take(5000)->get()->map(fn ($a) => [$a->created_at->format('Y-m-d H:i:s'), $a->user_name, AuditLog::ACTIONS[$a->action] ?? $a->action, AuditService::modelLabel($a->model), $a->label ?: '#' . $a->model_id, static::plain($a), $a->ip]));
        }
        $stats = (clone $q)->selectRaw('action, COUNT(*) n')->groupBy('action')->pluck('n', 'action');
        $byUser = (clone $q)->selectRaw('user_name, COUNT(*) n')->groupBy('user_name')->orderByDesc('n')->take(8)->pluck('n', 'user_name');
        $rows = $q->latest('id')->paginate(50)->withQueryString();

        return view('sg.audit.index', [
            'nav' => 'audit', 'tab' => $tab, 'period' => $p, 'rows' => $rows, 'stats' => $stats, 'byUser' => $byUser,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'models' => AuditLog::query()->distinct()->pluck('model')->mapWithKeys(fn ($m) => [$m => AuditService::modelLabel($m)])->sort(),
            'fields' => collect(AuditService::FIELDS)->only(['total_price', 'paid_price', 'discount', 'ship_price', 'status', 'phone_1', 'code', 'name', 'credit', 'points', 'cost', 'amount', 'password']),
        ]);
    }

    public static function plain(AuditLog $a): string
    {
        $out = [];
        foreach ((array) $a->changes as $k => $v) {
            $out[] = AuditService::fieldLabel($k) . ': ' . (is_array($v) ? ($v[0] === '' || $v[0] === null ? '—' : $v[0]) . ' ← ' . $v[1] : $v);
        }

        return implode(' | ', $out);
    }
}
