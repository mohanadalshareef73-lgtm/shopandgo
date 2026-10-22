@extends('sg.layout')
@section('title', 'السلات')
@section('bodyClass', 'soft')
@php
    use App\Models\CartQuote as CQ;
    $retailer = $filters['retailer'] ?? null;
    $op = $filters['operator_status'] ?? null;
    $keep = fn ($extra = []) => array_filter(array_merge(['retailer' => $retailer, 'search' => $filters['search'] ?? null, 'session_uuid' => $filters['session_uuid'] ?? null, 'per_page' => $perPage != 25 ? $perPage : null, 'operator_status' => $op], $extra), fn ($v) => $v !== null && $v !== '');
    $opColor = ['new' => '#6250d6', 'following_up' => '#2a78d6', 'editing' => '#eda100', 'confirmed' => '#12805c', 'buying' => '#eb6834', 'purchased' => '#12805c', 'cancelled' => '#c4352f', 'dismissed' => '#8a90a3'];
    $waiting = ($counts['new'] ?? 0) + ($counts['following_up'] ?? 0) + ($counts['editing'] ?? 0);
    $confirmed = ($counts['confirmed'] ?? 0) + ($counts['buying'] ?? 0) + ($counts['purchased'] ?? 0);
    $lost = ($counts['cancelled'] ?? 0) + ($counts['dismissed'] ?? 0);
    $tiles = ['#f1effc', '#e8f0fb', '#e6f4ee', '#fff4dc', '#fbeceb'];
