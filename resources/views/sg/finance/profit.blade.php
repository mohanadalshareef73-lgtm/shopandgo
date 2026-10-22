@extends('sg.layout')
@section('title', 'المالية · الأرباح')
@php
    use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n);
    $shipProfit = $r['shipIncome']['dinar'] - $r['shipCost']['dinar'];
    $prevShip = $prev['shipIncome']['dinar'] - $prev['shipCost']['dinar'];
    $exp = $r['expenses']['dinar'] + $r['paymentExpenses']['dinar'];
    $marg = $r['sales']['dinar'] ? $r['goodsProfit']['dinar'] / $r['sales']['dinar'] * 100 : 0;
    $byDesc = $r['expensesByDesc']->where('currency', 'dinar');
    $mx = max(1, $byDesc->max('total') ?? 1);
    $cols = ['var(--s2)', 'var(--s1)', 'var(--s3)', 'var(--s7)', 'var(--s4)', 'var(--brand)'];
    $partnerCols = ['var(--brand)', 'var(--s1)', 'var(--s3)', 'var(--s7)'];
    $statusTx = \App\Models\PartnerTransaction::TYPES;
@endphp
@section('content')
@include('sg.finance._head')

<div class="kpis g6">
    <div class="card kpi"><div class="lbl">مبيعات الرحلات</div><div class="v"><span class="num">{{ $f($r['sales']['dinar']) }}</span> <small>د.ل</small></div><div class="s">الذي قلته للعملاء · {{ $r['counts']['trips'] }} رحلة</div></div>
    <div class="card kpi"><div class="lbl">تكلفة الشراء</div><div class="v minus"><span class="num">−{{ $f($r['cost']['dinar']) }}</span> <small>د.ل</small></div><div class="s">بسعر صرف كل رحلة</div></div>
    <div class="card kpi"><div class="lbl">ربح الرحلات</div><div class="v plus"><span class="num">{{ $f($r['goodsProfit']['dinar']) }}</span> <small>د.ل</small></div><div class="s"><span class="chip u"><span class="num">{{ number_format($marg, 1) }}%</span></span> {{ $f($r['goodsProfitDollar']) }} $</div></div>
    <div class="card kpi" style="{{ empty($r['withShip']) ? 'opacity:.6' : '' }}"><div class="lbl">ربح الشحن @if (empty($r['withShip']))<small>(مش محسوب في الصافي)</small>@endif @role('Admin')<form method="post" action="{{ route('sg.finance.shiptoggle') }}" style="display:inline">@csrf<button class="btn ghost sm" style="padding:0 8px;min-height:0;height:24px;font-size:12px">{{ \App\Services\AppSettings::get('profit_with_ship', false) ? 'ما تحسبوش' : 'احسبه' }}</button></form>@endrole</div><div class="v {{ $shipProfit >= 0 ? 'plus' : 'minus' }}"><span class="num">{{ $f($shipProfit) }}</span> <small>د.ل</small></div><div class="s">قبضنا {{ $f($r['shipIncome']['dinar']) }} − دفعنا {{ $f($r['shipCost']['dinar']) }}</div></div>
    <div class="card kpi"><div class="lbl">المصاريف</div><div class="v minus"><span class="num">−{{ $f($exp) }}</span> <small>د.ل</small></div><div class="s">{{ $byDesc->count() }} أنواع</div></div>
    <div class="card kpi accent"><div class="lbl">صافي الربح</div><div class="v"><span class="num">{{ $f($r['net']['dinar']) }}</span> <small>د.ل</small></div><div class="s">@include('sg.partials.chg', ['cur' => $r['net']['dinar'], 'prev' => $prev['net']['dinar']]) {{ $period->prevLabel }} {{ $f($prev['net']['dinar']) }}</div></div>
</div>

