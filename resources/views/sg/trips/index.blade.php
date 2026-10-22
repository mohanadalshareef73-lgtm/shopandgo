@extends('sg.layout')
@section('title', 'الرحلات')
@section('bodyClass', 'soft')
@php
    use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n);
    $titles = ['prep' => ['قيد التجهيز', '#6250d6', 'prep'], 'way' => ['في الطريق', '#2a78d6', 'way'], 'arr' => ['وصلت ليبيا', '#12805c', 'ready'], 'deliv' => ['قيد التسليم', '#eda100', 'none'], 'done' => ['مقفولة', '#b7bccb', 'done']];
    $cur = request('stage', 'all');
    $all = collect($cols)->sum(fn ($c) => count($c));
    $shelfN = \App\Models\Order::where('arrived', true)->where('delivered', false)->where('status', '!=', 'canceld')->count();
@endphp
@push('style')
<style>
.trc b { font-family: InterNum; font-weight: 800; font-size: 15px; display: block; }
.trc small { color: var(--ink-3); font-size: 12px; }
.mini-prog { width: 110px; }
.mini-prog .b { height: 6px; background: #eef0f4; border-radius: 99px; overflow: hidden; }
.mini-prog .b i { display: block; height: 100%; background: var(--up); border-radius: 99px; }
.mini-prog small { font-size: 11.5px; color: var(--ink-3); }
.cmd .menu { min-width: 210px; }
.cmd .menu hr { border: 0; border-top: 1px solid #eef0f4; margin: 4px 0; }
.cmd .menu .danger { color: var(--down); }
.trip-steps { display: flex; gap: 3px; margin-top: 6px; width: 120px; }
.trip-steps i { flex: 1; height: 5px; border-radius: 3px; background: #e6e8ee; }
.trip-steps i.on { background: var(--brand); }
</style>
@endpush
@section('content')
<div class="crumb"><a href="{{ route('sg.orders.index') }}">الطلبات</a> › الرحلات</div>
<div class="top">
    <div><h1>الرحلات</h1><div class="sub">كل رحلة أين وصلت، وكم سلّمنا منها</div></div>
    <div class="acts">
        <form method="get" style="display:flex;gap:6px;align-items:center"><input name="q" value="{{ request('q') }}" class="inp numr" placeholder="🔍 رقم الرحلة… مثلاً B-0404" style="height:42px;width:230px;font-size:15px" autocomplete="off"><button class="btn ghost" style="height:42px">بحث</button></form>
        <div class="seg">@foreach ([1 => 'شهر', 2 => 'شهرين', 6 => '6 شهور', 120 => 'الكل'] as $k => $v)<a href="{{ request()->fullUrlWithQuery(['m' => $k]) }}" class="{{ (int) request('m', 2) === $k ? 'on' : '' }}">{{ $v }}</a>@endforeach</div>
        @can('trip-purchase-create')<a class="btn" href="{{ route('finance.trips.create') }}">✈ إضافة رحلة</a>@endcan
        <button class="sg-burger">☰</button>
    </div>
</div>
@can('trip-purchase-edit')
<details class="card mb" id="bulkT" style="padding:0">
    <summary style="cursor:pointer;padding:14px 16px;font-weight:800;font-size:15.5px;list-style:none">🔁 تغيير حالة مجموعة رحلات مرة وحدة ▾</summary>
    <form method="post" action="{{ route('sg.trips.bulk.status') }}" style="padding:0 16px 16px" id="btF">@csrf
        <label class="l">أرقام الرحلات (افصل بينهم بفاصلة ولا سطر جديد)</label>
        <textarea name="codes" id="btCodes" class="inp numr" rows="2" placeholder="B-0404, B-0405, 0406" required style="font-size:16px"></textarea>
        <div id="btPrev" class="mt"></div>
        <div class="grid g2 mt" style="align-items:end">
            <div><label class="l">الحالة الجديدة</label><select name="trip_status" class="inp" required style="font-size:15px"><option value="">— اختار —</option>@foreach ($statuses as $st)<option>{{ $st->name }}</option>@endforeach</select></div>
            <div><label class="ck"><input type="checkbox" name="send_whatsapp" value="1" checked> ابعت للعملاء رسالة بالحالة الجديدة</label></div>
        </div>
        <button class="btn mt" id="btGo" onclick="return confirm('نغيّرو حالة الرحلات اللي فوق كلهم؟')">✓ غيّر حالتهم</button>
    </form>
</details>
<script>
(function () {
  const ta = document.getElementById('btCodes'), box = document.getElementById('btPrev'); let t;
  ta.addEventListener('input', () => { clearTimeout(t); t = setTimeout(async () => {
    if (!ta.value.trim()) { box.innerHTML = ''; return; }
    try {
      const r = await (await fetch('{{ route('sg.trips.bulk.preview') }}?codes=' + encodeURIComponent(ta.value), { headers: { 'Accept': 'application/json' } })).json();
      box.innerHTML = '<table class="t"><thead><tr><th>الرحلة</th><th>حالتها توا</th><th class="n">إيصالات مفتوحة</th></tr></thead><tbody>' + r.rows.map(x => x.found
        ? `<tr><td><b class="num">${x.code}</b></td><td><b>${x.status}</b>${x.mixed ? '<small style="display:block;color:#c2410c">⚠ مخلّطة: ' + x.all + '</small>' : ''}</td><td class="n num">${x.n}</td></tr>`
        : `<tr style="background:#fdecea"><td><b class="num">${x.code}</b></td><td colspan="2" style="color:#b3261e">✕ ما لقيناش الرحلة هذي</td></tr>`).join('') + '</tbody></table>';
    } catch (e) { box.textContent = ''; }
  }, 400); });
})();
</script>
@endcan
@if (!empty($legacyPending))
<div class="card" style="margin-bottom:14px;display:flex;gap:14px;align-items:center;flex-wrap:wrap;border:1.5px dashed var(--s1);background:var(--s1-bg)">
    <div style="flex:1;min-width:260px"><b style="font-size:15.5px">📥 {{ count($legacyPending) }} رحلة من المنظومة القديمة غير ظاهرة هنا</b>
        <div class="note" style="margin-top:4px">مثل {{ collect($legacyPending)->sortByDesc('first_at')->take(4)->pluck('code')->implode('، ') }}… — استوردها باش تظهر بعملائها وتقدر تغيّر حالتها وتعدل بياناتها. <b>ما تتحرك أي فلوس</b>، والطلبات ما تتغيرش.</div></div>
    <form method="post" action="{{ route('sg.trips.import-legacy') }}" data-confirm="استيراد {{ count($legacyPending) }} رحلة قديمة؟ (بدون أي حركة مالية)">@csrf<button class="btn">📥 استيراد الرحلات القديمة</button></form>
</div>
@endif

<div class="kpis g4">
    <div class="card kpi accent"><div class="lbl">رحلات الشهر هذا</div><div class="v num">{{ $kpi['count'] }}</div><div class="s">{{ $kpi['orders'] }} إيصال · {{ $f($kpi['usd']) }} $</div></div>
    @can('profit-view')
    <div class="card kpi green"><div class="lbl">ربح الرحلات</div><div class="v"><span class="num">{{ $f($kpi['profit']) }}</span> <small>د.ل</small></div><div class="s"><span class="chip u"><span class="num">{{ $kpi['sales'] ? number_format($kpi['profit'] / $kpi['sales'] * 100, 1) : 0 }}%</span></span> متوسط النسبة</div></div>
    @endcan
    <a href="{{ route('finance.trips.create') }}" class="card kpi {{ $freeCount ? 'red' : '' }}"><div class="lbl">إيصالات ليس في رحلة</div><div class="v num">{{ $freeCount }}</div><div class="s">اضغط لإنشاء رحلة</div></a>
    <a href="{{ route('sg.orders.index', ['stage' => 'ready']) }}" class="card kpi"><div class="lbl">بضاعة في الرف تستنى أصحابها</div><div class="v num">{{ $shelfN }}</div><div class="s">اضغط لعرضها</div></a>
</div>

<div class="stages">
    <a href="{{ request()->fullUrlWithQuery(['stage' => null]) }}" class="stg {{ $cur === 'all' ? 'on' : '' }}"><span class="dot" style="background:#262c4a"></span>الكل<span class="n">{{ $all }}</span></a>
    @foreach ($titles as $k => [$t, $c])
        <a href="{{ request()->fullUrlWithQuery(['stage' => $k]) }}" class="stg {{ $cur === $k ? 'on' : '' }}"><span class="dot" style="background:{{ $c }}"></span>{{ $t }}<span class="n">{{ count($cols[$k]) }}</span></a>
    @endforeach
</div>

<div class="card tw">
    <form method="get" class="tw-h">
        @if (request('stage'))<input type="hidden" name="stage" value="{{ request('stage') }}">@endif
        @if (request('m'))<input type="hidden" name="m" value="{{ request('m') }}">@endif
        <div class="sx"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input name="q" id="tripQ" value="{{ request('q') }}" placeholder="اكتب جزء من رقم الرحلة أو رقم إيصال… (يفلتر وأنت تكتب)" autocomplete="off"></div>
        @if (request('q'))<a class="note" href="{{ request()->fullUrlWithQuery(['q' => null]) }}">✕ مسح</a>@endif
    </form>
    <div class="scroll-x">
    <table class="t">
        <thead><tr><th>الرحلة</th><th>الحالة</th><th class="n">إيصالات</th><th class="n">عملاء</th><th class="n">دفعنا</th><th class="n">للعملاء</th>@can('profit-view')<th class="n">الربح</th>@endcan<th>التسليم</th><th></th></tr></thead>
        <tbody>
        @forelse ($list as $trip)
            @php
                $s = $trip->summary(); $ords = $trip->orders->where('status', '!=', 'canceld');
                $del = $ords->where('delivered', true)->count(); $arr = $ords->where('arrived', true)->count();
                $pfs = $ords->map(fn ($o) => optional($o->platform)->name)->filter()->unique()->take(2);
                [$t, $c, $stc] = $titles[$trip->col];
            @endphp
            <tr>
                <td class="trc"><a href="{{ route('finance.trips.show', $trip->id) }}"><b>{{ $trip->trip_code }}</b></a>@if ($trip->legacy) <span class="chip n" style="font-size:11px" title="مستوردة من المنظومة القديمة">قديمة</span>@endif<small class="num">{{ $trip->purchased_at->format('Y-m-d') }}</small> @foreach ($pfs as $p)<span class="pf {{ strtolower($p) }}" style="font-size:10px;padding:1px 6px">{{ $p }}</span> @endforeach @if ($trip->coupon_used)<span class="chip u" style="font-size:10.5px;padding:1px 7px">كوبون</span>@endif</td>
                <td><span class="st" style="background:{{ $c }}1a;color:{{ $c === '#b7bccb' ? 'var(--ink-3)' : $c }}"><i></i>{{ $s['status'] ?: $t }}</span>@php $stepN = array_search($trip->col, array_keys($titles)) + 1; @endphp<div class="trip-steps" title="المرحلة {{ $stepN }} من 5">@for ($i = 1; $i <= 5; $i++)<i class="{{ $i <= $stepN ? 'on' : '' }}"></i>@endfor</div></td>
                <td class="n num">{{ $s['orders'] }}</td>
                <td class="n num">{{ $ords->pluck('customer_id')->unique()->count() }}</td>
                <td class="n num">@if ($trip->unknownCost())<span class="note" title="التكلفة غير مسجلة">—</span>@else{{ $f($trip->cost) }} <span class="note">{{ TS::sign($trip->cost_currency) }}</span>@endif @if ($trip->cards_amount > 0)<small style="display:block;color:var(--ink-3);font-size:11px">💳 {{ $f($trip->cards_amount) }} $ بطاقات</small>@endif</td>
                <td class="n num">{{ $f($s['sales_dinar']) }}</td>
                @can('profit-view')<td class="n">@if ($trip->unknownCost())<span class="note" style="font-size:12px">التكلفة غير مسجلة</span>@else<b class="num {{ $s['profit_dinar'] >= 0 ? 'plus' : 'minus' }}">{{ $f($s['profit_dinar']) }}</b><small style="display:block;color:var(--ink-3);font-size:11px" class="num">{{ $s['margin'] }}%</small>@endif</td>@endcan
                <td>@if ($arr)<div class="mini-prog"><div class="b"><i style="width:{{ $ords->count() ? $del / $ords->count() * 100 : 0 }}%"></i></div><small>تسلّم {{ $del }} من {{ $ords->count() }}</small></div>@else<span class="note">لم تصل بعد</span>@endif</td>
                <td><div style="display:flex;gap:6px;justify-content:flex-end;align-items:center">
                    @if (in_array($trip->col, ['arr', 'deliv']))@can('order-receive')<a class="btn green sm" href="{{ route('sg.trips.receive', $trip->id) }}">📦 استلام</a>@endcan
                    @else<a class="btn ghost sm" href="{{ route('finance.trips.show', $trip->id) }}">فتح</a>@endif
                    <div class="dropdown cmd"><span class="btn ghost sm" data-dd>الأوامر ▾</span><div class="menu">
                        <a href="{{ route('finance.trips.show', $trip->id) }}">📄 فتح الرحلة</a>
                        @can('trip-purchase-edit')<button type="button" data-status="{{ $trip->id }}" data-code="{{ $trip->trip_code }}" data-cur="{{ $s['status'] }}">🔄 تغيير الحالة</button>@endcan
                        @can('order-receive')<a href="{{ route('sg.trips.receive', $trip->id) }}">📦 استلام البضاعة</a>@endcan
                        @can('trip-purchase-edit')<a href="{{ route('finance.trips.show', $trip->id) }}#addCust">➕ إضافة إيصالات للرحلة</a>@endcan
                        @can('shein-card-use')@if ($cards && $trip->cost_currency === 'dollar')<a href="{{ route('finance.trips.show', $trip->id) }}">💳 دفع ببطاقة SHEIN</a>@endif @endcan
                        <a href="{{ route('sg.orders.index', ['trip' => $trip->trip_code]) }}">🧾 إيصالات الرحلة</a>
                        <a href="{{ route('order.ready.print') }}" target="_blank">🖨 قائمة الطرود</a>
                        @can('trip-purchase-edit')<a href="{{ route('finance.trips.show', $trip->id) }}#edit">✎ تعديل بيانات الشراء</a>@endcan
                        @can('trip-purchase-delete')<hr><form method="post" action="{{ route('finance.trips.destroy', $trip->id) }}" data-confirm="حذف تسجيل شراء الرحلة {{ $trip->trip_code }} وإرجاع المبلغ للخزينة؟">@csrf @method('DELETE')<button class="danger">🗑 حذف الرحلة</button></form>@endcan
                    </div></div>
                </div></td>
            </tr>
        @empty
            <tr><td colspan="9"><div class="empty"><span class="ic">✈</span>لا يوجد رحلات هنا</div></td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="tw-f">
        <span>المعروض <b class="num">{{ $list->count() }}</b> رحلة</span>
        <span>دفعنا <b class="num">{{ $f($list->where('cost_currency', 'dollar')->sum('cost')) }}</b> $</span>
        @can('profit-view')<span>الربح <b class="num plus">{{ $f($list->reject(fn ($t) => $t->unknownCost())->sum(fn ($t) => $t->summary()['profit_dinar'])) }}</b> د.ل</span>@endcan
    </div>
</div>

@foreach ($tripCustomers as $tc)
<div class="card tw mt">
    <div class="tw-h"><h3>👥 عملاء الرحلة <a class="code" href="{{ route('finance.trips.show', $tc->trip->id) }}">{{ $tc->trip->trip_code }}</a></h3><span class="note">{{ $tc->rows->count() }} عميل · {{ $tc->rows->sum(fn ($r) => $r->orders->count()) }} إيصال · {{ $tc->rows->sum('qty') }} قطعة</span>
        <a class="btn ghost sm" style="margin-inline-start:auto" href="{{ route('sg.trips.receive', $tc->trip->id) }}">📦 استلام / فرز</a></div>
    <div class="scroll-x">
    <table class="t">
        <thead><tr><th>العميل</th><th>الإيصالات</th><th class="n">طلب</th><th class="n">وصل</th><th class="n">طرود</th><th>الحالة</th><th class="n">عليه</th><th></th></tr></thead>
        <tbody>
        @foreach ($tc->rows as $r)
            <tr>
                <td><b>{{ optional($r->customer)->name }}</b><div class="note num">{{ optional($r->customer)->code }} · {{ optional($r->customer)->phone_1 }}</div></td>
                <td>@foreach ($r->orders as $o)<a class="code" style="font-size:12.5px" href="{{ route('sg.orders.show', $o) }}">{{ $o->code }}</a>{{ $loop->last ? '' : ' ' }}@endforeach</td>
                <td class="n num">{{ $r->qty }}</td>
                <td class="n num">{{ $r->arrived ? $r->received : '—' }}@if ($r->arrived && $r->received < $r->qty) <span class="chip w" style="font-size:10.5px">ناقص {{ $r->qty - $r->received }}</span>@endif</td>
                <td class="n num">{{ $r->arrived ? $r->bags : '—' }}</td>
                <td>@if ($r->delivered === $r->orders->count())<span class="chip u">تسلّم</span>@elseif ($r->arrived)<span class="chip w">في الرف</span>@else<span class="chip n">لم يصل بعد</span>@endif</td>
                <td class="n"><b class="num {{ $r->due > 0.009 ? 'minus' : 'plus' }}">{{ $r->due > 0.009 ? $f($r->due) : '✓' }}</b></td>
                <td><a class="btn ghost sm" href="https://wa.me/{{ \App\Services\MessageService::normalize((string) optional($r->customer)->phone_1) }}" target="_blank" style="color:var(--wa)">💬</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    <div class="tw-f"><span>الباقي على عملاء الرحلة <b class="num minus">{{ $f($tc->rows->sum('due')) }}</b> د.ل</span></div>
</div>
@endforeach

@can('trip-purchase-edit')
<div class="modal-bg" id="mStatus"><div class="modal">
    <h3>تغيير حالة الرحلة <span class="code" id="stCode"></span></h3>
    <form method="post" id="stForm" data-base="{{ route('sg.trips.status', 0) }}">
        @csrf
        <div class="opts mb" style="flex-direction:column">@foreach ($statuses as $st)<label><input type="radio" name="trip_status" value="{{ $st->name }}"><span>{{ $st->name }}</span></label>@endforeach</div>
        <label class="ck mb"><input type="checkbox" name="send_whatsapp" value="1" checked> أرسل للعملاء رسالة بالحالة الجديدة</label>
        <div style="display:flex;gap:8px"><button class="btn">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
@endcan
@endsection
@push('script')
<script>
// بحث الرحلات المباشر
(function () {
  const q = document.getElementById('tripQ'); if (!q) return;
  let t, ctl;
  q.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(async () => {
      const u = new URL(location.href); u.searchParams.delete('page');
      q.value.trim() ? u.searchParams.set('q', q.value.trim()) : u.searchParams.delete('q');
      try {
        if (ctl) ctl.abort(); ctl = new AbortController();
        const doc = new DOMParser().parseFromString(await (await fetch(u, { signal: ctl.signal })).text(), 'text/html');
        const main = q.closest('.card.tw'), nmain = doc.getElementById('tripQ')?.closest('.card.tw'); if (!main || !nmain) return;
        [...main.children].forEach(c => { if (c.tagName !== 'FORM') c.remove(); });
        [...nmain.children].forEach(c => { if (c.tagName !== 'FORM') main.appendChild(c); });
        document.querySelectorAll('.card.tw.mt').forEach(c => c.remove());
        let after = main; doc.querySelectorAll('.card.tw.mt').forEach(c => { after.after(c); after = c; });
        history.replaceState(null, '', u);
      } catch (e) {}
    }, 300);
  });
  q.form.addEventListener('submit', e => { e.preventDefault(); q.dispatchEvent(new Event('input')); });
})();
</script>
<script>
document.querySelectorAll('[data-status]').forEach(b => b.addEventListener('click', () => {
  const f = document.getElementById('stForm');
  f.action = f.dataset.base.replace(/\/0\/status$/, '/' + b.dataset.status + '/status');
  document.getElementById('stCode').textContent = b.dataset.code;
  f.querySelectorAll('input[name=trip_status]').forEach(r => r.checked = r.value === b.dataset.cur);
  b.closest('.dropdown').classList.remove('open');
  document.getElementById('mStatus').classList.add('open');
}));
</script>
@endpush
