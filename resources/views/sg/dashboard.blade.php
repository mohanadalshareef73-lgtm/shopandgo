@extends('sg.layout')
@section('title', 'التقرير العام')
@php
    use App\Services\TreasuryService as TS;
    $f = fn ($n) => TS::fmt($n);
    $pct = fn ($a, $b) => \App\Services\Period::pct($a, $b);
    $tk = [
        ['الطلبات', $f($ordCount), $ordCount, $ordCountPrev, false],
        ['قيمة الطلبات', $f($ordValue) . ' د.ل', $ordValue, $ordValuePrev, false],
        ['الكاش', $f($cash), $cash, $cashPrev, false],
        ['المصرف', $f($bank), $bank, $bankPrev, false],
        ['الفيزا', $f($visa) . ' $', $visa, $visaPrev, false],
        ['الديون', $f($debts), $debts, $debtsPrev, true],
        ['عملاء جدد', $f($act['customers'][0]), $act['customers'][0], $act['customers'][1], false],
        ['مسلّمة', $f($act['delivered'][0]), $act['delivered'][0], $act['delivered'][1], false],
    ];
    if ($rate) { $tk[] = ['سعر الدولار', number_format($rate, 2), $rate, $rate, false]; }
    $tickerHtml = collect($tk)->map(function ($t) use ($pct) {
        $p = $pct($t[2], $t[3]); $good = $t[4] ? $p <= 0 : $p >= 0;
        return '<span class="it"><span class="lbl">' . e($t[0]) . '</span><b class="num">' . e($t[1]) . '</b><span class="' . ($good ? 'u' : 'd') . ' num">' . ($p >= 0 ? '▲' : '▼') . ' ' . number_format(abs($p), 1) . '%</span></span>';
    })->implode('');
    $tColor = fn ($a, $b, $inv = false) => (($inv ? $pct($a, $b) <= 0 : $pct($a, $b) >= 0) ? '#12805c' : '#c4352f');
    $parts = [['الكاش', $cash, 'var(--s1)'], ['المصرف', $bank, 'var(--s2)'], ['دولار الفيزا (بالدينار)', $visa * $rate, 'var(--s3)'], ['ديون عند العملاء', $debts, 'var(--s4)']];
    $capTotal = max(1, array_sum(array_map(fn ($x) => max(0, $x[1]), $parts)));
@endphp