@php $cs = $r['cardSales'] ?? null; @endphp
@if ($cs && $cs['count'])
<div class="card mb" style="display:flex;gap:18px;flex-wrap:wrap;align-items:center">
    <h3 style="margin:0">💳 بيع بطاقات SHEIN للعملاء <span class="h-note">{{ $cs['count'] }} بطاقة · {{ $f($cs['usd']) }} $</span></h3>
    <div>المبيعات <b class="num">{{ $f($cs['sales']) }}</b> د.ل</div>
    <div>التكلفة <b class="num minus">−{{ $f($cs['cost']) }}</b> د.ل</div>
    <div>الربح <b class="num {{ $cs['profit'] >= 0 ? 'plus' : 'minus' }}">{{ $f($cs['profit']) }}</b> د.ل <span class="note">(محسوب في صافي الربح)</span></div>
    @can('shein-card-sell')<a class="btn ghost sm" href="{{ route('sg.cards.sell') }}" style="margin-inline-start:auto">بيع بطاقة ←</a>@endcan
</div>
@endif
<div class="side-lay mb">
    <div class="card">
        <h3>صافي الربح {{ $period->unit === 'month' ? 'شهر بشهر' : 'يوم بيوم' }} <span class="h-note">ربح الرحلات + الشحن + بيع البطاقات − المصاريف</span></h3>
        <svg class="chart" data-bars data-h="230" data-chart='{{ json_encode(array_merge($daily, ['unit' => 'د.ل', 'color' => '#12805c', 'soft' => '#8fd0b5', 'highlightLast' => true])) }}'></svg>
    </div>
    <div class="card">
        <h3>المصاريف حسب النوع</h3>
        @forelse ($byDesc as $d)
            <div class="hb"><div class="tt"><span>{{ $d->name }}</span><b class="num">{{ $f($d->total) }}</b></div><div class="b"><i style="width:{{ $d->total / $mx * 100 }}%;background:{{ $cols[$loop->index % 6] }}"></i></div></div>
        @empty
            <div class="note">لا يوجد مصاريف</div>
        @endforelse
        @if ($r['paymentExpenses']['dinar'] - ($r['companyFees'] ?? 0) > 0.009)<div class="hb"><div class="tt"><span>مصروفات الدفع</span><b class="num">{{ $f($r['paymentExpenses']['dinar'] - ($r['companyFees'] ?? 0)) }}</b></div></div>@endif
        @if (($r['companyFees'] ?? 0) > 0)<div class="hb"><div class="tt"><span>أجرة توصيل على الشركة</span><b class="num">{{ $f($r['companyFees']) }}</b></div></div>@endif
    </div>
</div>