@endphp
@push('style')
<style>
.ct { display: grid; grid-template-columns: 2fr 1.3fr .9fr 1.2fr 140px; gap: 16px; align-items: center; padding: 12px 16px; border-top: 1px solid var(--line-soft); }
.ct:first-child { border-top: 0; }
.ct:hover { background: #fafbfd; }
.ct-h { display: grid; grid-template-columns: 2fr 1.3fr .9fr 1.2fr 140px; gap: 14px; padding: 10px 16px; font-size: 12px; color: var(--ink-3); border-bottom: 1px solid var(--line-soft); }
.thumbs { display: flex; gap: 4px; flex: none; }
.thumb { width: 42px; height: 52px; border-radius: 10px; overflow: hidden; position: relative; display: grid; place-items: center; font-size: 15px; font-weight: 700; color: var(--ink-2); }
.thumb img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.thumb.more { background: var(--line-soft); font-family: InterNum; font-size: 12px; }
.cart-id { display: flex; gap: 12px; align-items: center; min-width: 0; }
.cart-id b { font-size: 14.5px; display: block; }
.cart-id .meta { font-size: 12px; color: var(--ink-3); }
.rt { font-size: 10.5px; font-weight: 800; letter-spacing: .5px; padding: 2px 7px; border-radius: 6px; font-family: InterNum; }
.rt.shein { background: #161a2e; color: #fff; } .rt.amazon { background: #ff9900; color: #161a2e; } .rt.iherb { background: #458500; color: #fff; }
.who3 { display: flex; gap: 10px; align-items: center; min-width: 0; }
.who3 .av { width: 36px; height: 36px; font-size: 14px; flex: none; }
.who3 b { font-size: 14px; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.who3 small { font-size: 12px !important; color: var(--ink-3); font-family: InterNum; direction: ltr; unicode-bidi: isolate; display: block; text-align: right; }
.tot b { font-family: InterNum; font-size: 17px; font-weight: 800; } .tot small { font-size: 12px; color: var(--ink-3); display: block; }
.opst { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 700; padding: 4px 11px; border-radius: 99px; white-space: nowrap; }
.opst i { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.qs { font-size: 11.5px; color: var(--ink-3); margin-top: 5px; display: block; }
.qs.wait { color: var(--warn); } .qs.bad { color: var(--down); } .qs.ok { color: var(--up); }
.wal3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 10px; }
.wal3 div { background: var(--line-soft); border-radius: 14px; padding: 12px; } .wal3 div:first-child { background: var(--up-bg); }
.wal3 small { font-size: 12px; color: var(--ink-3); display: block; } .wal3 bdi { font-family: InterNum; font-size: 18px; font-weight: 800; }
.fx3 { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--line-soft); }
.fx3 select { height: 38px; width: auto; }
@media (max-width: 1100px) { .ct, .ct-h { grid-template-columns: 1fr 1fr; } .ct-h { display: none; } }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>السلات</h1><div class="sub">سلات شي إن وروابط آي هيرب ومنتجات أمازون من الواتساب: من عرض السعر حتى تأكيد الطلب</div></div>
    <div class="acts">
        @can('cart-create')@foreach (['shein' => '+ فحص سلة شي إن', 'iherb' => '+ فحص آي هيرب', 'amazon' => '+ منتج أمازون'] as $store => $checkLabel)@if ($storeStates[$store] ?? true)<a class="btn {{ $store === 'shein' ? '' : 'ghost' }}" href="{{ route('carts.create', $store === 'shein' ? [] : ['retailer' => $store]) }}">{{ $checkLabel }}</a>@else<span class="btn ghost" style="opacity:.55;cursor:not-allowed" title="المتجر متوقف من الإعدادات">{{ ltrim($checkLabel, '+ ') }} · متوقف</span>@endif @endforeach @endcan
        <button class="sg-burger">☰</button>
    </div>
</div>

<div class="kpis g4">
    <a class="card kpi accent" href="{{ route('sg.carts', $keep(['operator_status' => null])) }}"><div class="lbl">كل السلات</div><div class="v num">{{ $counts->sum() }}</div><div class="s">شي إن وآي هيرب وأمازون</div></a>
    <a class="card kpi {{ $waiting ? 'red' : '' }}" href="{{ route('sg.carts', $keep(['operator_status' => 'new'])) }}"><div class="lbl">تستنى متابعة</div><div class="v num">{{ $waiting }}</div><div class="s">جديدة · قيد المتابعة · تعديل كميات</div></a>
    <div class="card kpi green"><div class="lbl">مؤكدة</div><div class="v num">{{ $confirmed }}</div><div class="s">مؤكدة · قيد الشراء · تم الشراء</div></div>
    <div class="card kpi"><div class="lbl">ملغاة أو مستبعدة</div><div class="v num">{{ $lost }}</div><div class="s">{{ $counts->sum() ? round($confirmed / $counts->sum() * 100) : 0 }}% من السلات تأكدت</div></div>
</div>

<div class="stages">
    <a href="{{ route('sg.carts', $keep(['operator_status' => null])) }}" class="stg {{ !$op ? 'on' : '' }}"><span class="dot" style="background:#262c4a"></span>الكل<span class="n">{{ $counts->sum() }}</span></a>
    @foreach (CQ::OPERATOR_STATUSES as $k => $v)
        <a href="{{ route('sg.carts', $keep(['operator_status' => $k])) }}" class="stg {{ $op === $k ? 'on' : '' }}"><span class="dot" style="background:{{ $opColor[$k] ?? '#8a90a3' }}"></span>{{ $v }}<span class="n">{{ $counts[$k] ?? 0 }}</span></a>
    @endforeach
</div>

<div class="grid g2 mb">    <div class="card" id="shein-wallet" data-url="{{ route('carts.wallet', [], false) }}" data-pause-on-empty-wallet="{{ $walletPauseEnabled ? '1' : '0' }}">
        <div style="display:flex;justify-content:space-between;align-items:center"><h3 style="margin:0">محفظة مزود شي إن</h3><button type="button" class="btn ghost sm" id="wallet-refresh">↻ تحديث</button></div>
        <div class="wal3">
            <div><small>المتاح</small><bdi data-wallet-amount="available">—</bdi></div>
            <div><small>المحجوز</small><bdi data-wallet-amount="held">—</bdi></div>
            <div><small>الإجمالي</small><bdi data-wallet-amount="balance">—</bdi></div>
        </div>
        <div class="note mt" id="wallet-status">جاري تحديث الرصيد…</div>
        <div class="hint mt" id="wallet-pause-status" hidden style="background:var(--warn-bg);color:var(--warn)"></div>
    </div>
    <div class="card">
        <h3>ماذا نقوم بـ فيهن</h3>
        @foreach (['new' => 'جديدة: اعرض العرض وأرسل للعميل', 'following_up' => 'قيد المتابعة: العميل لم يقرر بعد', 'editing' => 'تعديل كميات: العميل يريد يبدل', 'confirmed' => 'مؤكدة: جاهزة للشراء'] as $k => $t)
            <a class="ln" href="{{ route('sg.carts', $keep(['operator_status' => $k])) }}"><span><span class="dot" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $opColor[$k] }};margin-left:6px"></span>{{ $t }}</span><b class="num">{{ $counts[$k] ?? 0 }}</b></a>
        @endforeach
    </div>
