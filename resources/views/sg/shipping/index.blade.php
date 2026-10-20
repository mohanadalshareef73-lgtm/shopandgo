@extends('sg.layout')
@section('title', 'الشحن')
@section('bodyClass', 'soft')
@php
    use App\Services\TreasuryService as TS; use App\Services\ShippingService as SS; $f = fn ($n) => TS::fmt($n);
    $cur = request('stage');
@endphp
@push('style')
<style>
.og { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; padding: 3px 10px; border-radius: 99px; background: var(--line-soft); white-space: nowrap; }
.trk { font-family: InterNum; font-size: 12px; color: var(--ink-3); direction: ltr; unicode-bidi: isolate; display: block; }
.pk { font-family: InterNum; font-weight: 800; }
.ppk del { color: var(--ink-3); font-size: 12px; margin-left: 4px; } .ppk b { font-family: InterNum; }
.cost b { font-family: InterNum; font-size: 16px; font-weight: 800; display: block; } .cost small { color: var(--ink-3); font-size: 12px; font-family: InterNum; }
.shelf { font-family: InterNum; font-weight: 800; background: #262c4a; color: #fff; border-radius: 8px; padding: 3px 8px; font-size: 12.5px; }
.bulk { display: none; align-items: center; gap: 8px; background: var(--brand); color: #fff; padding: 10px 16px; border-radius: 14px; margin-bottom: 10px; }
.bulk.on { display: flex; }
.bulk select { height: 36px; border-radius: 10px; border: 0; padding: 0 10px; font-family: inherit; }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>الشحن</h1><div class="sub">شحنات العملاء الذين يشترون بأنفسهم ونشحن لهم · {{ collect($origins)->pluck('name')->implode('، ') }}</div></div>
    @php $pendN = \App\Models\Order::where('kind', 'shipping')->where('arrived', true)->where('delivered', false)->whereNull('arrived_notified_at')->count(); @endphp
    <div class="acts">@if ($pendN)<a class="btn" href="{{ route('sg.shipping.notify') }}">📨 مراجعة رسائل الوصول <span class="chip w">{{ $pendN }}</span></a>@endif @include('sg.partials.period')<a class="btn ghost" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">⬇ Excel</a>@can('system-edit')<a class="btn ghost" href="{{ route('sg.shipping.prices') }}">⚙ أسعار الكيلو</a>@endcan @can('shipping-edit')<a class="btn ghost" href="{{ route('sg.shipping.addresses') }}">📍 إرسال عناوين الشحن</a><a class="btn" href="{{ route('sg.shipping.create') }}">+ شحنة جديدة</a>@endcan<button class="sg-burger">☰</button></div>
</div>

<div class="kpis g4">
    <div class="card kpi accent"><div class="lbl">شحنات {{ $period->label }}</div><div class="v num">{{ $kpi['count'] }}</div><div class="s">{{ $f($kpi['kg']) }} كغ · {{ $kpi['customers'] }} عميل</div></div>
    <div class="card kpi"><div class="lbl">في المخزن بالخارج / في الطريق</div><div class="v num">{{ $kpi['abroad'] }} <small>/ {{ $kpi['transit'] }}</small></div><div class="s">@foreach ($origins as $k => $og){{ $og['flag'] }} {{ $byOrigin[$k] ?? 0 }} @endforeach</div></div>
    <a class="card kpi green" href="{{ request()->fullUrlWithQuery(['stage' => 'arrived']) }}"><div class="lbl">في الرف لدينا</div><div class="v num">{{ $kpi['shelf'] }}</div><div class="s">{{ $kpi['shelfOld'] }} تأخر استلامها</div></a>
    <div class="card kpi red"><div class="lbl">قيمة الشحن {{ $period->label }}</div><div class="v"><span class="num">{{ $f($kpi['usd']) }}</span> <small>$</small></div><div class="s">≈ {{ $f($kpi['lyd']) }} د.ل</div></div>
</div>

<div class="stages">
    <a href="{{ request()->fullUrlWithQuery(['stage' => null]) }}" class="stg {{ !$cur ? 'on' : '' }}"><span class="dot" style="background:#262c4a"></span>الكل<span class="n">{{ $counts['all'] }}</span></a>
    @foreach (SS::STAGES as $k => [$t, $c])
        @continue($k === 'cancelled' && !$counts[$k])
        <a href="{{ request()->fullUrlWithQuery(['stage' => $k]) }}" class="stg {{ $cur === $k ? 'on' : '' }}"><span class="dot" style="background:{{ $c }}"></span>{{ $t }}<span class="n">{{ $counts[$k] }}</span></a>
    @endforeach
</div>

@can('shipping-edit')
<form method="post" action="{{ route('sg.shipping.stage') }}" class="bulk" id="bulk">
    @csrf
    <b id="bulkN">0</b> شحنة محددة ·
    <select name="stage">@foreach (['warehouse', 'transit', 'arrived'] as $k)<option value="{{ $k }}">{{ SS::STAGES[$k][0] }}</option>@endforeach</select>
    <label class="ck" style="color:#fff"><input type="checkbox" name="send_whatsapp" value="1" checked> إرسال رسالة للعملاء <small style="opacity:.75">(رسالة الوصول تُراجع قبل الإرسال)</small></label>
    <button class="btn green sm">تحديث الحالة</button>
    <div id="bulkIds"></div>
</form>
@endcan

<div class="card tw">
    <form class="tw-h" method="get">
        @if ($cur)<input type="hidden" name="stage" value="{{ $cur }}">@endif
        <a class="pill {{ !request('origin') ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['origin' => null]) }}">الكل</a>
        @foreach ($origins as $k => $og)<a class="pill {{ request('origin') === $k ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['origin' => $k]) }}">{{ $og['flag'] }} {{ $og['name'] }}</a>@endforeach
        <div class="sx" style="flex:1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input name="q" value="{{ request('q') }}" placeholder="كود العميل، رقم التتبع، رقم الشحنة…"></div>
    </form>
    <div class="scroll-x"><table class="t">
        <thead><tr><th><input type="checkbox" id="chkAll"></th><th>الشحنة / التتبع</th><th>العميل</th><th>من</th><th class="n">طرود</th><th class="n">الوزن</th><th class="n">سعر الكيلو</th><th class="n">الشحن</th><th>الحالة</th><th>الرف</th><th class="n">الدفع</th><th></th></tr></thead>
        <tbody>
        @forelse ($rows as $o)
            @php $st = SS::stageOf($o); [$stt, $stc] = SS::STAGES[$st]; $og = $origins[$o->ship_origin] ?? null; $rem = $o->remaining(); @endphp
            <tr>
                <td>@if (!in_array($st, ['delivered', 'cancelled', 'courier']))<input type="checkbox" class="pick" value="{{ $o->id }}">@endif</td>
                <td><a class="code" href="{{ route('sg.orders.show', $o) }}">{{ $o->code }}</a><span class="trk">{{ $o->tracking_no ?: '—' }}</span></td>
                <td><b>{{ optional($o->customer)->name }}</b><div class="note num">{{ optional($o->customer)->code }}</div></td>
                <td><span class="og">{{ $og['flag'] ?? '🌍' }} {{ $og['name'] ?? $o->ship_origin }}</span></td>
                <td class="n"><span class="pk">{{ $o->bags_num }}</span> <span class="note">{{ $o->package_type }}</span></td>
                <td class="n">@if ($o->kilo)<span class="pk">{{ $f($o->kilo) }}</span> <span class="note">كغ</span>@else<span class="note">—</span>@endif</td>
                <td class="n ppk">@if ($o->usd_discount > 0)<del>{{ $f($o->usd_per_kg) }}$</del>@endif<b>{{ $f($o->usd_per_kg - $o->usd_discount) }}$</b></td>
                <td class="n cost"><b>{{ $f($o->usd_amount) }}$</b><small>@if (\App\Services\ShippingService::isDual($o)) @php $dp = \App\Services\ShippingService::dualPrices($o); @endphp💵 {{ $f($dp['cash']) }} · 🏦 {{ $f($dp['bank']) }} د.ل @else ≈ {{ $f($o->ship_price) }} د.ل @endif</small></td>
                <td><span class="st" style="background:{{ $stc }}1a;color:{{ $stc === '#b7bccb' ? 'var(--ink-3)' : $stc }}"><i></i>{{ $stt }}</span></td>
                <td>@if ($o->storage_num)<span class="shelf">{{ $o->storage_num }}</span>@else<span class="note">—</span>@endif</td>
                <td class="n">@if ($rem > 0.009)<span class="chip d">{{ $f($rem) }}</span>@else<span class="chip u">مدفوعة</span>@endif</td>
                <td><div class="dropdown"><span class="btn ghost sm" data-dd>الأوامر ▾</span><div class="menu">
                    <a href="{{ route('sg.orders.show', $o) }}">📄 فتح الشحنة</a>
                    @can('shipping-edit')<a href="{{ route('sg.shipping.edit', $o) }}">✎ تعديل</a>@endcan
                    <a href="{{ route('order.invoice', $o->code) }}" target="_blank">🧾 الفاتورة</a>
                    @can('shipping-edit')<a href="#" style="color:#1e7b45;font-weight:800" data-ship-ready="{{ route('sg.shipping.ready', $o) }}" data-preview="{{ route('sg.shipping.ready.preview', $o) }}" data-code="{{ $o->code }}">{{ $o->arrived_notified_at ? '📩 ابعت «جاهز للاستلام» تاني' : '✅ جاهز للاستلام + رسالة' }}</a>@endcan
                    @if ($st === 'arrived')<a href="{{ route('sg.delivery', ['q' => optional($o->customer)->code]) }}">✅ تسليم</a>@endif
                </div></div></td>
            </tr>
        @empty
            <tr><td colspan="12"><div class="empty"><span class="ic">📦</span>لا توجد شحنات</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="tw-f"><span>المعروض <b class="num">{{ $rows->count() }}</b></span><span>الوزن <b class="num">{{ $f($rows->sum('kilo')) }}</b> كغ</span><span>الشحن <b class="num">{{ $f($rows->sum('usd_amount')) }}</b> $</span></div>
</div>
@can('shipping-edit')@include('sg.shipping.ready-modal')@endcan
@endsection
@push('script')
<script>
(function () {
  const bulk = document.getElementById('bulk'); if (!bulk) return;
  const picks = [...document.querySelectorAll('.pick')];
  const upd = () => { const on = picks.filter(p => p.checked); bulk.classList.toggle('on', on.length > 0); document.getElementById('bulkN').textContent = on.length;
    document.getElementById('bulkIds').innerHTML = on.map(p => '<input type="hidden" name="ids[]" value="' + p.value + '">').join(''); };
  picks.forEach(p => p.addEventListener('change', upd));
  document.getElementById('chkAll').addEventListener('change', e => { picks.forEach(p => p.checked = e.target.checked); upd(); });
})();
</script>
@endpush