<div class="side-lay">
    <div class="card tw">
        <div class="tw-h"><h3>ربح كل رحلة <span class="h-note">من الأحدث</span></h3></div>
        <div class="scroll-x">
        <table class="t"><thead><tr><th>الرحلة</th><th>الحالة</th><th class="n">إيصالات</th><th class="n">للعملاء</th><th class="n">كلفتني</th><th class="n">الربح</th><th>النسبة</th></tr></thead><tbody>
        @forelse ($trips as $t)
            @php $s = $t->summary(); @endphp
            <tr>
                <td><a class="code" href="{{ route('finance.trips.show', $t->id) }}">{{ $t->trip_code }}</a> <span class="note">{{ $t->purchased_at->format('j/n') }}</span></td>
                <td><span class="chip n">{{ $s['status'] ?: '—' }}</span></td>
                <td class="n num">{{ $s['orders'] }}</td>
                <td class="n num">{{ $f($s['sales_dinar']) }}</td>
                <td class="n num">{{ $f($s['cost_dinar']) }} <span class="note">({{ $f($t->cost) }} {{ TS::sign($t->cost_currency) }} × {{ $t->rate }})</span></td>
                <td class="n"><b class="num {{ $s['profit_dinar'] >= 0 ? 'plus' : 'minus' }}">{{ $f($s['profit_dinar']) }}</b></td>
                <td><span class="chip {{ $s['margin'] >= 10 ? 'u' : ($s['margin'] >= 0 ? 'w' : 'd') }}"><span class="num">{{ $s['margin'] }}%</span></span></td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty">لا يوجد رحلات في الفترة</div></td></tr>
        @endforelse
        </tbody></table>
        </div>
    </div>

    <div class="card">
        <h3>الشركاء @can('partner-create')<button class="btn ghost sm" data-modal="mNewPartner">+ شريك</button>@endcan</h3>
        <div class="pt surplus mb">
            <div style="font-size:14px;color:var(--ink-2)">الفائض الذي لم يُقسّم (من البداية)</div>
            <div class="v"><span class="num">{{ $f($surplus['surplus']) }}</span> <small style="font-size:14px;color:var(--ink-3);font-weight:400">د.ل</small></div>
            <div class="note">صافي الربح {{ $f($surplus['net']) }} − توزيعات (والسحب اليومي دين ينخصم من النصيب) {{ $f($surplus['drawn']) }}</div>
            <div class="note">من تاريخ {{ $surplus['from'] ?? 'البداية' }}@if (($surplus['opening'] ?? 0) != 0) · فيه رصيد افتتاحي {{ $f($surplus['opening']) }}@endif</div>
            @role('Admin')<details style="margin-top:8px"><summary class="note" style="cursor:pointer">⚙ تعديل حساب الفائض</summary>
                <form method="post" action="{{ route('finance.partners.surplus') }}" style="margin-top:8px">@csrf
                    <div class="grid g2"><div><label class="l">يبدا من تاريخ</label><input type="date" name="surplus_from" class="inp" value="{{ \App\Services\AppSettings::get('surplus_from') }}"><small class="note">فاضي = من تاريخ رحلة B-0393</small></div>
                    <div><label class="l">رصيد افتتاحي (د.ل)</label><input name="surplus_opening" class="inp numr" value="{{ \App\Services\AppSettings::get('surplus_opening', 0) }}"><small class="note">يتزاد على الفائض (أو سالب ينقص)</small></div></div>
                    <button class="btn sm mt">حفظ</button></form></details>@endrole
            @can('partner-create')@if ($partners->count())<button class="btn green mt" data-modal="mDist">توزيع الفائض</button>@endif @endcan
        </div>
        <div class="grid g2">
            @foreach ($partners as $pt)
                @php $w = $ptx->where('partner_id', $pt->id)->where('type', 'withdrawal')->sum('s'); $d = $ptx->where('partner_id', $pt->id)->where('type', 'distribution')->sum('s'); $debt = \App\Models\PartnerTransaction::debtOf($pt->id); $pe = $ptx->where('partner_id', $pt->id)->where('type', 'expense')->sum('s'); @endphp
                <div class="pt"><div class="nm"><span class="av" style="width:30px;height:30px;font-size:13px;background:{{ $partnerCols[$loop->index % 4] }}">{{ mb_substr($pt->name, 0, 1) }}</span>{{ $pt->name }}</div>
                    <div class="ln"><span>سحب يومي</span><b class="num">{{ $f($w) }}</b></div><div class="ln"><span>توزيع</span><b class="num">{{ $f($d) }}</b></div><div class="ln"><span>مصروفات شخصية (الفترة)</span><b class="num">{{ $f($pe) }}</b></div><div class="ln"><span>دين عليه حالياً <small class="note">(سحب + مصروفات − المسدد)</small></span><b class="num {{ $debt > 0 ? 'minus' : '' }}">{{ $f($debt) }}</b></div><div class="ln tot" style="font-size:14px;border-top-width:1px"><span>المجموع (سحب + توزيع + مصروفات)</span><b class="num">{{ $f($w + $d + $pe) }}</b></div></div>
            @endforeach
        </div>
        @if ($recentTx->count())
            <div style="margin-top:14px;font-weight:700;font-size:14px">آخر الحركات</div>
            @foreach ($recentTx as $tx)
                <div class="ln" style="font-size:13.5px"><span>{{ optional($tx->partner)->name }} · {{ $statusTx[$tx->type] ?? $tx->type }} <span class="note">{{ $tx->date->format('j/n') }}</span></span><span style="display:flex;gap:6px;align-items:center"><b class="num">{{ $f($tx->amount) }}</b>@can('partner-delete')<form method="post" action="{{ route('finance.partners.transactions.destroy', $tx) }}" data-confirm="حذف الحركة وإرجاع المبلغ؟">@csrf @method('DELETE')<button class="more" style="width:24px;height:24px;font-size:13px">×</button></form>@endcan</span></div>
            @endforeach
        @endif
    </div>
</div>