</div>
<div class="card tw">
    <form method="get" class="fx3">
        @if ($op)<input type="hidden" name="operator_status" value="{{ $op }}">@endif
        <div class="sx" style="flex:1;min-width:220px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="كود الزبون، اسم، رقم هاتف، #رقم السلة… واضغط Enter" autocomplete="off" data-nolive data-suggest="cart"></div>
        <select name="retailer" class="inp" data-autosubmit><option value="">كل المتاجر</option><option value="shein" @selected($retailer === 'shein')>شي إن</option><option value="iherb" @selected($retailer === 'iherb')>آي هيرب</option><option value="amazon" @selected($retailer === 'amazon')>أمازون</option></select>
        <select name="session_uuid" class="inp" data-autosubmit><option value="">كل أرقام الاستقبال</option>@foreach ($receivingSessions as $uuid => $session)<option value="{{ $uuid }}" @selected(($filters['session_uuid'] ?? '') === $uuid)>{{ $session['phone'] ? $session['phone'] . ' · ' : '' }}{{ $session['name'] ?? $uuid }}</option>@endforeach</select>
        <select name="per_page" class="inp" data-autosubmit>@foreach ([10, 25, 50, 100] as $size)<option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} سلة</option>@endforeach</select>
        <button class="btn sm">🔍 بحث</button>
        @if (!empty($filters['search']) || $op || !empty($filters['session_uuid']))<a class="note" href="{{ route('sg.carts', array_filter(['retailer' => $retailer])) }}">✕ مسح</a>@endif
    </form>
    <div class="ct-h"><span>السلة</span><span>العميل</span><span>الإجمالي</span><span>الحالة</span><span></span></div>
    <div>
    @forelse ($carts as $cart)
        @php
            $items = data_get($cart->payload, 'result.items', []);
            $c = $cart->incomingMessage;
            $name = $c?->name ?: 'غير محدد';
            $col = $opColor[$cart->operator_status] ?? '#8a90a3';
            $qs = $cart->status === 'succeeded' ? 'ok' : (in_array($cart->status, ['pending', 'submitted']) ? 'wait' : 'bad');
        @endphp
        <div class="ct">
            <a class="cart-id" href="{{ route('carts.show', $cart) }}">
                <span class="thumbs">
                    @foreach (array_slice($items, 0, 3, true) as $ix => $it)
                        <span class="thumb" style="background:{{ $tiles[($cart->id + $ix) % 5] }}">{{ mb_substr($it['name'] ?? '•', 0, 1) }}@if (!empty($it['imageUrl']))<img src="{{ route('carts.products.image', [$cart, $ix]) }}" alt="" loading="lazy" onerror="this.remove()">@endif</span>
                    @endforeach
                    @if (count($items) > 3)<span class="thumb more">+{{ count($items) - 3 }}</span>@endif
                    @if (!count($items))<span class="thumb more">🛍</span>@endif
                </span>
                <span style="min-width:0"><b><span class="rt {{ in_array($cart->retailer, ['amazon', 'iherb'], true) ? $cart->retailer : 'shein' }}">{{ strtoupper(in_array($cart->retailer, ['amazon', 'iherb'], true) ? $cart->retailer : 'shein') }}</span> <span class="num">#{{ $cart->id }}</span></b>
                    <span class="meta">{{ count($items) }} منتجات · {{ $cart->origin === 'manual' ? 'إضافة يدوية' : 'من واتساب' }}{{ $c?->receiving_phone ? ' · على ' . preg_replace('/^218/', '0', $c->receiving_phone) : '' }}</span>
                    @if ($cart->retailer === 'amazon')<span class="meta" style="display:block">{{ \Illuminate\Support\Str::limit(data_get($cart->payload, 'result.items.0.name', 'منتج أمازون قيد الفحص'), 40) }}</span>@endif
                </span>
            </a>
            <div class="who3"><span class="av" style="background:{{ $col }}1a;color:{{ $col }}">{{ mb_substr($name, 0, 1) }}</span><div style="min-width:0"><b>{{ $name }}</b><small class="num" style="color:#111;font-weight:700;font-size:13.5px">{{ $cart->contact_phone ? preg_replace('/^218/', '0', $cart->contact_phone) : 'بدون رقم' }}</small><small>{{ $cart->created_at->format('Y-m-d H:i') }}</small></div></div>
            <div class="tot">
                @if ($cart->status === 'succeeded')
                    <b>{{ data_get($viewTotals, $cart->id . '.amount') ?? data_get($cart->payload, 'result.total.amount', '—') }}</b> <span class="note">{{ data_get($viewTotals, $cart->id . '.currency') ?? data_get($cart->payload, 'result.total.currency') }}</span>
                    <small>{{ data_get($viewTotals, $cart->id . '.item_count') ?? data_get($cart->payload, 'result.itemCount') }} قطع</small>
                @else
                    <span class="note">العرض لم يجهز بعد</span>
                @endif
            </div>
            <div>
                <span class="opst" style="background:{{ $col }}1a;color:{{ $col }}"><i></i>{{ CQ::OPERATOR_STATUSES[$cart->operator_status] ?? $cart->operator_status }}</span>
                <span class="qs {{ $qs }}">{{ $qs === 'ok' ? '✓ ' : '' }}{{ CQ::QUOTE_STATUSES[$cart->status] ?? $cart->status }}{{ $cart->notified_at ? ' · أُرسل للعميل' : '' }}</span>
                @if (in_array($cart->last_error, [\App\Services\CartWalletPausedException::EMPTY, \App\Services\CartWalletPausedException::INSUFFICIENT], true))<span class="qs wait">⏸ تستنى شحن المحفظة</span>@endif
            </div>
            <div style="display:flex;gap:6px;justify-content:flex-end">
                @if ($cart->status === 'succeeded' && !($cart->booked_done_at ?? null))@can('cart-list')<button type="button" class="btn green sm" onclick="openCB({{ json_encode(['id' => $cart->id, 'url' => route('sg.carts.confirmbook', $cart), 'name' => $name, 'phone' => $cart->contact_phone ? preg_replace('/^218/', '0', $cart->contact_phone) : '', 'total' => data_get($viewTotals, $cart->id . '.amount') ?? data_get($cart->payload, 'result.total.amount'), 'has' => (bool) $cart->order_id]) }})">✓ تأكيد الحجز</button>@endcan @elseif ($cart->booked_done_at ?? null)<span class="chip u">✓ تحجزت</span>@endif
                @if ($cart->whatsapp_url)<a class="btn ghost sm" href="{{ $cart->whatsapp_url }}" target="_blank" rel="noopener" title="واتساب" style="color:var(--wa)">💬</a>@endif
                <a class="btn ghost sm" href="{{ route('carts.show', $cart) }}">فتح ←</a>
            </div>
        </div>
    @empty
        <div class="empty"><span class="ic">🛍</span>{{ !empty($filters['search']) || $op ? 'لا يوجد سلات بالبحث هذا' : 'لا يزال لا يوجد سلات — تجي هنا من الواتساب أو من زر "فحص سلة"' }}</div>
    @endforelse
    </div>
    <div class="tw-f"><span>{{ $carts->firstItem() ?? 0 }}–{{ $carts->lastItem() ?? 0 }} من <b class="num">{{ $carts->total() }}</b> سلة</span>@include('sg.partials.pager', ['p' => $carts])</div>
