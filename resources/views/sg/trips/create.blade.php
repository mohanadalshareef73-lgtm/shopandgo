@extends('sg.layout')
@section('title', 'إضافة رحلة')
@section('bodyClass', 'soft')
@php use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); @endphp
@push('style')
<style>
.pending { display: flex; align-items: center; gap: 18px; padding: 16px 20px; margin-bottom: 16px; background: var(--warn-bg); border: 1px solid #f3dfb2; border-radius: 16px; }
.pending .big { font-size: 44px; font-weight: 800; color: var(--warn); line-height: 1; min-width: 70px; text-align: center; transition: transform .25s; }
.pending .big.bump { transform: scale(1.18); }
.pending .txt b { font-size: 17px; display: block; color: #6b4600; }
.pending .txt span { font-size: 13.5px; color: #8a6a2a; }
.pending .prog { flex: 1; max-width: 340px; margin-inline-start: auto; }
.pending .prog .b { height: 8px; background: #f3e3bd; }
.pending .prog small { display: flex; justify-content: space-between; font-size: 12.5px; color: #8a6a2a; margin-top: 6px; }
.fields { display: grid; grid-template-columns: 1fr 1fr 1.6fr; gap: 14px; padding: 18px 20px; margin-bottom: 16px; }
.inp.code { font-family: InterNum; font-weight: 700; letter-spacing: .5px; direction: ltr; text-align: right; font-size: 18px; height: 50px; }
.pfchips { display: flex; gap: 8px; flex-wrap: wrap; }
.pfchips button { height: 50px; padding: 0 16px; border: 1px solid var(--line); border-radius: 12px; background: #fff; font: inherit; font-size: 15px; color: var(--ink-2); cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
.pfchips button .n { font-size: 12px; background: var(--line-soft); color: var(--ink-3); padding: 2px 7px; border-radius: 99px; font-family: InterNum; }
.pfchips button.on { background: var(--brand); color: #fff; border-color: var(--brand); }
.pfchips button.on .n { background: rgba(255,255,255,.18); color: #fff; }
.lists { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.panel { display: flex; flex-direction: column; min-height: 520px; padding: 0; overflow: hidden; }
.panel-h { padding: 16px 18px 12px; border-bottom: 1px solid var(--line-soft); }
.panel-h .t { display: flex; justify-content: space-between; align-items: center; }
.panel-h h3 { margin: 0; font-size: 17px; }
.sr { position: relative; margin-top: 12px; }
.sr input { padding-inline-start: 44px; font-size: 18px; height: 54px; border-width: 2px; }
.sr svg { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; color: var(--ink-3); }
.sr kbd { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-family: inherit; font-size: 12px; color: var(--ink-3); background: var(--line-soft); border-radius: 6px; padding: 3px 8px; }
.rows { flex: 1; overflow: auto; padding: 6px 10px 10px; max-height: 560px; }
.row { display: grid; grid-template-columns: 58px 1fr auto auto auto; align-items: center; gap: 12px; padding: 10px; border-radius: 12px; border: 1px solid transparent; }
.row:hover { background: #f7f8fb; }
.row.hit { border-color: #cfe0f7; background: #f3f8fe; }
.row .cd { font-family: InterNum; font-weight: 700; font-size: 16px; background: var(--line-soft); border-radius: 9px; padding: 6px 0; text-align: center; }
.row .cd mark { background: #ffe58a; color: inherit; border-radius: 3px; }
.row .nm { font-size: 15px; font-weight: 700; }
.row .meta { font-size: 12.5px; color: var(--ink-3); display: flex; gap: 8px; align-items: center; margin-top: 2px; }
.row .amt { text-align: left; font-size: 14px; }
.row .amt small { display: block; font-size: 11.5px; color: var(--ink-3); font-family: Somar; }
.nt { width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--line); background: #fff; font-size: 16px; cursor: pointer; }
.add { width: 38px; height: 38px; border-radius: 10px; border: 0; background: var(--brand); color: #fff; font-size: 20px; cursor: pointer; }
.rmv { width: 34px; height: 34px; border-radius: 10px; border: 1px solid var(--line); background: #fff; color: var(--down); font-size: 18px; cursor: pointer; }
.row.in { animation: pop .35s ease; } @keyframes pop { from { background: var(--up-bg); } to { background: transparent; } }
.trip-head { background: var(--brand); color: #fff; border-bottom: 0; }
.trip-head .note { color: #b9bfd8; }
.trip-head .badge { font-family: InterNum; font-weight: 800; font-size: 15px; background: rgba(255,255,255,.12); padding: 4px 10px; border-radius: 8px; }
.totals { display: grid; grid-template-columns: repeat(3, 1fr); border-top: 1px solid var(--line); }
.totals div { padding: 12px 16px; border-left: 1px solid var(--line-soft); } .totals div:last-child { border-left: 0; }
.totals small { display: block; font-size: 12.5px; color: var(--ink-3); } .totals b { font-size: 20px; }
.calc { display: grid; grid-template-columns: 1.3fr 1fr; margin-top: 16px; padding: 0; overflow: hidden; }
.calc .in { padding: 18px 20px; } .calc .out { background: #f7f9fc; border-inline-start: 1px solid var(--line); padding: 18px 20px; }
.conv { display: grid; grid-template-columns: 1fr 28px 1fr 28px 1.1fr; gap: 10px; align-items: start; }
.conv .op { font-size: 26px; color: var(--ink-3); text-align: center; padding-top: 34px; font-family: InterNum; }
.unit { position: relative; } .unit span { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--ink-3); font-size: 14px; }
.lyd { height: 54px; border-radius: 12px; background: var(--brand); color: #fff; display: flex; align-items: center; padding: 0 16px; gap: 6px; }
.lyd .num { font-size: 22px; font-weight: 800; } .lyd small { color: #b9bfd8; }
.profit { display: flex; align-items: flex-end; justify-content: space-between; margin-top: 12px; }
.profit .v { font-size: 38px; font-weight: 800; color: var(--up); line-height: 1.1; }
.footer { display: flex; align-items: center; gap: 16px; margin-top: 16px; padding: 14px 18px; flex-wrap: wrap; }
.wa .msg { background: var(--wa-bg); border: 1px solid #c6ecd4; border-radius: 12px; padding: 8px 12px; font-size: 13.5px; color: #145c33; flex: 1; }
.cardlist { display: flex; flex-direction: column; gap: 6px; max-height: 320px; overflow: auto; }
.cardlist .crd { display: flex; gap: 8px; align-items: center; border: 1px solid var(--line); border-radius: 12px; padding: 6px 10px; }
.cardlist .crd.on { border-color: var(--brand); background: #f6f7fc; }
.cardlist .no { font-weight: 700; direction: ltr; }
@media (max-width: 1100px) { .fields, .lists, .calc, .conv { grid-template-columns: 1fr; } .conv .op { display: none; } }
</style>
@endpush
@section('content')
<div class="crumb"><a href="{{ route('sg.orders.index') }}">الطلبات</a> › <a href="{{ route('sg.trips.index') }}">الرحلات</a> › رحلة جديدة</div>
<div class="top"><h1>إضافة رحلة</h1><div class="acts"><a href="{{ route('sg.orders.index') }}" class="btn ghost">رجوع للطلبات</a><button class="sg-burger">☰</button></div></div>

<form method="post" action="{{ route('finance.trips.store') }}" id="tf">
@csrf
<div id="hidden"></div>
<div class="pending">
    <div class="big num" id="left">{{ $allFree }}</div>
    <div class="txt"><b id="leftTxt">عميل لديهم طلبات ومش في رحلة</b><span>كل ما تضيف عميل للرحلة ينقص العدد</span></div>
    <div class="prog"><div class="b"><i id="progBar" style="width:0%"></i></div><small><span>دخلوا الرحلة: <b class="num" id="progIn">0</b></span><span>من <b class="num" id="allN">{{ $allFree }}</b></span></small></div>
</div>

<div class="card fields">
    <div><label class="l"><span class="step-n" style="width:20px;height:20px;font-size:11px">1</span>كود الرحلة</label><input class="inp code" name="trip_code" id="tripCode" value="{{ old('trip_code', $nextCode) }}" required></div>
    <div><label class="l"><span class="step-n" style="width:20px;height:20px;font-size:11px">2</span>حالة الرحلة</label>
        <select class="inp" name="trip_status" id="tripStatus" style="height:50px">@foreach ($statuses as $s)<option @selected(old('trip_status') == $s->name)>{{ $s->name }}</option>@endforeach</select></div>
    <div><label class="l"><span class="step-n" style="width:20px;height:20px;font-size:11px">3</span>المنصة</label><div class="pfchips" id="pfChips"></div></div>
</div>

<div class="lists">
    <div class="card panel">
        <div class="panel-h">
            <div class="t"><h3>عملاء ليس في رحلة</h3><span class="note" id="freeCnt"></span></div>
            <div class="sr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input class="inp" id="q" placeholder="كود العميل أو اسمه أو كود الإيصال…" autocomplete="off"><kbd>Enter = إضافة</kbd></div>
            <div style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap"><button type="button" class="btn ghost sm" id="ntAll">🚫 بدون رحلة لكل المعروض</button><span class="note">الزبون يشوف «سيصل قريباً» وتطلع من هالقائمة</span></div>
        </div>
        <div class="rows" id="freeRows"></div>
    </div>
    <div class="card panel">
        <div class="panel-h trip-head"><div class="t"><h3>في الرحلة <span class="badge" id="tripBadge"></span></h3><span class="note" id="tripCnt"></span></div></div>
        <div class="rows" id="tripRows"></div>
        <div class="totals"><div><small>عملاء</small><b class="num" id="tCust">0</b></div><div><small>إيصالات</small><b class="num" id="tRec">0</b></div><div><small>قيمة الإيصالات</small><b><span class="num" id="tVal">0</span> <small style="display:inline">د.ل</small></b></div></div>
    </div>
</div>

<div class="card calc">
    <div class="in">
        <h3>حساب الرحلة</h3>
        <div class="conv">
            <div><label class="l">كلفتني الرحلة (دولار)</label><div class="unit"><input class="inp big" name="cost" id="cost" value="{{ old('cost') }}" placeholder="0" required inputmode="decimal"><span>$</span></div></div>
            <div class="op">×</div>
            <div><label class="l">سعر الصرف</label><div class="unit"><input class="inp big" name="rate" id="rate" value="{{ old('rate', $avgRate) }}" required inputmode="decimal"><span>د.ل</span></div>@if ($avgRate)<small class="note">متوسط سعر الدولار الذي اشتريته: <b class="num">{{ $avgRate }}</b></small>@endif</div>
            <div class="op">=</div>
            <div><label class="l">كلفتني (دينار)</label><div class="unit"><input class="inp big" id="costLydIn" value="" placeholder="0" inputmode="decimal"><span>د.ل</span></div><small class="note">اكتب أي اثنين، والثالث يُحسب تلقائياً</small></div>
        </div>
        <input type="hidden" name="cost_currency" value="dollar">
        <label class="l mt">طريقة الدفع</label>
        @php $oldSrc = in_array(old('pay_source'), ['cards', 'both']) ? old('pay_source') : 'treasury'; @endphp
        <div class="opts mb" id="srcOpts" style="flex-wrap:wrap">
            <label><input type="radio" name="src" value="treasury" @checked($oldSrc === 'treasury')><span>🏦 خزينة</span></label>
            @if ($cards->count())
            <label><input type="radio" name="src" value="cards" @checked($oldSrc === 'cards')><span>🛍 بطاقات SHEIN<small class="note num" style="display:block">{{ $f($cards->sum('balance')) }} $</small></span></label>
            <label><input type="radio" name="src" value="both" @checked($oldSrc === 'both')><span>🔀 بطاقة + خزينة<small class="note" style="display:block">الباقي من الخزينة</small></span></label>
            @endif
        </div>
        <input type="hidden" name="pay_source" id="paySource" value="{{ $oldSrc }}">
        <input type="hidden" name="payment_id" id="payId" value="{{ old('payment_id', $visaId) }}">
        @if ($cards->count())
        <div id="cardsBox" class="mb" hidden>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap"><b>💳 بطاقات SHEIN <span class="note">({{ $cards->count() }} بطاقة · الكلي {{ $f($cards->sum('face_value')) }} $ · الباقي {{ $f($cards->sum('balance')) }} $)</span></b><button type="button" class="btn ghost sm" id="cardsAuto">املأ تلقائياً</button></div>
            <input class="inp mb" id="cardQ" placeholder="ابحث بجزء من رقم البطاقة…" autocomplete="off" inputmode="numeric">
            <div class="cardlist">
            @foreach ($cards as $c)
                <div class="crd" data-no="{{ auth()->user()->can('shein-card-list') ? $c->card_no : $c->masked() }}" style="flex-wrap:wrap">
                    <span class="num no">{{ $c->masked() }}</span>
                    @can('shein-card-list')<button type="button" class="cpy" data-copy="{{ $c->card_no }}" title="نسخ رقم البطاقة فقط">⧉ نسخ الرقم</button>@endcan
                    @can('shein-card-pin')<button type="button" class="cpy pinB" data-url="{{ route('sg.shein.pin', $c->id) }}" title="إظهار الرمز">👁 الرمز</button><b class="num pinV" hidden></b>@endcan
                    <span class="note" style="flex:1">الكلي <b class="num">{{ $f($c->face_value) }}</b> $ · الباقي <b class="num">{{ $f($c->balance) }}</b> $ @can('profit-view')· الدولار بـ {{ number_format($c->costPerDollar(), 2) }}@endcan</span>
                    <input name="cards[{{ $c->id }}]" class="inp numr cardAmt" style="height:36px;width:100px" data-bal="{{ $c->balance }}" data-cpd="{{ $c->costPerDollar() }}" value="{{ old('cards.' . $c->id, 0) }}" inputmode="decimal"><span class="note">$</span>
                </div>
            @endforeach
            </div>
            <div class="note mt" id="cardsSum"></div>
        </div>
        @endif
        <div id="trBox">
            <label class="l">من الخزينة · العملة</label>
            <div class="opts mb"><label><input type="radio" name="pay_currency" value="dollar" @checked(old('pay_currency', 'dollar') === 'dollar')><span>دولار $</span></label><label><input type="radio" name="pay_currency" value="dinar" @checked(old('pay_currency') === 'dinar')><span>دينار د.ل (بسعر الصرف)</span></label></div>
            <label class="l">الطريقة</label>
            @php $pSel = old('payment_id', $visaId) ?: optional($payments->first())->id; @endphp
            <input type="hidden" id="paySel" value="{{ $pSel }}">
            <div class="opts" style="flex-wrap:wrap">@foreach ($payments as $p)<label><input type="radio" name="pm_pick" value="{{ $p->id }}" @checked($pSel == $p->id) onchange="const h=document.getElementById('paySel');h.value=this.value;h.dispatchEvent(new Event('change'))"><span>{{ $p->name }}<small class="note num" style="display:block">{{ $f($dollarBalances[$p->id]) }} $ · {{ $f($dinarBalances[$p->id]) }} د.ل</small></span></label>@endforeach</div>
            <small class="note" style="display:block;margin-top:4px">خزينة ناقصة (مثلاً USDT)؟ زيدها من <a href="{{ route('sg.settings', ['tab' => 'money']) }}" target="_blank">الإعدادات ← الخزائن</a></small>
            <small class="note" id="payNote"></small>
        </div>
        <div class="grid g2 mt"><div><label class="l">تاريخ الشراء</label><input type="date" class="inp" name="purchased_at" value="{{ old('purchased_at', now()->toDateString()) }}"></div></div>
        <label class="ck mt"><input type="checkbox" name="coupon_used" value="1" @checked(old('coupon_used'))> استخدمت كوبون في الرحلة هذه</label>
        <input class="inp mt" name="note" value="{{ old('note') }}" placeholder="ملاحظة (اختياري)">
    </div>
    <div class="out">
        <h3>@can('profit-view')الربح@else الملخص@endcan</h3>
        <div class="ln"><span>الذي قلته للعملاء (مجموع الإيصالات)</span><b class="num" id="cSales">0</b></div>
        <div class="ln"><span>التكلفة بالدينار الليبي</span><b class="num minus" id="cCost">0</b></div>
        <div class="profit" @cannot('profit-view') hidden style="display:none" @endcannot>
            <div><div class="note" style="font-size:14px;color:var(--ink-2)">ربحت في الرحلة</div><div class="v"><span class="num" id="pD">0</span> <small style="font-size:15px;color:var(--ink-3);font-weight:400">د.ل</small></div></div>
            <div style="text-align:left"><div class="num plus" style="font-size:20px;font-weight:700" id="pU">0 $</div><span class="chip u">▲ <span class="num" id="pM">0%</span> نسبة الربح</span></div>
        </div>
        <div class="note mt">✓ عند الحفظ: يُخصم المبلغ من البطاقات أو الخزينة التي اخترتها@can('profit-view')، ويدخل الربح في المالية ← الأرباح@endcan</div>
    </div>
</div>

<div class="card footer">
    <label class="tgl"><input type="checkbox" name="send_whatsapp" value="1" id="waSw" checked><span class="tg"></span></label>
    <div class="wa" style="flex:1;display:flex"><div class="msg" id="waMsg"></div></div>
    <button class="btn green lg" id="saveBtn" disabled>حفظ الرحلة</button>
</div>
</form>
@endsection
@push('script')
<style>
.rows .sub{grid-column:1/-1;margin:2px 0 6px;border-top:1px dashed var(--line,#ddd)}
.rows .sr2{display:grid;grid-template-columns:1fr auto 1fr auto 30px;gap:8px;align-items:center;padding:4px 6px;font-size:13px}
.rows .sr2 .st2{color:var(--muted,#888);font-size:12px}
.rows .sr2 button{width:28px;height:28px;border-radius:8px;border:1px solid var(--line,#ddd);background:transparent;cursor:pointer;font-weight:700}
.rows .sr2 .rmv1{color:var(--down,#c33)}
</style>

<script>
(function () {
  const CUST = @json($customers);
  const PRE = @json($pre);
  const $ = id => document.getElementById(id);
  const fmt = SG.fmt;
  let pf = 'all', inTrip = new Set(PRE);
  let ALL = CUST.length, shown = [];
  const ordersOf = c => c.orders.filter(o => pf === 'all' || o.pf === pf);
  const custIn = c => c.orders.some(o => inTrip.has(o.id));
  const custFree = c => ordersOf(c).some(o => !inTrip.has(o.id));
  function chips() {
    const pfs = [...new Set(CUST.flatMap(c => c.orders.map(o => o.pf)).filter(Boolean))];
    const cnt = p => CUST.filter(c => c.orders.some(o => !inTrip.has(o.id) && (p === 'all' || o.pf === p))).length;
    $('pfChips').innerHTML = [['all', 'الكل'], ...pfs.map(p => [p, p])].map(([k, t]) => `<button type="button" class="${pf === k ? 'on' : ''}" data-pf="${k}">${t}<span class="n">${cnt(k)}</span></button>`).join('');
    $('pfChips').querySelectorAll('button').forEach(b => b.onclick = () => { pf = b.dataset.pf; render(); });
  }
  const hl = (s, q) => { s = String(s ?? ''); const i = q ? s.indexOf(q) : -1; return i < 0 ? s : s.slice(0, i) + '<mark>' + s.slice(i, i + q.length) + '</mark>' + s.slice(i + q.length); };
  const daysTxt = d => ['من اليوم', 'من أمس', 'من يومين'][d] || ('من ' + d + ' أيام');
  function row(c, mode, q) {
    const os = mode === 'free' ? ordersOf(c).filter(o => !inTrip.has(o.id)) : c.orders.filter(o => inTrip.has(o.id));
    const tot = os.reduce((a, o) => a + o.total, 0);
    const pfs = [...new Set(os.map(o => o.pf).filter(Boolean))];
    return `<div class="row ${mode === 'free' && q && (c.code || '').includes(q) ? 'hit' : ''}">
      <div class="cd num">${hl(c.code, q)}</div>
      <div><div class="nm">${c.name || ''}</div><div class="meta">${pfs.map(p => `<span class="pf ${p.toLowerCase()}">${p}</span>`).join('')}<span>${os.length} ${os.length > 1 ? 'إيصالات' : 'إيصال'}</span><span>· ${daysTxt(c.days)}</span></div></div>
      <div class="amt"><b class="num">${fmt(tot)}</b><small>د.ل</small></div>
      ${mode === 'free' ? `<button type="button" class="nt" data-id="${c.id}" title="بدون رحلة: كل إيصالاته المعروضة">🚫</button><button type="button" class="add" data-id="${c.id}" title="كل إيصالاته">+</button>` : `<button type="button" class="rmv" data-id="${c.id}" title="طلّع كل إيصالاته">×</button>`}
      <div class="sub">${os.map(o => `<div class="sr2"><span class="num cd2">${hl(o.code, q)}</span><span class="num">${o.qty} قطعة</span><span class="st2">${o.st || ''}</span><span class="num">${fmt(o.total)}</span>${mode === 'free' ? `<button type="button" class="add1" data-oid="${o.id}">+</button>` : `<button type="button" class="rmv1" data-oid="${o.id}">×</button>`}</div>`).join('')}</div></div>`;
  }
  // 🚫✈ بدون رحلة: نبعتو الإيصالات للمنظومة، وتطلع من القائمة من غير ما نخسرو اللي اخترناه للرحلة
  async function noTrip(ids) {
    if (!ids.length || !confirm('نخلي ' + ids.length + ' إيصال «بدون رحلة»؟\nالزبون يشوف «سيصل قريباً» وتطلع من هالقائمة.')) return;
    try {
      const r = await fetch(@json(route('finance.trips.noTrip')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('#tf input[name=_token]').value, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ order_ids: ids }) });
      const j = await r.json();
      if (!r.ok || !j.ok) throw new Error(j.error || j.message || 'ما تمتش العملية');
      const done = new Set(j.ids);
      CUST.forEach(c => { c.orders = c.orders.filter(o => !done.has(o.id)); });
      for (let i = CUST.length - 1; i >= 0; i--) if (!CUST[i].orders.length) CUST.splice(i, 1);
      done.forEach(id => inTrip.delete(id)); ALL = CUST.length;
      bump(); render();
    } catch (e) { alert(e.message); }
  }
  $('ntAll').onclick = () => noTrip(shown);
  function bump() { $('left').classList.add('bump'); setTimeout(() => $('left').classList.remove('bump'), 220); }
  function add(id) { const c = CUST.find(x => x.id == id); ordersOf(c).forEach(o => inTrip.add(o.id)); $('q').value = ''; bump(); render(); $('q').focus(); }
  function rm(id) { const c = CUST.find(x => x.id == id); c.orders.forEach(o => inTrip.delete(o.id)); bump(); render(); }
  function render() {
    const q = $('q').value.trim();
    const free = CUST.filter(c => custFree(c) && (!q || (c.code || '').includes(q) || (c.name || '').includes(q) || c.orders.some(o => String(o.code ?? '').includes(q))));
    $('freeRows').innerHTML = free.length ? free.map(c => row(c, 'free', q)).join('') : '<div class="empty"><span class="ic">🔍</span>لا يوجد عميل هنا</div>';
    $('freeCnt').textContent = free.length + ' عميل';
    shown = free.flatMap(c => ordersOf(c).filter(o => !inTrip.has(o.id)).map(o => o.id)); $('ntAll').textContent = '🚫 بدون رحلة لكل المعروض (' + shown.length + ' إيصال)'; $('ntAll').disabled = !shown.length;
    $('allN').textContent = ALL;
    const trip = CUST.filter(custIn);
    $('tripRows').innerHTML = trip.length ? trip.map(c => row(c, 'trip')).join('') : '<div class="empty"><span class="ic">✈️</span>الرحلة فارغة<br>اكتب كود العميل على اليمين واضغط +</div>';
    const ords = CUST.flatMap(c => c.orders).filter(o => inTrip.has(o.id));
    const val = ords.reduce((a, o) => a + o.total, 0);
    const left = ALL - trip.length;
    $('left').textContent = left; $('progIn').textContent = trip.length; $('progBar').style.width = (ALL ? trip.length / ALL * 100 : 0) + '%';
    $('leftTxt').textContent = left ? 'عميل لديهم طلبات ومش في رحلة' : 'كل العملاء داخلين في رحلات 👍';
    $('tCust').textContent = trip.length; $('tRec').textContent = ords.length; $('tVal').textContent = fmt(val);
    $('tripCnt').textContent = ords.length + ' إيصال'; $('tripBadge').textContent = $('tripCode').value || '—';
    $('saveBtn').textContent = ords.length ? 'حفظ الرحلة · ' + ords.length + ' إيصال' : 'حفظ رحلة فارغة (تضيف العملاء لاحقاً)'; $('saveBtn').disabled = !(parseFloat($('cost').value) > 0);
    $('waMsg').innerHTML = $('waSw').checked ? `واتساب لـ <b class="num">${trip.length}</b> عميل: "اهلا… طلبك دخل رحلة <b class="num">${$('tripCode').value}</b> وحالته: <b>${$('tripStatus').value}</b>"` : 'بدون رسائل واتساب';
    $('hidden').innerHTML = ords.map(o => `<input type="hidden" name="order_ids[]" value="${o.id}">`).join('');
    document.querySelectorAll('.add').forEach(b => b.onclick = () => add(b.dataset.id));
    document.querySelectorAll('.rmv').forEach(b => b.onclick = () => rm(b.dataset.id));
    document.querySelectorAll('.nt').forEach(b => b.onclick = () => { const c = CUST.find(x => x.id == b.dataset.id); noTrip(ordersOf(c).filter(o => !inTrip.has(o.id)).map(o => o.id)); });
    document.querySelectorAll('.add1').forEach(b => b.onclick = () => { inTrip.add(+b.dataset.oid); bump(); render(); $('q').focus(); });
    document.querySelectorAll('.rmv1').forEach(b => b.onclick = () => { inTrip.delete(+b.dataset.oid); bump(); render(); });
    chips(); calc(val);
  }
  const srcEl = () => document.querySelector('input[name=src]:checked');
  const src = () => { const e = srcEl(); return e ? e.value : 'treasury'; };
  const BAL = { usd: @json($dollarBalances), lyd: @json($dinarBalances) };
  const payCur = () => (document.querySelector('input[name=pay_currency]:checked') || {}).value || 'dollar';
  function calc(sales) {
    if (sales === undefined) sales = CUST.flatMap(c => c.orders).filter(o => inTrip.has(o.id)).reduce((a, o) => a + o.total, 0);
    const cost = parseFloat($('cost').value) || 0, rate = parseFloat($('rate').value) || 0, s = src();
    const cardEls = [...document.querySelectorAll('.cardAmt')], useCards = s !== 'treasury';
    const cardsUsd = useCards ? cardEls.reduce((a, i) => a + (parseFloat(i.value) || 0), 0) : 0, cardsLyd = useCards ? cardEls.reduce((a, i) => a + (parseFloat(i.value) || 0) * +i.dataset.cpd, 0) : 0;
    cardEls.forEach(i => i.closest('.crd').classList.toggle('on', (parseFloat(i.value) || 0) > 0));
    const cashUsd = Math.max(0, cost - cardsUsd), cd = cashUsd * rate + cardsLyd, p = sales - cd;
    if (document.activeElement !== $('costLydIn')) $('costLydIn').value = cost && rate ? Math.round(cost * rate * 100) / 100 : '';
    $('cSales').textContent = fmt(sales) + ' د.ل'; $('cCost').textContent = '− ' + fmt(cd) + ' د.ل';
    $('pD').textContent = fmt(p); $('pD').parentElement.style.color = p < 0 ? 'var(--down)' : 'var(--up)';
    $('pU').textContent = (rate ? fmt(p / rate) : 0) + ' $'; $('pM').textContent = (sales ? (p / sales * 100).toFixed(1) : 0) + '%';
    if ($('cardsBox')) $('cardsBox').hidden = !useCards;
    $('trBox').style.display = s === 'cards' ? 'none' : '';
    if ($('cardsSum')) $('cardsSum').innerHTML = useCards ? 'من البطاقات <b class="num">' + SG.fmt2(cardsUsd) + '</b> $' + (s === 'cards' ? (Math.abs(cardsUsd - cost) > 0.009 ? ' · <span style="color:var(--down)">يجب أن يساوي التكلفة ' + SG.fmt2(cost) + ' $</span>' : ' ✓') : (cardsUsd > cost + 0.009 ? ' · <span style="color:var(--down)">أكثر من التكلفة</span>' : ' · والباقي ' + SG.fmt2(Math.max(0, cost - cardsUsd)) + ' $ من الخزينة')) : '';
    $('paySource').value = s; $('payId').value = $('paySel').value;
    const pid = $('payId').value, pc = payCur(), need = pc === 'dinar' ? cashUsd * rate : cashUsd, bal = +((pc === 'dinar' ? BAL.lyd : BAL.usd)[pid] || 0);
    $('payNote').innerHTML = s === 'cards' ? '' : 'يُخصم من الخزينة: <b class="num">' + SG.fmt2(need) + '</b> ' + (pc === 'dinar' ? 'د.ل' : '$') + ' · الرصيد ' + SG.fmt2(bal) + (pc === 'dinar' ? ' د.ل' : ' $') + (need > bal + 0.009 ? ' <span style="color:var(--down)">· الرصيد لا يكفي</span>' : '');
    $('saveBtn').disabled = !(cost > 0);
  }
  $('costLydIn').addEventListener('input', () => { const l = parseFloat($('costLydIn').value) || 0, c = parseFloat($('cost').value) || 0, r = parseFloat($('rate').value) || 0;
    if (c > 0) $('rate').value = Math.round(l / c * 10000) / 10000; else if (r > 0) $('cost').value = Math.round(l / r * 100) / 100; calc(); });
  document.querySelectorAll('input[name=src]').forEach(i => i.addEventListener('change', () => { if (i.dataset.cur) { const r = document.querySelector('input[name=pay_currency][value=' + i.dataset.cur + ']'); if (r) r.checked = true; } calc(); }));
  document.querySelectorAll('input[name=pay_currency]').forEach(i => i.addEventListener('change', () => calc()));
  $('paySel').addEventListener('change', () => { const id = $('paySel').value, u = +(BAL.usd[id] || 0), l = +(BAL.lyd[id] || 0); const r = document.querySelector('input[name=pay_currency][value=' + (u <= 0 && l > 0 ? 'dinar' : 'dollar') + ']'); if (r) r.checked = true; calc(); });
  if ($('cardQ')) $('cardQ').addEventListener('input', () => { const t = $('cardQ').value.replace(/\s/g, ''); document.querySelectorAll('#cardsBox .crd').forEach(c => c.style.display = !t || c.dataset.no.replace(/\s/g, '').includes(t) ? '' : 'none'); });
  document.querySelectorAll('.pinB').forEach(b => b.onclick = async () => { const v = b.nextElementSibling; if (!v.hidden) { v.hidden = true; return; } try { const r = await (await fetch(b.dataset.url)).json(); v.textContent = 'الرمز: ' + (r.pin || '—'); v.hidden = false; } catch (e) { alert('ما قدرناش نجيبو الرمز'); } });
  $('q').addEventListener('input', render);
  $('q').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); const b = document.querySelector('#freeRows .add'); if (b) add(b.dataset.id); } });
  ['tripCode', 'tripStatus', 'waSw'].forEach(id => $(id).addEventListener('input', render));
  $('waSw').addEventListener('change', render);
  ['cost', 'rate'].forEach(id => $(id).addEventListener('input', () => calc()));
  document.querySelectorAll('.cardAmt').forEach(i => i.addEventListener('input', () => calc()));
  if ($('cardsAuto')) $('cardsAuto').onclick = () => { let left = parseFloat($('cost').value) || 0; document.querySelectorAll('.cardAmt').forEach(i => { const v = Math.min(left, +i.dataset.bal); i.value = v > 0 ? Math.round(v * 100) / 100 : 0; left -= Math.max(0, v); }); calc(); };
  $('tf').addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.id !== 'q') e.preventDefault(); });
  if (!srcEl()) { const f = document.querySelector('input[name=src]'); if (f) f.checked = true; }
  render();
})();
</script>
@endpush
