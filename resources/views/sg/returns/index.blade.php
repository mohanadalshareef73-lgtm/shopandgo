@extends('sg.layout')
@section('title', 'طلبيات مسترجعة')
@section('bodyClass', 'soft')
@php use App\Services\TreasuryService as TS; use App\Models\OrderReturn as R; $f = fn ($n) => TS::fmt($n); @endphp
@section('content')
<div class="top">
    <div><h1>طلبيات مسترجعة</h1><div class="sub">كل قطعة عادت: قيمتها، والفلوس عادت كيف، والبضاعة أين مشت</div></div>
    <div class="acts">@can('return-create')<button class="btn" data-modal="mRet">+ إيصال مرتجع</button>@endcan @include('sg.partials.period')<a class="btn ghost" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">⬇ Excel</a><button class="sg-burger">☰</button></div>
</div>

<div class="kpis g4">
    <div class="card kpi accent"><div class="lbl">مرتجعات {{ $period->label }}</div><div class="v num">{{ $kpi['count'] }}</div><div class="s">{{ $kpi['qty'] }} قطعة · {{ $kpi['exchanges'] }} استبدال</div></div>
    <div class="card kpi red"><div class="lbl">قيمة المرتجع</div><div class="v"><span class="num">{{ $f($kpi['amount']) }}</span> <small>د.ل</small></div><div class="s">{{ $kpi['orders'] ? number_format($kpi['count'] / $kpi['orders'] * 100, 1) : 0 }}% من الإيصالات</div></div>
    <div class="card kpi"><div class="lbl">صُرف من الخزينة</div><div class="v"><span class="num">{{ $f($kpi['refunded']) }}</span> <small>د.ل</small></div><div class="s">أموال عادت للعملاء نقدي</div></div>
    <div class="card kpi"><div class="lbl">رصيد للعملاء</div><div class="v"><span class="num">{{ $f($kpi['credit']) }}</span> <small>د.ل</small></div><div class="s">ينخصم من طلبياتهم القادمة</div></div>
</div>

<div class="side-lay">
<div class="card tw">
    <form method="get" class="tw-h">
        @if (request('p'))<input type="hidden" name="p" value="{{ request('p') }}">@endif
        <a class="pill {{ !request('type') ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['type' => null]) }}">الكل</a>
        <a class="pill {{ request('type') === 'return' ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['type' => 'return']) }}">مرتجع</a>
        <a class="pill {{ request('type') === 'exchange' ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['type' => 'exchange']) }}">استبدال</a>
        <div class="sx"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input name="q" value="{{ request('q') }}" placeholder="رقم إيصال، عميل، هاتف…"></div>
    </form>
    <div class="scroll-x">
    <table class="t">
        <thead><tr><th>التاريخ</th><th>الإيصال</th><th>العميل</th><th>ماذا رجع</th><th class="n">القيمة</th><th>المبلغ</th><th>البضاعة / الموقع</th><th>استلمه</th><th>السبب</th></tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr>
                <td class="note num">{{ $r->created_at->format('Y-m-d') }}<br>{{ optional($r->createdUser)->name }}</td>
                <td><a class="code" href="{{ route('sg.orders.show', $r->order_id) }}">{{ optional($r->order)->code }}</a><br><span class="chip {{ $r->type === 'exchange' ? 'n' : 'd' }}">{{ $r->typeLabel() }}</span></td>
                <td>{{ optional($r->customer)->name }}<div class="note num">{{ optional($r->customer)->phone_1 }}</div></td>
                <td class="wrap">{{ $r->items ?: '—' }} @if ($r->qty)<span class="note">({{ $r->qty }} قطعة)</span>@endif @if ($r->new_items)<div class="note">⇄ بدالها: {{ $r->new_items }}</div>@endif</td>
                <td class="n"><b class="num minus">{{ $f($r->amount) }}</b>@if ($r->extra > 0)<div class="note num plus">+{{ $f($r->extra) }} فرق</div>@endif</td>
                <td><span class="chip n">{{ $r->methodLabel() }}</span>@if ($r->refunded > 0)<div class="note">{{ optional($r->payment)->name }} · {{ $f($r->refunded) }}</div>@endif</td>
                <td class="note">{{ R::GOODS[$r->goods] ?? $r->goods }}@if ($r->location)<div><b>📍 {{ $r->location }}</b></div>@endif</td>
                <td class="note">{{ optional($r->receiver)->name ?: '—' }}</td>
                <td class="note wrap">{{ $r->reason }}</td>
            </tr>
        @empty
            <tr><td colspan="9"><div class="empty"><span class="ic">↩</span>لا يوجد مرتجعات في الفترة هذه<br><small>سُجّل المرتجع من صفحة الإيصال: زر "↩ مرتجع / استبدال"</small></div></td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="tw-f">@include('sg.partials.pager', ['p' => $rows])</div>