@push('style')
<style>
.g-orders { grid-template-columns: 2fr 1fr; }
.stack-tiles { display: grid; gap: 14px; grid-template-rows: repeat(3, 1fr); }
.stack-tiles .tile { display: grid; grid-template-columns: 1fr 120px; align-items: center; gap: 10px; padding: 12px 16px; }
.stack-tiles .tile .t-val { font-size: 24px; margin: 4px 0 2px; }
.stack-tiles .spark { height: 40px; margin: 0; }
.g-fin { grid-template-columns: 1.35fr 1fr; }
.act .t-val { font-size: 32px; }
.sec { margin-bottom: 22px; }
.sec-h { display: flex; align-items: baseline; justify-content: space-between; margin: 0 2px 10px; }
.sec-h h2 { font-size: 18px; margin: 0; }
@media (max-width: 1100px) { .g-orders, .g-fin { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="top">
    <div><h1>التقرير العام</h1><div class="sub">{{ $period->dateLabel() }}</div></div>
    <div class="acts">@include('sg.partials.period')<span class="note">{{ $period->cmpLabel }}</span><button class="sg-burger">☰</button></div>
</div>

@if (!auth()->user()->hasRole('Admin'))
<section class="sec">
    <div class="sec-h"><h2>👋 أهلاً {{ auth()->user()->name }}</h2><span class="note">{{ $period->label }}</span></div>
    <div class="grid g4">
        <a class="card tile act" href="{{ route('sg.pcarts') }}"><div class="t-lbl">🛒 حجوزات تستنى تأكيد</div><div class="t-val"><span class="num">{{ $waiting['pcart'] }}</span></div></a>
        <a class="card tile act" href="{{ route('sg.bqueue') }}"><div class="t-lbl">📋 قائمة الحجز</div><div class="t-val"><span class="num">{{ $waiting['bqueue'] }}</span></div></a>
        <a class="card tile act" href="{{ route('sg.delivery') }}"><div class="t-lbl">✅ جاهز للاستلام</div><div class="t-val"><span class="num">{{ $waiting['ready'] }}</span></div></a>
        <a class="card tile act" href="{{ route('sg.tickets') }}"><div class="t-lbl">💬 بلاغات مفتوحة</div><div class="t-val"><span class="num">{{ $waiting['tickets'] }}</span></div></a>
    </div>
    <div class="card mt">
        <b style="font-size:16px">📊 حركتي</b>
        <div class="grid g4 mt">
            <div><small style="display:block">💵 قبضت</small><b class="num plus" style="font-size:22px">{{ $f($myAct['money'] ?? 0) }}</b> د.ل</div>
            <div><small style="display:block">📦 سلّمت</small><b class="num" style="font-size:22px">{{ $myAct['delivered'] ?? 0 }}</b> طلب</div>
            <div><small style="display:block">🧾 سجلت</small><b class="num" style="font-size:22px">{{ $myAct['created'] ?? 0 }}</b> إيصال</div>
            <div><small style="display:block">⚙ حركات</small><b class="num" style="font-size:22px">{{ $myAct['actions'] ?? 0 }}</b></div>
        </div>
        @if ($myShift)
        <div class="hint mt" style="font-size:15px;display:flex;gap:14px;flex-wrap:wrap;align-items:center">
            <span>💼 ورديتك من <b class="num">{{ $myShift['since']->format('g:i a') }}</b></span>
            <span>+ قبضت كاش <b class="num plus">{{ $f($myShift['cash_in']) }}</b></span>
            <span>− صرفت <b class="num minus">{{ $f($myShift['cash_out']) }}</b></span>
            <span style="font-size:17px">👈 <b>المفروض معاك: <span class="num">{{ $f($myShift['expected']) }}</span> د.ل</b></span>
            @can('purchase-create')<a class="btn ghost sm" href="{{ route('sg.expenses') }}">🧾 سجّل مصروف</a>@endcan
        </div>
        @endif
    </div>
</section>
@endif

@role('Admin')
<div class="ticker"><div class="track">{!! $tickerHtml !!}{!! $tickerHtml !!}</div></div>

<section class="sec">
    <div class="sec-h"><h2>الطلبات</h2><span class="note">{{ $period->unit === 'hour' ? 'حسب الساعة' : ($period->unit === 'month' ? 'حسب الشهر' : 'حسب اليوم') }}</span></div>
    <div class="grid g-orders">
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <div>
                    <div style="font-size:14px;color:var(--ink-2)">إجمالي قيمة الطلبات</div>
                    <div class="big-num"><span class="num">{{ $f($ordValue) }}</span> <small style="font-size:15px;color:var(--ink-3);font-weight:400">د.ل</small></div>
                    @include('sg.partials.chg', ['cur' => $ordValue, 'prev' => $ordValuePrev]) <span class="note">{{ $period->prevLabel }}: <span class="num">{{ $f($ordValuePrev) }}</span></span>
                </div>
                <div class="legend"><span><i style="background:var(--s1)"></i>{{ $period->label }}</span><span><i style="background:var(--prev)"></i>{{ $period->prevLabel }}</span></div>
            </div>
            <svg class="chart" data-line data-chart='{{ json_encode($chart) }}' style="height:250px;margin-top:6px"></svg>
        </div>
        <div class="stack-tiles">
            <div class="card tile"><div><div class="t-lbl">عدد الطلبات</div><div class="t-val"><span class="num">{{ $f($ordCount) }}</span><small>طلب</small></div><div style="display:flex;gap:8px;align-items:center">@include('sg.partials.chg', ['cur' => $ordCount, 'prev' => $ordCountPrev])<span class="t-sub">{{ $period->prevLabel }} {{ $f($ordCountPrev) }}</span></div></div><svg class="spark" data-spark='{{ json_encode($sparkCount) }}' data-color="{{ $tColor($ordCount, $ordCountPrev) }}"></svg></div>
            <div class="card tile"><div><div class="t-lbl">متوسط قيمة الطلب</div><div class="t-val"><span class="num">{{ $f($avg) }}</span><small>د.ل</small></div><div style="display:flex;gap:8px;align-items:center">@include('sg.partials.chg', ['cur' => $avg, 'prev' => $avgPrev])<span class="t-sub">{{ $period->prevLabel }} {{ $f($avgPrev) }}</span></div></div><svg class="spark" data-spark='{{ json_encode(array_slice($chart['cur'], 0, $chart['curCount'])) }}' data-color="{{ $tColor($avg, $avgPrev) }}"></svg></div>
            <div class="card tile"><div><div class="t-lbl">المقبوض من العملاء</div><div class="t-val"><span class="num">{{ $f($collected) }}</span><small>د.ل</small></div><div style="display:flex;gap:8px;align-items:center">@include('sg.partials.chg', ['cur' => $collected, 'prev' => $collectedPrev])<span class="t-sub">عربون + باقي</span></div></div><svg class="spark" data-spark='{{ json_encode($collectedSeries) }}' data-color="{{ $tColor($collected, $collectedPrev) }}"></svg></div>
        </div>
    </div>
</section>

@endrole

@role('Admin')
<section class="sec">
    <div class="sec-h"><h2>رأس المال والحالة المالية</h2><span class="note">الأرصدة الآن · التغيير مقارنة ببداية الفترة</span></div>
    <div class="grid g4">
        <div class="card tile"><div class="t-lbl">الكاش</div><div class="t-val"><span class="num">{{ $f($cash) }}</span><small>د.ل</small></div><div>@include('sg.partials.chg', ['cur' => $cash, 'prev' => $cashPrev])</div><svg class="spark" data-spark='{{ json_encode($sparks['cash']) }}' data-color="{{ $tColor($cash, $cashPrev) }}"></svg></div>
        <div class="card tile"><div class="t-lbl">المصرف</div><div class="t-val"><span class="num">{{ $f($bank) }}</span><small>د.ل</small></div><div>@include('sg.partials.chg', ['cur' => $bank, 'prev' => $bankPrev])</div><svg class="spark" data-spark='{{ json_encode($sparks['bank']) }}' data-color="{{ $tColor($bank, $bankPrev) }}"></svg></div>
        <div class="card tile"><div class="t-lbl">دولار الفيزا / USDT</div><div class="t-val"><span class="num">{{ $f($visa) }}</span><small>$</small></div><div style="display:flex;gap:8px;align-items:center">@include('sg.partials.chg', ['cur' => $visa, 'prev' => $visaPrev])<span class="t-sub">≈ {{ $f($visa * $rate) }} د.ل</span></div><svg class="spark" data-spark='{{ json_encode($sparks['visa']) }}' data-color="{{ $tColor($visa, $visaPrev) }}"></svg></div>
        <div class="card tile"><div class="t-lbl">ديون عند العملاء</div><div class="t-val"><span class="num">{{ $f($debts) }}</span><small>د.ل</small></div><div style="display:flex;gap:8px;align-items:center">@include('sg.partials.chg', ['cur' => $debts, 'prev' => $debtsPrev, 'invert' => true])<span class="t-sub">{{ $debtCustomers }} عميل</span></div><a href="{{ route('sg.debts') }}" class="note" style="margin-top:10px;color:var(--s1)">تفاصيل الديون ←</a></div>
    </div>
    <div class="grid g-fin mt">
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <div><div style="font-size:14px;color:var(--ink-2)">رأس المال الكلي</div><div class="big-num"><span class="num">{{ $f($capital) }}</span> <small style="font-size:15px;color:var(--ink-3);font-weight:400">د.ل</small></div></div>
                @include('sg.partials.chg', ['cur' => $capital, 'prev' => $capitalPrev])
            </div>
            <div class="split" style="height:16px;margin:14px 0 12px">@foreach ($parts as $pt)<span style="width:{{ max(0, $pt[1]) / $capTotal * 100 }}%;background:{{ $pt[2] }}"></span>@endforeach</div>
            @foreach ($parts as $pt)
                <div class="ln"><span style="display:flex;align-items:center;gap:8px;color:var(--ink-2)"><i style="width:10px;height:10px;border-radius:3px;background:{{ $pt[2] }}"></i>{{ $pt[0] }}</span><span><b class="num">{{ $f($pt[1]) }}</b> <span class="note num">{{ number_format(max(0, $pt[1]) / $capTotal * 100, 0) }}%</span></span></div>
            @endforeach
        </div>
        @can('profit-view')
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><b style="font-size:16px">الحالة المالية للفترة</b><span class="chip {{ $finState[1] }}" style="font-size:13.5px;padding:5px 12px">{{ $finState[0] }}</span></div>
            <div class="ln"><span>ربح رحلات الشراء</span><b class="num plus">+{{ $f($fin['goodsProfit']['dinar']) }}</b></div>
            <div class="ln" style="{{ empty($fin['withShip']) ? 'opacity:.55' : '' }}"><span>ربح الشحن @if (empty($fin['withShip']))<small class="note">(مش محسوب)</small>@endif @role('Admin')<form method="post" action="{{ route('sg.finance.shiptoggle') }}" style="display:inline">@csrf<button class="btn ghost sm" style="padding:0 8px;min-height:0;height:24px;font-size:12px">{{ \App\Services\AppSettings::get('profit_with_ship', false) ? 'ما تحسبوش' : 'احسبه' }}</button></form>@endrole</span><b class="num {{ $fin['shipIncome']['dinar'] - $fin['shipCost']['dinar'] >= 0 ? 'plus' : 'minus' }}">{{ $f($fin['shipIncome']['dinar'] - $fin['shipCost']['dinar']) }}</b></div>
            <div class="ln"><span>مصاريف الشركة</span><b class="num minus">−{{ $f($fin['expenses']['dinar'] + $fin['paymentExpenses']['dinar']) }}</b></div>
            <div class="ln tot"><span>صافي الربح</span><b class="num {{ $net >= 0 ? 'plus' : 'minus' }}">{{ $f($net) }} <small style="font-family:Somar;font-weight:400;color:var(--ink-3)">د.ل</small></b></div>
            <div class="ln"><span class="note">سحوبات الشركاء</span><b class="num" style="color:var(--ink-2)">{{ $f($partnersOut) }}</b></div>
                @endcan
</div>
    </div>
</section>
@endrole

<section class="sec">
    <div class="sec-h"><h2>نشاط الفترة</h2><span class="note">{{ $period->cmpLabel }}</span></div>
    @php
        $ic = [
            'customers' => ['عملاء جدد', '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6"/><path d="M19 8v6M16 11h6"/>', route('sg.clients')],
            'orders' => ['طلبات جديدة', '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>', route('sg.orders.index')],
            'delivered' => ['طلبيات مسلّمة', '<path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M8 12l3 3 5-6"/>', route('sg.delivery.done')],
            'arrived' => ['بضاعة وصلت', '<path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/>', route('sg.orders.index', ['stage' => 'ready'])],
            'trips' => ['رحلات شراء', '<path d="M2 16l20-8-6 12-3-5-5-1z"/>', route('sg.trips.index')],
        ];
    @endphp
    <div class="grid g5">
        @foreach ($ic as $k => [$lbl, $svg, $link])
            <a href="{{ $link }}" class="card tile act">
                <div class="t-head"><span class="t-lbl">{{ $lbl }}</span><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $svg !!}</svg></span></div>
                <div class="t-val"><span class="num">{{ $f($act[$k][0]) }}</span></div>
                <div style="display:flex;gap:8px;align-items:center">@include('sg.partials.chg', ['cur' => $act[$k][0], 'prev' => $act[$k][1]])<span class="t-sub">{{ $period->prevLabel }} {{ $f($act[$k][1]) }}</span></div>
                <svg class="spark" data-kind="bar" data-spark='{{ json_encode(array_slice($act[$k][2], max(0, \App\Services\Series::elapsed($period) - 12), min(12, \App\Services\Series::elapsed($period)))) }}' data-color="#2a78d6"></svg>
            </a>
        @endforeach
    </div>
</section>
@role('Admin')
<section class="sec">
    <div class="sec-h"><h2>👥 حركة الموظفين</h2><span class="note">{{ $period->label }} · اضغط على أي موظف تشوف التفاصيل</span></div>
    <div class="card" style="padding:0">
    @forelse ($staffAct as $r)
        <details style="border-bottom:1px solid var(--line)">
            <summary style="list-style:none;cursor:pointer;display:grid;grid-template-columns:minmax(120px,1.3fr) repeat(5,minmax(70px,1fr));gap:8px;align-items:center;padding:12px 16px">
                <span><b>{{ $r['user']->name }}</b>@if (!empty($r['first']))<small style="display:block">من {{ $r['first'] }} لين {{ $r['last'] }}</small>@endif</span>
                <span><small style="display:block">💵 قبض</small><b class="num plus">{{ $f($r['money'] ?? 0) }}</b></span>
                <span><small style="display:block">كاش / مصرف</small><b class="num">{{ $f($r['cash'] ?? 0) }}</b> / <b class="num">{{ $f($r['bank'] ?? 0) }}</b></span>
                <span><small style="display:block">📦 سلّم</small><b class="num">{{ $r['delivered'] ?? 0 }}</b></span>
                <span><small style="display:block">🧾 إيصالات جديدة</small><b class="num">{{ $r['created'] ?? 0 }}</b></span>
                <span><small style="display:block">⚙ حركات</small><b class="num">{{ $r['actions'] ?? 0 }}</b></span>
            </summary>
            <div style="padding:0 16px 14px;display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
                <div><b>💵 الدفعات ({{ count($r['payList']) }})</b>
                    @forelse ($r['payList'] as [$code, $amt, $pm, $tm])<div class="ln" style="font-size:13.5px"><span>{{ $tm }} · <span class="num">{{ $code }}</span> · {{ $pm }}</span><b class="num">{{ $f($amt) }}</b></div>@empty<div class="note">ما فيش</div>@endforelse</div>
                <div><b>📦 سلّم ({{ count($r['delList']) }})</b><div class="num" style="font-size:13.5px;line-height:1.9">{{ implode(' · ', $r['delList']) ?: '—' }}</div>
                    <div class="mt"><b>القيمة اللي سجلها:</b> <span class="num">{{ $f($r['createdValue'] ?? 0) }}</span> د.ل</div>
                    <div><b>علّم وصول:</b> <span class="num">{{ $r['arrived'] ?? 0 }}</span> · <b>حجز سلات:</b> <span class="num">{{ $r['booked'] ?? 0 }}</span></div>
                    @if (!empty($r['undo']))<div style="color:#b3261e"><b>↩ ألغى تسليم:</b> <span class="num">{{ $r['undo'] }}</span> <small class="num">({{ implode('، ', $r['undoList'] ?? []) }})</small></div>@endif
                    @if (!empty($r['dupOver']))<div style="color:#c2410c"><b>⚠ إيصالات رغم التكرار:</b> <span class="num">{{ $r['dupOver'] }}</span> <small class="num">({{ implode('، ', $r['dupOverList'] ?? []) }})</small></div>@endif
                    <a class="note" style="color:var(--s1)" href="{{ route('sg.audit', ['user' => $r['user']->id]) }}">كل حركاته ←</a></div>
            </div>
        </details>
    @empty
        <div class="empty" style="padding:24px"><span class="ic">👥</span>ما فيش حركة للموظفين في الفترة هذي</div>
    @endforelse
    </div>
</section>
@endrole
@endsection