</div>
<div class="modal-bg" id="mCB"><div class="modal" style="max-width:460px">
    <h3 id="cbT">✓ تأكيد الحجز</h3>
    <div class="who" style="margin-bottom:10px"><span class="av" id="cbAv">؟</span><div><b id="cbN"></b><div class="num" id="cbP" style="font-weight:700;color:#111;direction:ltr;text-align:right"></div></div></div>
    <div class="ln"><span>إجمالي السلة</span><b class="num" id="cbTot">—</b></div>
    <form method="post" id="cbF" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='⏳ يتأكد ويتبعت…'">@csrf
        <div id="cbPay">
            <div class="f mt"><label class="l">العربون المدفوع <span class="note">(اختياري — لو ما دفعش خليه 0)</span></label><input name="paid" class="inp big" inputmode="decimal" placeholder="0"></div>
            <div class="f"><label class="l">الدفع</label><select name="payment_id" class="inp">@foreach (\App\Models\PaymentMethod::where('status', true)->orderBy('id')->get() as $pm)<option value="{{ $pm->id }}" @selected($pm->id == \App\Services\AppSettings::cashPaymentId())>{{ $pm->name }}</option>@endforeach</select></div>
        </div>
        <div class="hint">لما تضغط «تأكيد الحجز» يصير الآتي مرة وحدة:<br>1) 🧾 ينعمل إيصال ويوصل للعميل على الواتساب ويطلع في «الطلبات»<br>2) 📤 السلة تمشي لقروب منتظرة: الرابط + PDF لو فيها تكرار + الكود<br>3) 📋 تنزل في «قائمة الحجز» ← تحجزت</div>
        <div style="display:flex;gap:8px;margin-top:12px"><button class="btn green" style="flex:1">✓ تأكيد الحجز</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
