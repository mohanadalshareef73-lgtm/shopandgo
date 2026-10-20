@extends('sg.layout')
@section('title', 'إيصال دفع مواقع')
@php use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); $profitM = $kpi['profit']; @endphp
@push('style')
<style>
.cs { position: relative; } .cs .list { position: absolute; z-index: 20; right: 0; left: 0; top: 100%; margin-top: 4px; background: #fff; border-radius: 14px; box-shadow: 0 14px 34px rgba(22,26,46,.16); max-height: 260px; overflow: auto; padding: 6px; display: none; }
.cs.open .list { display: block; } .cs .opt { padding: 9px 12px; border-radius: 10px; cursor: pointer; display: flex; justify-content: space-between; gap: 8px; } .cs .opt:hover { background: #f3f4f8; }
.cs .opt.new { color: var(--s1); font-weight: 800; }
.cf { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.cf .inp { height: 36px; }
</style>
@endpush
@section('content')
<div class="crumb"><a href="{{ route('sg.orders.index') }}">الطلبات</a> › إيصال دفع مواقع</div>
<div class="top"><div><h1>🌐 إيصال دفع مواقع</h1><div class="sub">العميل يعطيك فاتورة (مثلاً حساب صيني)، تخلصها بالفيزا، وتاخذ عمولة · الربح يتسجل</div></div><div class="acts"><button class="sg-burger">☰</button></div></div>
<div style="display:inline-flex;background:#fff;border-radius:99px;padding:5px;gap:4px;margin-bottom:16px;flex-wrap:wrap">
    <a href="{{ route('sg.orders.create') }}" style="padding:9px 18px;border-radius:99px">🛍 طلب مشتريات</a>
    @can('shein-card-sell')<a href="{{ route('sg.cards.sell') }}" style="padding:9px 18px;border-radius:99px">💳 بيع بطاقة SHEIN</a>@endcan
    <a style="padding:9px 18px;border-radius:99px;background:var(--brand);color:#fff;font-weight:800">🌐 دفع مواقع</a>
</div>

<div class="grid g4 mb">
    <div class="card tile"><div class="t-lbl">إيصالات الشهر</div><div class="t-val"><span class="num">{{ $kpi['n'] }}</span></div></div>
    <div class="card tile"><div class="t-lbl">قيمتها</div><div class="t-val"><span class="num">{{ $f($kpi['sales']) }}</span><small>د.ل</small></div>@if ($kpi['salesUsd'] > 0)<div class="note num">+ {{ $f($kpi['salesUsd']) }} $</div>@endif</div>
    @can('profit-view')<div class="card tile"><div class="t-lbl">💰 ربح الشهر</div><div class="t-val"><span class="num {{ $profitM >= 0 ? 'plus' : 'minus' }}">{{ $f($profitM) }}</span><small>د.ل</small></div></div>@endcan
    <a class="card tile act" href="?tab=nocost"><div class="t-lbl">⏳ تستنى الكلفة</div><div class="t-val"><span class="num {{ $noCost ? 'minus' : '' }}">{{ $noCost }}</span></div></a>
</div>

<div class="grid" style="grid-template-columns:minmax(300px,1fr) minmax(300px,1.4fr);gap:16px;align-items:start">
<form class="card" method="post" action="{{ route('sg.sitepay.store') }}" id="spF">@csrf
    <h3>إيصال جديد</h3>
    <div class="f"><label class="l">العميل — اكتب كوده أو اسمه أو رقمه</label>
        <div class="cs" id="cs"><input type="hidden" name="customer_id" id="cid" value="{{ old('customer_id') }}">
            <input class="inp" id="csq" autocomplete="off" placeholder="مثلاً 2001 أو 0921234567…" style="font-size:16px"><div class="list" id="csl"></div></div>
        <div class="note" id="csPicked" style="margin-top:6px"></div></div>
    <div id="newC" hidden>
        <div class="grid g2 f"><div><label class="l">اسم العميل الجديد</label><input name="name" class="inp" value="{{ old('name') }}"></div><div><label class="l">رقمه</label><input name="phone" id="nph" class="inp numr" value="{{ old('phone') }}" inputmode="tel"></div></div>
    </div>
    <div class="f"><label class="l">الموقع اللي دفعناله</label>
        <select name="site" class="inp" required style="font-size:16px"><option value="">— اختار —</option>@foreach ($sites as $st)<option @selected(old('site') === $st)>{{ $st }}</option>@endforeach</select>
        @if (!$sites)<div class="note" style="color:var(--down)">ضيف المواقع أول من المربع تحت ↓</div>@endif</div>
    <div class="grid g2 f">
        <div><label class="l">قيمة الفاتورة</label><input name="bill_usd" id="billUsd" class="inp numr" value="{{ old('bill_usd') }}" inputmode="decimal" style="font-size:18px;font-weight:800" placeholder="مثلاً 1500"></div>
        <div><label class="l">عملة الفاتورة</label><select name="bill_cur" id="billCur" class="inp" style="font-size:15px">@foreach ($billCurs as $bc)<option @selected(old('bill_cur') === $bc)>{{ $bc }}</option>@endforeach</select></div>
    </div>
    <div class="grid g3 f">
        <div><label class="l">القيمة على العميل</label><input name="total" id="tot" class="inp numr" value="{{ old('total') }}" inputmode="decimal" required style="font-size:20px;font-weight:800"></div>
        <div><label class="l">استلمت منه بـ</label><select name="recv_cur" id="recvCur" class="inp" style="font-size:15px"><option value="dinar" @selected(old('recv_cur') !== 'dollar')>دينار (د.ل)</option><option value="dollar" @selected(old('recv_cur') === 'dollar')>دولار ($)</option></select></div>
        <div><label class="l">دفع توا</label><input name="paid" id="pd" class="inp numr" value="{{ old('paid') }}" inputmode="decimal" style="font-size:20px;font-weight:800"></div>
    </div>
    <div class="grid g2 f"><div><label class="l">دخلت في خزينة</label><select name="payment_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == $cashId)>{{ $pm->name }}</option>@endforeach</select></div>
        <div><label class="l">ملاحظة (اختياري)</label><input name="note" class="inp" value="{{ old('note') }}" placeholder="مثلاً رقم الطلب في الموقع"></div></div>
    <details class="card" style="background:#f3f6ff;padding:10px 12px;margin-bottom:12px" @if (old('use_calc')) open @endif>
        <summary style="cursor:pointer;font-weight:800"><label onclick="event.stopPropagation()"><input type="checkbox" name="use_calc" value="1" id="useCalc" @checked(old('use_calc'))> 💵 حساب بالعمولة + خلصتها بالفيزا (اختياري)</label></summary>
        <div class="note" style="margin:6px 0">للفاتورة بالدولار: يحسب القيمة على العميل بالدينار، ويسحب الدولار من خزينة الفيزا</div>
        <div class="grid g2 f">
            <div><label class="l">سعر الدولار على العميل</label><input name="sale_rate" id="saleRate" class="inp numr" value="{{ old('sale_rate', $saleRate) }}" inputmode="decimal"></div>
            <div><label class="l">العمولة %</label><input name="comm_pct" id="commPct" class="inp numr" value="{{ old('comm_pct', $commPct) }}" inputmode="decimal"></div>
        </div>
        <div class="note" id="billCalc"></div>
        <label class="ck mt"><input type="checkbox" name="paid_now" value="1" id="paidNow" checked> خلصتها توا بالفيزا — اسحب الدولار من الخزينة</label>
        <div class="grid g2 f" id="paidNowBox"><div><label class="l">من خزينة الدولار</label><select name="cost_pid" class="inp">@foreach ($usdPays->isNotEmpty() ? $usdPays : $payments as $pm)<option value="{{ $pm->id }}">{{ $pm->name }} ({{ $f(\App\Services\TreasuryService::balance('dollar', $pm->id)) }} $)</option>@endforeach</select></div>
            <div><label class="l">دفعت فعلاً ($) <span class="note">فارغ = نفس الفاتورة</span></label><input name="cost_usd" class="inp numr" value="{{ old('cost_usd') }}" inputmode="decimal"></div></div>
    </details>
    <button type="button" class="btn ghost sm" onclick="document.getElementById('pd').value=document.getElementById('tot').value">= دفع الكل</button>
    <label class="ck mt"><input type="checkbox" name="send_whatsapp" value="1" checked> ابعتله الإيصال على الواتساب</label>
    @include('sg.partials.dup-warn')
    <button class="btn green block lg mt">✓ سجّل الإيصال</button>
    <div class="note mt">الكلفة (قداش دفعنا للموقع) تنكتب بعدين من الجدول ←</div>
</form>

<div>
    <nav class="tabs">
        <a href="?tab=new" class="{{ $tab !== 'nocost' ? 'on' : '' }}">كل الإيصالات</a>
        <a href="?tab=nocost" class="{{ $tab === 'nocost' ? 'on' : '' }}">⏳ تستنى الكلفة <span class="b {{ $noCost ? 'r' : '' }}">{{ $noCost }}</span></a>
    </nav>
    <div class="card tw"><div class="scroll-x">
    <table class="t"><thead><tr><th>الإيصال</th><th>العميل</th><th>الموقع</th><th class="n">القيمة</th><th class="n">المدفوع</th><th>الكلفة</th>@can('profit-view')<th class="n">الربح</th>@endcan</tr></thead><tbody>
    @forelse ($rows as $o)
        @php $pr = $o->site_cost_lyd !== null ? (float) $o->total_price - (float) $o->site_cost_lyd : null; @endphp
        <tr>
            <td><a class="code" href="{{ route('sg.orders.show', $o) }}">{{ $o->code }}</a><small style="display:block">{{ $o->created_at->format('m-d g:i a') }} · {{ optional($o->createdUser)->name }}</small></td>
            <td class="cell2"><b>{{ optional($o->customer)->name }}</b><small class="num">{{ optional($o->customer)->code }} · {{ optional($o->customer)->phone_1 }}</small></td>
            <td><b>{{ $o->site_name }}</b>@if ($o->site_bill_usd)<small style="display:block" class="num">فاتورة {{ $f($o->site_bill_usd) }} {{ $o->site_bill_currency ?? '$' }}</small>@endif @if ($o->site_cost_pid)<small style="display:block;color:var(--up)">✓ انسحبت من الخزينة</small>@endif</td>
            <td class="n num">{{ $f($o->total_price) }} <small>{{ $o->currency_sign ?: 'د.ل' }}</small></td>
            <td class="n num">{{ $f($o->paid_price) }}@if ($o->remaining() > 0.009)<small style="display:block;color:var(--down)">باقي {{ $f($o->remaining()) }}</small>@endif</td>
            <td>@if ($o->site_cost !== null)
                    <b class="num">{{ $f($o->site_cost) }} {{ $o->site_cost_currency === 'dollar' ? '$' : 'د.ل' }}</b>@if ($o->site_cost_currency === 'dollar')<small style="display:block">× {{ rtrim(rtrim(number_format($o->site_cost_rate, 3), '0'), '.') }} = {{ $f($o->site_cost_lyd) }} د.ل</small>@endif
                    @can('order-edit')<details><summary class="note" style="cursor:pointer">✎ تعديل</summary>@endcan
                @endif
                @can('order-edit')
                <form method="post" action="{{ route('sg.sitepay.cost', $o) }}" class="cf">@csrf
                    <input name="cost" class="inp numr" style="width:90px" placeholder="الكلفة" value="{{ $o->site_cost }}" inputmode="decimal" required>
                    <select name="currency" class="inp" style="width:80px" onchange="this.form.rate.hidden=this.value!=='dollar'"><option value="dollar" @selected($o->site_cost_currency !== 'dinar')>$</option><option value="dinar" @selected($o->site_cost_currency === 'dinar')>د.ل</option></select>
                    <input name="rate" class="inp numr" style="width:80px" placeholder="سعر $" value="{{ $o->site_cost_rate ?: $rate }}" @if ($o->site_cost_currency === 'dinar') hidden @endif>
                    <select name="pid" class="inp" style="width:130px" title="لو تبيها تنسحب من الخزينة"><option value="">ما تنسحبش</option>@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected((int) $o->site_cost_pid === (int) $pm->id)>من {{ $pm->name }}</option>@endforeach</select>
                    <button class="btn sm">✓</button>
                </form>
                @endcan
                @if ($o->site_cost !== null) @can('order-edit')</details>@endcan @endif
            </td>
            @can('profit-view')<td class="n num">
                @if ($o->site_profit !== null)<b class="plus">{{ $f($o->site_profit) }} {{ $o->site_profit_currency === 'dollar' ? '$' : 'د.ل' }}</b>@if ($o->site_profit_move)<small style="display:block;color:var(--up)">✓ انتقل للخزينة</small>@endif
                @elseif ($pr !== null)<b class="{{ $pr >= 0 ? 'plus' : 'minus' }}">{{ $f($pr) }}</b>@else<span class="note">—</span>@endif
                @role('Admin')<button type="button" class="btn ghost sm" style="display:block;margin-top:4px" onclick="openProfit({{ json_encode(['id' => $o->id, 'code' => $o->code, 'amt' => $o->site_profit, 'cur' => $o->site_profit_currency ?: 'dinar', 'from' => optional($o->paymentMethod)->name . ' ' . ($o->currency === 'dollar' ? '$' : 'د.ل'), 'fromCur' => $o->currency ?: 'dinar']) }})">{{ $o->site_profit !== null ? '✎ الربح' : '➕ إضافة ربح' }}</button>@endrole
            </td>@endcan
        </tr>
    @empty
        <tr><td colspan="7"><div class="empty"><span class="ic">🌐</span>ما فيش إيصالات دفع مواقع</div></td></tr>
    @endforelse
    </tbody></table></div>
    @include('sg.partials.pager', ['p' => $rows])
    </div>

    @can('order-edit')
    <form class="card mt" method="post" action="{{ route('sg.sitepay.sites') }}">@csrf
        <h3>🌐 المواقع/المنصات اللي ندفعولها</h3>
        <textarea name="sites" class="inp" rows="3" placeholder="كل موقع في سطر — مثلاً: Temu، AliExpress، Netflix">{{ implode("\n", $sites) }}</textarea>
        <label class="l mt">عملات الفواتير</label>
        <textarea name="bill_currencies" class="inp" rows="2" placeholder="يوان، دولار، درهم…">{{ implode('، ', $billCurs) }}</textarea>
        <button class="btn mt">حفظ</button>
    </form>
    @endcan
</div>
</div>
@role('Admin')
<button type="button" id="prOpener" data-modal="prModal" hidden></button>
<div class="modal-bg" id="prModal"><div class="modal" style="max-width:440px">
    <h3>➕ ربح الإيصال <span class="num" id="prCode"></span></h3>
    <form method="post" id="prF" data-base="{{ route('sg.sitepay.profit', 0) }}">@csrf
        <div class="grid g2 f"><div><label class="l">قيمة الربح</label><input name="amount" id="prAmt" class="inp numr" inputmode="decimal" required style="font-size:20px;font-weight:800"></div>
            <div><label class="l">العملة</label><select name="currency" id="prCur" class="inp"><option value="dinar">دينار</option><option value="dollar">دولار</option></select></div></div>
        <div class="f"><label class="l">يمشي لخزينة</label><select name="target" id="prTo" class="inp">
            @foreach ($payments as $pm)<option value="{{ $pm->id }}:dinar" data-cur="dinar">{{ $pm->name }} · دينار</option><option value="{{ $pm->id }}:dollar" data-cur="dollar">{{ $pm->name }} · دولار</option>@endforeach
        </select></div>
        <div class="f" id="prRateBox"><label class="l">سعر الصرف (دينار للدولار)</label><input name="rate" id="prRate" class="inp numr" value="{{ $rate }}" inputmode="decimal"></div>
        <label class="ck"><input type="checkbox" name="move" value="1" id="prMove" checked> انقله من خزينة لخزينة (ينقص من <b id="prFrom"></b> ويزيد في اللي اخترتها)</label>
        <div class="note" id="prCalc" style="margin:6px 0"></div>
        <div class="note mb">اكتب 0 باش تشيل الربح · التعديل يصلح الخزاين بروحه</div>
        <div style="display:flex;gap:8px"><button class="btn green">حفظ الربح</button><button type="button" class="btn ghost" data-close>رجوع</button></div>
    </form>
</div></div>
<script>
(function () {
  let FROMCUR = 'dinar';
  const cur = document.getElementById('prCur'), to = document.getElementById('prTo'), rb = document.getElementById('prRateBox'), rate = document.getElementById('prRate'), amt = document.getElementById('prAmt'), calc = document.getElementById('prCalc');
  function pickTo() { const o = [...to.options].find(x => x.dataset.cur === cur.value); if (o && to.selectedOptions[0].dataset.cur !== cur.value) to.value = o.value; }
  function upd() {
    const tc = to.selectedOptions[0].dataset.cur, need = cur.value !== tc || cur.value !== FROMCUR; rb.hidden = !need;
    const a = parseFloat(amt.value) || 0, r = parseFloat(rate.value) || 0;
    const cv = (x, f, t) => f === t ? x : (f === 'dollar' ? x * r : (r ? x / r : 0));
    calc.textContent = a ? ('يمشي ' + cv(a, cur.value, tc).toFixed(2) + (tc === 'dollar' ? ' $' : ' د.ل') + (cur.value !== FROMCUR ? ' · ينقص ' + cv(a, cur.value, FROMCUR).toFixed(2) + (FROMCUR === 'dollar' ? ' $' : ' د.ل') : '')) : '';
  }
  cur.addEventListener('change', () => { pickTo(); upd(); }); [to, rate, amt].forEach(x => x.addEventListener('input', upd)); to.addEventListener('change', upd);
  window.openProfit = function (o) {
    const f = document.getElementById('prF'); f.action = f.dataset.base.replace(/\/0\/profit$/, '/' + o.id + '/profit');
    document.getElementById('prCode').textContent = o.code; amt.value = o.amt || ''; cur.value = o.cur || 'dinar'; FROMCUR = o.fromCur || 'dinar';
    document.getElementById('prFrom').textContent = o.from || ''; pickTo(); upd(); document.getElementById('prOpener').click();
  };
})();
</script>
@endrole
@endsection
@push('script')
@php $custJson = $customers->map(function ($c) { return ['id' => $c->id, 'n' => $c->name, 'c' => (string) $c->code, 'p' => (string) $c->phone_1]; })->values(); @endphp
<script>
(function () {
  const C = {!! json_encode($custJson, JSON_UNESCAPED_UNICODE) !!};
  const q = document.getElementById('csq'), cs = document.getElementById('cs'), list = document.getElementById('csl'), cid = document.getElementById('cid'), nc = document.getElementById('newC'), picked = document.getElementById('csPicked');
  function render() {
    const t = q.value.trim().toLowerCase();
    const exact = t ? C.filter(c => c.c.toLowerCase() === t) : [];
    const rows = exact.length ? exact : (t ? C.filter(c => (c.n + ' ' + c.c + ' ' + c.p).toLowerCase().includes(t)) : []).slice(0, 30);
    list.innerHTML = rows.map(c => `<div class="opt" data-id="${c.id}"><span>${c.n}</span><small class="num">${c.c} · ${c.p}</small></div>`).join('') + (t ? '<div class="opt new" data-new="1">➕ عميل جديد</div>' : '');
    cs.classList.toggle('open', !!t);
  }
  function pick(c) { cid.value = c.id; q.value = c.n + ' · ' + c.c; picked.innerHTML = '✓ <b>' + c.n + '</b> · كود ' + c.c + ' · ' + c.p; nc.hidden = true; cs.classList.remove('open'); }
  q.addEventListener('input', () => { cid.value = ''; picked.textContent = ''; render(); });
  q.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); const f = list.querySelector('.opt[data-id]'); if (f) pick(C.find(c => c.id == f.dataset.id)); } });
  list.addEventListener('mousedown', e => { const o = e.target.closest('.opt'); if (!o) return;
    if (o.dataset.new) { cid.value = ''; nc.hidden = false; cs.classList.remove('open'); picked.textContent = '➕ عميل جديد — اكتب اسمه ورقمه'; if (/^\d{7,}$/.test(q.value.trim())) document.getElementById('nph').value = q.value.trim(); return; }
    pick(C.find(c => c.id == o.dataset.id)); });
  document.addEventListener('click', e => { if (!cs.contains(e.target)) cs.classList.remove('open'); });
  document.getElementById('spF').addEventListener('submit', e => { if (!cid.value && nc.hidden) { e.preventDefault(); alert('اختار العميل أو اضغط «عميل جديد»'); } });
  @if (old('name'))nc.hidden = false;@endif
  const bu = document.getElementById('billUsd'), sr = document.getElementById('saleRate'), cp = document.getElementById('commPct'), tot = document.getElementById('tot'), bc = document.getElementById('billCalc'), RATE = {{ (float) $rate }};
  const uc = document.getElementById('useCalc');
  function calc() { const b = parseFloat(bu.value) || 0; if (!b || !uc.checked) { bc.textContent = ''; return; } const r = parseFloat(sr.value) || 0, c = parseFloat(cp.value) || 0;
    const base = b * r, comm = base * c / 100, t = Math.ceil(base + comm); tot.value = t;
    const cost = b * RATE; bc.innerHTML = `${b}$ × ${r} = <b>${Math.round(base)}</b> + عمولة ${c}% (${Math.round(comm)}) = <b>${t} د.ل</b>` + (RATE ? ` · كلفتنا ≈ ${Math.round(cost)} د.ل · الربح ≈ <b style="color:var(--up)">${Math.round(t - cost)} د.ل</b>` : ''); }
  [bu, sr, cp].forEach(i => i.addEventListener('input', calc)); uc.addEventListener('change', calc);
  const pn = document.getElementById('paidNow'); pn.addEventListener('change', () => document.getElementById('paidNowBox').hidden = !pn.checked);
})();
</script>
@endpush
