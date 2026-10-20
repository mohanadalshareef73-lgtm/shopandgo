@extends('sg.layout')
@section('title', 'بيع بطاقة SHEIN')
@section('bodyClass', 'soft')
@php use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); $groups = $cards->groupBy(fn ($c) => (string) (float) $c->face_value); $canProfit = auth()->user()->can('profit-view'); @endphp
@push('style')
<style>
.tabs2 { display: inline-flex; background: #fff; border-radius: 99px; padding: 5px; gap: 4px; box-shadow: var(--sh, 0 2px 8px rgba(0,0,0,.04)); margin-bottom: 16px; }
.tabs2 a { padding: 9px 18px; border-radius: 99px; font-size: 14px; color: var(--ink-2); }
.tabs2 a.on { background: var(--brand); color: #fff; font-weight: 700; }
.cs { position: relative; } .cs .list { position: absolute; z-index: 20; right: 0; left: 0; top: 100%; margin-top: 4px; background: #fff; border-radius: 16px; box-shadow: 0 14px 34px rgba(22,26,46,.16); max-height: 260px; overflow: auto; padding: 6px; display: none; }
.cs.open .list { display: block; } .cs .opt { padding: 9px 12px; border-radius: 10px; cursor: pointer; font-size: 14px; display: flex; justify-content: space-between; gap: 8px; } .cs .opt:hover { background: #f3f4f8; } .cs .opt small { color: var(--ink-3); font-family: InterNum; }
.vgrp { margin-bottom: 14px; } .vgrp h4 { margin: 0 0 8px; font-size: 14px; display: flex; gap: 8px; align-items: center; }
.cardsel { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 10px; }
.cardsel label { border: 1.5px solid var(--line); border-radius: 16px; padding: 12px 14px; cursor: pointer; background: #fff; display: block; position: relative; transition: .15s; }
.cardsel label:hover { border-color: #c7cbe0; }
.cardsel input { position: absolute; opacity: 0; }
.cardsel label:has(input:checked) { border-color: var(--brand); background: #f6f7fc; box-shadow: 0 0 0 3px rgba(38,44,74,.08); }
.cardsel .no { font-family: InterNum; font-weight: 700; font-size: 15px; letter-spacing: 1px; direction: ltr; text-align: right; }
.cardsel .v { font-family: InterNum; font-size: 22px; font-weight: 700; }
.cardsel .m { font-size: 12px; color: var(--ink-3); margin-top: 4px; }
.cardsel .tick { position: absolute; left: 12px; top: 12px; width: 22px; height: 22px; border-radius: 50%; border: 2px solid var(--line); display: grid; place-items: center; font-size: 12px; color: #fff; }
.cardsel label:has(input:checked) .tick { background: var(--brand); border-color: var(--brand); }
.selrow { display: grid; grid-template-columns: 1fr 110px 30px; gap: 8px; align-items: center; padding: 8px 0; border-bottom: 1px dashed var(--line); }
.selrow .no { font-family: InterNum; direction: ltr; text-align: right; }
.profit { font-size: 12px; } .profit.neg { color: var(--down); } .profit.pos { color: var(--up); }
</style>
@endpush
@section('content')
<div class="crumb"><a href="{{ route('sg.orders.index') }}">الطلبات</a> › إيصال جديد › بيع بطاقة SHEIN</div>
<div class="top"><div><h1>إيصال جديد</h1><div class="sub">بيع بطاقة SHEIN جديدة لعميل · تُرسل له برقمها ورمزها عبر واتساب</div></div><div class="acts"><a class="btn ghost" href="{{ route('sg.shein') }}">💳 كل البطاقات</a><button class="sg-burger">☰</button></div></div>
<div class="tabs2"><a href="{{ route('sg.orders.create') }}">🛍 طلب مشتريات</a><a class="on" href="{{ route('sg.cards.sell') }}">💳 بيع بطاقة SHEIN</a><a href="{{ route('sg.sitepay') }}">🌐 دفع مواقع</a></div>

<form method="post" action="{{ route('sg.cards.sell.store') }}" id="sf">
@csrf
<div class="grid" style="grid-template-columns:1.6fr 1fr;gap:16px;align-items:start">
    <div>
        <div class="card mb">
            <label class="l">العميل</label>
            <div class="cs" id="cs">
                <input type="hidden" name="customer_id" id="cid" value="{{ old('customer_id', optional($pre)->id) }}">
                <input class="inp" id="csq" autocomplete="off" placeholder="اكتب الاسم أو الكود أو الهاتف…" value="{{ $pre ? $pre->name . ' - ' . $pre->code . ' - ' . $pre->phone_1 : '' }}">
                <div class="list" id="csl"></div>
            </div>
        </div>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><h3 style="margin:0">البطاقات المتاحة <span class="h-note">{{ $cards->count() }} بطاقة جديدة · {{ $f($cards->sum('face_value')) }} $</span></h3><input class="inp" id="flt" placeholder="آخر 4 أرقام…" style="width:150px;height:38px"></div>
            @forelse ($groups as $val => $g)
                <div class="vgrp"><h4><span class="chip b">{{ $f($val) }} $</span><span class="note">{{ $g->count() }} بطاقة</span></h4>
                    <div class="cardsel">
                        @foreach ($g as $c)
                            <label data-last="{{ substr((string) $c->card_no, -4) }}"><input type="checkbox" name="cards[]" value="{{ $c->id }}" data-v="{{ (float) $c->face_value }}" @if ($canProfit) data-cost="{{ (float) $c->cost_dinar }}" data-costusd="{{ \App\Services\CardSaleService::costUsd($c, $mrate) }}" @endif data-no="{{ $c->masked() }}" @checked(in_array($c->id, (array) old('cards', [])))><span class="tick">✓</span>
                                <div class="no">{{ $c->masked() }}</div><div class="v">{{ $f($c->face_value) }} <small style="font-size:13px">$</small></div>
                                <div class="m">@if ($canProfit)التكلفة {{ $f($c->cost_dinar) }} د.ل @if (($cu = \App\Services\CardSaleService::costUsd($c, $mrate)) !== null)(≈ {{ $f($cu) }} $)@endif · الدولار عليك {{ number_format($c->costPerDollar(), 2) }} د.ل · @endif{{ optional($c->purchased_at)->format('Y-m-d') }}{{ $c->expires_at ? ' · تنتهي ' . $c->expires_at->format('Y-m-d') : '' }}</div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="empty"><span class="ic">💳</span>لا توجد بطاقات جديدة متاحة للبيع · <a href="{{ route('sg.finance', ['tab' => 'usd']) }}" style="text-decoration:underline">اشترِ بطاقات</a></div>
            @endforelse
        </div>
    </div>
    <div class="card sticky">
        <h3>الإيصال</h3>
        <div id="selBox"><div class="note" id="selEmpty">اختر بطاقة من القائمة</div></div>
        <div class="note mt">سعر الدولار المقترح للبيع: <b class="num">{{ $rate ?: '—' }}</b> د.ل · سعر السوق (آخر شراء): <b class="num">{{ $mrate ?: '—' }}</b> @can('system-edit')<a href="{{ route('sg.settings', ['tab' => 'numbers']) }}" style="color:var(--s1)">تغيير</a>@endcan</div>
        <div class="ln tot mt" style="display:flex;justify-content:space-between;font-size:16px"><span>الإجمالي</span><b class="num" id="tot">0</b></div>
        <div class="note" id="totx" style="display:flex;justify-content:space-between"></div>
        <div class="grid g2 mt"><div><label class="l">المدفوع الآن</label><input name="paid" id="paid" class="inp numr" value="{{ old('paid', 0) }}" inputmode="decimal"></div>
            <div><label class="l">طريقة الدفع</label><select name="payment_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == $cashId)>{{ $pm->name }}</option>@endforeach</select></div></div>
        <div class="note" id="due"></div>
        <div class="f mt"><label class="l">ملاحظة (اختياري)</label><input name="note" class="inp" value="{{ old('note') }}"></div>
        @role('Admin')<label class="ck"><input type="checkbox" name="send_whatsapp" value="1" checked> إرسال البطاقة للعميل عبر واتساب (الرقم والرمز)</label>@else<div class="note">📲 البطاقة (الرقم والرمز) تمشي للعميل على الواتساب بروحها</div>@endrole
        <button class="btn green block lg mt" id="sb" disabled>حفظ الإيصال</button>
    </div>
</div>
</form>

@if ($recent->count())
<div class="card mt">
    <h3>آخر البطاقات المباعة</h3>
    <div class="scroll-x"><table class="t"><thead><tr><th>البطاقة</th><th>العميل</th><th>الإيصال</th>@if ($canProfit)<th class="n">التكلفة</th>@endif<th class="n">سعر البيع</th>@if ($canProfit)<th class="n">الربح</th>@endif<th>البائع</th><th>الإرسال</th></tr></thead><tbody>
    @foreach ($recent as $c)
        <tr><td class="num">{{ $c->masked() }} · {{ $f($c->face_value) }}$</td><td>{{ optional($c->soldCustomer)->name }}</td><td><a class="code" href="{{ route('sg.orders.show', $c->sold_order_id) }}">{{ optional($c->soldOrder)->code }}</a></td>
            @php $cu = \App\Services\CardSaleService::costUsd($c, (float) $c->sale_rate ?: null); @endphp
            @if ($canProfit)<td class="n num">{{ $f($c->cost_dinar) }} د.ل<div class="note">{{ $cu !== null ? '≈ ' . $f($cu) . ' $' : '' }}</div></td>@endif
            <td class="n num">{{ $f($c->sale_price) }} د.ل<div class="note">{{ $c->sale_usd ? '≈ ' . $f($c->sale_usd) . ' $' : '' }}</div></td>
            @if ($canProfit)<td class="n num {{ $c->sale_price - $c->cost_dinar >= 0 ? 'plus' : 'minus' }}">{{ $f($c->sale_price - $c->cost_dinar) }} د.ل<div class="note">{{ $c->sale_usd && $cu !== null ? '≈ ' . $f($c->sale_usd - $cu) . ' $' : '' }}</div></td>@endif<td>{{ optional($c->soldUser)->name }}</td><td class="note">{{ $c->sent_at ? '✓ ' . $c->sent_at->diffForHumans() : 'لم تُرسل' }}</td></tr>
    @endforeach
    </tbody></table></div>
</div>
@endif
@endsection
@push('script')
<script>
(function () {
  const C = @json($customers->map(fn ($c) => ['id' => $c->id, 't' => $c->name . ' - ' . $c->code . ' - ' . $c->phone_1]));
  const q = document.getElementById('csq'), cs = document.getElementById('cs'), list = document.getElementById('csl'), cid = document.getElementById('cid');
  function render() { const t = q.value.trim().toLowerCase(); const rows = (t ? C.filter(c => c.t.toLowerCase().includes(t)) : C).slice(0, 40);
    list.innerHTML = rows.map(c => { const p = c.t.split(' - '); return `<div class="opt" data-id="${c.id}"><span>${p[0]}</span><small>${p.slice(1).join(' · ')}</small></div>`; }).join('') || '<div class="opt">لا يوجد</div>'; }
  q.addEventListener('focus', () => { q.select(); render(); cs.classList.add('open'); });
  q.addEventListener('input', () => { cid.value = ''; render(); cs.classList.add('open'); check(); });
  list.addEventListener('mousedown', e => { const o = e.target.closest('.opt[data-id]'); if (!o) return; cid.value = o.dataset.id; q.value = C.find(c => c.id == o.dataset.id).t; cs.classList.remove('open'); check(); });
  document.addEventListener('click', e => { if (!cs.contains(e.target)) cs.classList.remove('open'); });

  const SHOWP = @json($canProfit), RATE = {{ (float) $rate }}, MR = {{ (float) $mrate }}, box = document.getElementById('selBox'), prices = {};
  const picks = [...document.querySelectorAll('.cardsel input')];
  function draw() {
    const on = picks.filter(p => p.checked);
    box.innerHTML = on.length ? '' : '<div class="note">اختر بطاقة من القائمة</div>';
    on.forEach(p => { const v = +p.dataset.v, id = p.value; if (prices[id] === undefined) prices[id] = Math.round(v * RATE);
      const r = document.createElement('div'); r.className = 'selrow';
      const cost = +(p.dataset.cost || 0), costU = p.dataset.costusd ? +p.dataset.costusd : null;
      r.innerHTML = `<div style="grid-column:1/-1;display:flex;justify-content:space-between"><b class="no">${p.dataset.no} · ${v} $</b><button type="button" style="border:0;background:none;color:var(--down);font-size:18px;cursor:pointer">×</button></div>
        <div class="kv3" style="grid-column:1/-1;display:grid;grid-template-columns:${SHOWP ? '1fr 1fr 1fr' : '1fr'};gap:6px;font-size:12.5px">
          <div class="hint" style="margin:0;padding:6px 8px;${SHOWP ? '' : 'display:none'}"><small class="note">التكلفة</small><div class="num"><b>${SG.fmt(cost)}</b> د.ل</div><div class="num note">${costU !== null ? '≈ ' + SG.fmt2(costU) + ' $' : ''}</div></div>
          <div style="display:grid;gap:4px"><small class="note">سعر البيع</small><div style="display:flex;gap:4px;align-items:center"><input class="inp numr pr" name="price[${id}]" value="${prices[id]}" inputmode="decimal" style="height:34px"><small>د.ل</small></div><div style="display:flex;gap:4px;align-items:center"><input class="inp numr pu" value="" inputmode="decimal" style="height:34px"${MR ? '' : ' disabled'}><small>$</small></div></div>
          <div class="hint" style="margin:0;padding:6px 8px;${SHOWP ? '' : 'display:none'}"><small class="note">الربح</small><div class="num profit"></div><div class="num note profu"></div></div>
        </div>`;
      r.style.gridTemplateColumns = '1fr';
      const pr = r.querySelector('.pr'), pu = r.querySelector('.pu'), pf = r.querySelector('.profit'), pfu = r.querySelector('.profu');
      const upd = (src) => { if (src === 'u' && MR) pr.value = Math.round((parseFloat(pu.value) || 0) * MR * 100) / 100; if (src !== 'u' && MR) pu.value = pr.value ? (Math.round(parseFloat(pr.value) / MR * 100) / 100) : '';
        prices[id] = pr.value; const g = (parseFloat(pr.value) || 0) - cost; pf.innerHTML = '<b>' + SG.fmt(g) + '</b> د.ل'; pf.className = 'num profit ' + (g < 0 ? 'neg' : 'pos'); pfu.textContent = MR ? '≈ ' + SG.fmt2(g / MR) + ' $' : ''; tot(); };
      pr.addEventListener('input', () => upd('d')); pu.addEventListener('input', () => upd('u')); r.querySelector('button').onclick = () => { p.checked = false; draw(); }; box.appendChild(r); upd(); });
    tot(); check();
  }
  function tot() { let t = 0, c = 0; box.querySelectorAll('.pr').forEach(i => t += parseFloat(i.value) || 0); picks.filter(p => p.checked).forEach(p => c += +p.dataset.cost);
    document.getElementById('tot').textContent = SG.fmt(t) + ' د.ل' + (MR ? ' (≈ ' + SG.fmt2(t / MR) + ' $)' : '');
    document.getElementById('totx').innerHTML = t && SHOWP ? '<span>التكلفة ' + SG.fmt(c) + ' د.ل' + (MR ? ' ≈ ' + SG.fmt2(c / MR) + ' $' : '') + '</span><span style="color:' + (t - c < 0 ? 'var(--down)' : 'var(--up)') + '">الربح ' + SG.fmt(t - c) + ' د.ل' + (MR ? ' ≈ ' + SG.fmt2((t - c) / MR) + ' $' : '') + '</span>' : '';
    const paid = parseFloat(document.getElementById('paid').value) || 0; document.getElementById('due').textContent = t ? (paid >= t ? 'مدفوع بالكامل ✓' : 'يبقى على العميل ' + SG.fmt(t - paid) + ' د.ل (يُسجَّل ديناً)') : ''; }
  function check() { document.getElementById('sb').disabled = !(cid.value && picks.some(p => p.checked)); }
  picks.forEach(p => p.addEventListener('change', draw));
  document.getElementById('paid').addEventListener('input', tot);
  document.getElementById('flt').addEventListener('input', e => { const v = e.target.value.trim(); document.querySelectorAll('.cardsel label').forEach(l => l.hidden = v && !l.dataset.last.includes(v)); });
  document.getElementById('sf').addEventListener('submit', e => { const n = picks.filter(p => p.checked).length; if (!confirm('بيع ' + n + ' بطاقة للعميل؟ ستُعلَّم مباعة ولا يمكن استخدامها بعد ذلك.')) e.preventDefault(); });
  draw();
})();
</script>
@endpush