<script>
window.openCB = function (d) {
  document.getElementById('cbT').textContent = '✓ تأكيد الحجز · سلة #' + d.id;
  document.getElementById('cbN').textContent = d.name || '—';
  document.getElementById('cbAv').textContent = (d.name || '؟').trim().charAt(0);
  document.getElementById('cbP').textContent = d.phone || '';
  document.getElementById('cbTot').textContent = d.total ? d.total + ' د.ل' : '—';
  document.getElementById('cbPay').hidden = !!d.has;
  const f = document.getElementById('cbF'); f.action = d.url; f.reset();
  const b = f.querySelector('button'); b.disabled = false; b.textContent = '✓ تأكيد الحجز';
  document.getElementById('mCB').classList.add('open');
};
</script>
@include('sg.partials.suggest')
@endsection
@push('script')
<script>
(function () {
  const panel = document.getElementById('shein-wallet'); if (!panel) return;
  const refresh = document.getElementById('wallet-refresh'), status = document.getElementById('wallet-status'), pause = document.getElementById('wallet-pause-status');
  const amounts = panel.querySelectorAll('[data-wallet-amount]');
  async function load() {
    refresh.disabled = true; status.textContent = 'جاري تحديث الرصيد…';
    const ctl = new AbortController(), t = setTimeout(() => ctl.abort(), 12000);
    try {
      const r = await fetch(panel.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store', signal: ctl.signal });
      const d = await r.json();
      pause.hidden = !d.cart_replies_paused; pause.textContent = 'ردود جميع السلات متوقفة لحين شحن المحفظة. ستستأنف تلقائياً.';
      if (!r.ok) throw new Error();
      amounts.forEach(el => { const m = d.wallet[el.dataset.walletAmount]; el.textContent = m.amount + ' ' + m.currency; });
      status.textContent = 'آخر تحديث: ' + new Date(d.checked_at).toLocaleTimeString('ar-LY');
    } catch (e) {
      pause.hidden = panel.dataset.pauseOnEmptyWallet !== '1'; pause.textContent = 'ردود جميع السلات متوقفة مؤقتاً لحين التحقق من رصيد المحفظة.';
      amounts.forEach(el => el.textContent = '—'); status.textContent = 'تعذّر علينا نجيبو رصيد المحفظة. جرب تحديث.';
    } finally { clearTimeout(t); refresh.disabled = false; }
  }
  refresh.addEventListener('click', load); load();
})();
</script>
@endpush