@can('partner-create')
<div class="modal-bg" id="mDist"><div class="modal" style="max-width:560px">
    <h3>توزيع الفائض</h3>
    <div class="hint mb">الفائض {{ $f($surplus['surplus']) }} د.ل · مقسوم بالتساوي، ويمكنك التعديل. لكل شريك عليه دين (مصروفات شخصية) يمكنك خصمه من نصيبه.</div>
    <form method="post" action="{{ route('finance.partners.distribute') }}" id="distF">
        @csrf
        <div class="f"><label class="l">البند / النوع</label><input name="note" class="inp" placeholder="مثلاً: فائض شهر 10، أرباح رحلات…"></div>
        <div class="grid g2 f">
            <div><label class="l">العملة</label><div class="opts"><label><input type="radio" name="currency" value="dinar" checked><span>دينار</span></label><label><input type="radio" name="currency" value="dollar"><span>دولار</span></label></div></div>
            <div><label class="l">المبلغ الكلي اللي ناخدوه</label><div style="display:flex;gap:6px"><input id="dTot" class="inp numr" value="{{ floor(max(0, $surplus['surplus'])) }}"><button type="button" class="btn ghost sm" id="dEq">قسّم بالتساوي</button></div><small class="note">حسب حركة الشركة: {{ $f($surplus['surplus']) }} د.ل · أو اكتب أي مبلغ</small></div>
        </div>
        @foreach ($partners as $pt)
            @php $debt = max(0, \App\Models\PartnerTransaction::debtOf($pt->id)); $share = floor(max(0, $surplus['surplus']) / max(1, $partners->count())); @endphp
            <div class="f" style="border:1px solid var(--line);border-radius:12px;padding:10px 12px">
                <label class="l">{{ $pt->name }} — نصيبه</label><input name="amounts[{{ $pt->id }}]" class="inp numr dsh" data-debt="{{ $debt }}" value="{{ $share }}">
                @if ($debt > 0)
                    <label class="ck" style="margin-top:6px"><input type="checkbox" name="deduct[{{ $pt->id }}]" value="1" class="dded"> اخصم دينه ({{ $f($debt) }} د.ل) من نصيبه</label>
                @else<div class="note" style="margin-top:4px">لا يوجد عليه دين</div>@endif
                <div class="note dout" style="margin-top:4px"></div>
            </div>
        @endforeach
        <div class="grid g2 f">
            <div><label class="l">من خزينة</label><select name="payment_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == $cashId)>{{ $pm->name }}</option>@endforeach</select></div>
            <div><label class="l">التاريخ</label><input type="date" name="date" class="inp" value="{{ now()->toDateString() }}"></div>
        </div>
        <div style="display:flex;gap:8px"><button class="btn green">توزيع</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
    <div class="note mt">بدون صح على "اخصم دينه": الدين يتأجل ويقعد عليه.</div>
    <script>
    (function () {
      const f = document.getElementById('distF'), b = document.getElementById('dEq'); if (!b) return;
      b.onclick = () => { const ins = f.querySelectorAll('.dsh'), t = parseFloat(document.getElementById('dTot').value) || 0; ins.forEach(i => { i.value = Math.floor(t / ins.length * 100) / 100; i.dispatchEvent(new Event('input')); }); };
      f.querySelectorAll('input[name=currency]').forEach(r => r.onchange = () => f.querySelectorAll('.dded').forEach(c => { c.disabled = r.value === 'dollar' && r.checked; if (c.disabled) c.checked = false; }));
    })();
    </script>
</div></div>
<div class="modal-bg" id="mNewPartner"><div class="modal">
    <h3>شريك جديد</h3>
    <form method="post" action="{{ route('finance.partners.store') }}">
        @csrf
        <div class="f"><label class="l">الاسم</label><input name="name" class="inp" required></div>
        <div class="f"><label class="l">الهاتف</label><input name="phone" class="inp numr"></div>
        <div style="display:flex;gap:8px"><button class="btn">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
@endcan
@endsection
@push('script')
    <script>
    (function () { const f = document.getElementById('distF'); if (!f) return;
      const upd = () => f.querySelectorAll('.dsh').forEach(i => { const box = i.closest('.f'), cb = box.querySelector('.dded'), sh = parseFloat(i.value) || 0, d = cb && cb.checked ? Math.min(+i.dataset.debt, sh) : 0;
        box.querySelector('.dout').textContent = 'يُصرف له نقداً: ' + SG.fmt(sh - d) + ' د.ل' + (d ? ' (بعد خصم ' + SG.fmt(d) + ')' : ''); });
      f.addEventListener('input', upd); f.addEventListener('change', upd); upd(); })();
    </script>
@endpush