</div>
<div>
    <div class="card">
        <h3>أكثر أسباب الرجوع</h3>
        @forelse ($reasons as $why => $n)
            <div class="ln"><span>{{ $why }}</span><b class="num">{{ $n }}</b></div>
        @empty
            <div class="note">لا يزال لا يوجد أسباب مسجلة</div>
        @endforelse
    </div>
</div>
</div>
@can('return-create')
<div class="modal-bg" id="mRet"><div class="modal" style="max-width:620px">
    <h3>إيصال مرتجع / استبدال</h3>
    <form method="post" id="retF" data-base="{{ route('sg.returns.store', 0) }}">
        @csrf
        <div class="f"><label class="l">الزبون أو الإيصال</label><input class="inp" id="rq" placeholder="اكتب كود الزبون، اسمه، هاتفه، أو رقم الإيصال…" autocomplete="off"></div>
        <div class="f" id="rList" style="max-height:200px;overflow:auto"></div>
        <div id="rPicked" class="hint mb" hidden></div>
        <div class="f"><div class="opts"><label><input type="radio" name="type" value="return" checked><span>↩ مرتجع</span></label><label><input type="radio" name="type" value="exchange"><span>⇄ استبدال</span></label></div></div>
        <div class="grid g2 f"><div><label class="l">عدد القطع</label><input name="qty" class="inp numr" value="1"></div><div><label class="l">شن رجع</label><input name="items" class="inp" placeholder="مثلاً: فستان أسود مقاس M"></div></div>
        <div class="grid g2 f"><div><label class="l">استلمه من الزبون</label><select name="received_by" class="inp">@foreach ($users as $u)<option value="{{ $u->id }}" @selected($u->id == auth()->id())>{{ $u->name }}</option>@endforeach</select></div>
            <div><label class="l">الموقع (وين حطيناه)</label><input name="location" class="inp" placeholder="رف 4، المخزن، عند المورد…"></div></div>
        <div class="grid g2 f"><div><label class="l">البضاعة</label><select name="goods" class="inp"><option value="shop">عندنا في المحل</option><option value="supplier">رجعت للمورد</option><option value="damaged">تالفة</option></select></div>
            <div><label class="l">قيمة اللي رجع (د.ل)</label><input name="amount" class="inp numr" value="0"></div></div>
        <div class="f"><label class="l">كيف رجّعناله القيمة؟</label><div class="opts" style="flex-wrap:wrap"><label><input type="radio" name="method" value="cash" checked><span>💵 رجّعناله فلوس</span></label><label><input type="radio" name="method" value="credit"><span>رصيد عندنا</span></label><label><input type="radio" name="method" value="deduct"><span>تنخصم من الإيصال</span></label><label><input type="radio" name="method" value="none"><span>بدون</span></label></div></div>
        <div class="f" id="rPay"><label class="l">من خزينة</label><select name="payment_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}">{{ $pm->name }}</option>@endforeach</select></div>
        <div class="f"><label class="l">السبب</label><input name="reason" class="inp" placeholder="مقاس، عيب، مش عاجبه…"></div>
        <label class="ck f"><input type="checkbox" name="send_whatsapp" value="1"> أرسل للزبون واتساب</label>
        <div style="display:flex;gap:8px"><button class="btn" id="rGo" disabled>حفظ المرتجع</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
<script>
(function () {
  const f = document.getElementById('retF'), q = document.getElementById('rq'), list = document.getElementById('rList'), pk = document.getElementById('rPicked'), go = document.getElementById('rGo');
  let t;
  q.addEventListener('input', () => { clearTimeout(t); t = setTimeout(async () => {
    const v = q.value.trim(); if (!v) { list.innerHTML = ''; return; }
    const r = await (await fetch(@json(route('sg.returns.orders')) + '?q=' + encodeURIComponent(v))).json();
    list.innerHTML = r.length ? r.map(o => `<label style="display:flex;gap:10px;align-items:center;padding:8px 10px;border:1px solid var(--line);border-radius:10px;margin-bottom:6px;cursor:pointer"><input type="radio" name="_o" value="${o.id}" data-txt="${o.code} · ${o.name || ''} (${o.ccode || ''})"><b class="num">${o.code}</b><span style="flex:1">${o.name || ''} <small class="note num">${o.ccode || ''}</small></span><small class="note">${o.qty} قطعة · ${o.st}</small><b class="num">${o.total}</b></label>`).join('') : '<div class="note">ما فيش إيصالات</div>';
    list.querySelectorAll('input[name=_o]').forEach(i => i.onchange = () => { f.action = f.dataset.base.replace(/\/0\/return$/, '/' + i.value + '/return'); pk.hidden = false; pk.textContent = '✓ الإيصال: ' + i.dataset.txt; go.disabled = false; });
  }, 250); });
  f.querySelectorAll('input[name=method]').forEach(m => m.onchange = () => document.getElementById('rPay').hidden = m.value !== 'cash' || !m.checked);
})();
</script>
@endcan
@endsection
