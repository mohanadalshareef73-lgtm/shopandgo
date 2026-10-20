@extends('sg.layout')
@section('title', 'الزيارات')
@section('bodyClass', 'soft')
@php use App\Http\Controllers\Sg\VisitsController as VC; $mx = max(1, $chart->max(fn ($r) => $r['cust'] + $r['guest'])); @endphp
@section('content')
<div class="top"><div><h1>📈 زيارات المنظومة</h1><div class="sub">مين يدخل، وكم مرة، ووين يقعد · {{ $period->label }}</div></div>
    <div class="acts">@include('sg.partials.period')<button class="sg-burger">☰</button></div></div>

<div class="kpis" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-bottom:14px">
    <div class="card" style="padding:14px"><small class="note">عملاء دخلوا حساباتهم</small><div style="font-size:26px;font-weight:800" class="num">{{ number_format($kpi['cust']) }}</div><small class="note">من {{ number_format($totalCust) }} عميل · {{ $totalCust ? round($kpi['cust'] / $totalCust * 100) : 0 }}%</small></div>
    <div class="card" style="padding:14px"><small class="note">دخلوا اليوم</small><div style="font-size:26px;font-weight:800;color:var(--up)" class="num">{{ number_format($kpi['today']) }}</div><small class="note">عميل</small></div>
    <div class="card" style="padding:14px"><small class="note">فتحات صفحات العملاء</small><div style="font-size:26px;font-weight:800" class="num">{{ number_format($kpi['custHits']) }}</div><small class="note">{{ $kpi['cust'] ? round($kpi['custHits'] / $kpi['cust'], 1) : 0 }} صفحة لكل عميل</small></div>
    <div class="card" style="padding:14px"><small class="note">زوار من غير دخول</small><div style="font-size:26px;font-weight:800" class="num">{{ number_format($kpi['guest']) }}</div><small class="note">صفحة الدخول والتسجيل والفواتير</small></div>
    <div class="card" style="padding:14px"><small class="note">من الجوال</small><div style="font-size:26px;font-weight:800" class="num">{{ $kpi['custHits'] ? round($kpi['mobile'] / $kpi['custHits'] * 100) : 0 }}%</div><small class="note">من زيارات العملاء</small></div>
    @if ($carts)<div class="card" style="padding:14px"><small class="note">سلات من الحساب / الواتساب</small><div style="font-size:26px;font-weight:800" class="num">{{ $carts['portal'] }} <span style="color:var(--ink-3);font-size:18px">/ {{ $carts['wa'] }}</span></div><small class="note">{{ $carts['booked'] }} حجزوا من حسابهم</small></div>@endif
</div>

<div class="card mb" style="padding:16px">
    <h3 style="margin:0 0 10px">آخر 14 يوم <span class="note">🟦 عملاء دخلوا · ⬜ زوار</span></h3>
    <div style="display:flex;align-items:flex-end;gap:6px;height:170px">
        @foreach ($chart as $r)
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%" title="{{ $r['d'] }}: {{ $r['cust'] }} عميل · {{ $r['guest'] }} زائر · {{ $r['staff'] }} موظف">
                <small class="num" style="font-size:11px">{{ $r['cust'] ?: '' }}</small>
                <div style="width:100%;max-width:34px;background:#d9dceb;border-radius:6px 6px 0 0;height:{{ round($r['guest'] / $mx * 140) }}px"></div>
                <div style="width:100%;max-width:34px;background:var(--brand);height:{{ max($r['cust'] ? 3 : 0, round($r['cust'] / $mx * 140)) }}px"></div>
                <small class="num note" style="font-size:10.5px;margin-top:4px">{{ \Carbon\Carbon::parse($r['d'])->format('j/n') }}</small>
            </div>
        @endforeach
    </div>
</div>

<div class="grid g2" style="gap:14px">
    <div class="card tw"><div class="tw-h"><h3 style="margin:0">الصفحات اللي يفتحوها العملاء أكثر</h3></div>
        <table class="t"><thead><tr><th>الصفحة</th><th class="n">مرات</th><th class="n">عملاء</th></tr></thead><tbody>
        @forelse ($pages as $pg)<tr><td>{{ VC::label($pg->page) }}</td><td class="n num">{{ number_format($pg->h) }}</td><td class="n num">{{ $pg->u }}</td></tr>
        @empty<tr><td colspan="3"><div class="empty">لسه ما فيش زيارات مسجلة</div></td></tr>@endforelse
        </tbody></table></div>
    <div class="card tw"><div class="tw-h"><h3 style="margin:0">⭐ أكثر العملاء دخولاً</h3></div>
        <table class="t"><thead><tr><th>العميل</th><th class="n">أيام</th><th class="n">صفحات</th><th>آخر دخول</th></tr></thead><tbody>
        @forelse ($topCust as $tc)@php $cn = $names[$tc->actor_id] ?? null; @endphp
            <tr><td>@if ($cn)<a href="{{ route('sg.clients', ['id' => $cn->id]) }}"><b>{{ $cn->name }}</b></a> <span class="note num">{{ $cn->code }}</span>@else<span class="note">#{{ $tc->actor_id }}</span>@endif</td><td class="n num">{{ $tc->d }}</td><td class="n num">{{ $tc->h }}</td><td class="note">{{ $tc->l ? \Carbon\Carbon::parse($tc->l)->diffForHumans() : '' }}</td></tr>
        @empty<tr><td colspan="4"><div class="empty">ما فيش</div></td></tr>@endforelse
        </tbody></table></div>
</div>

<div class="card tw" style="margin-top:14px"><div class="tw-h"><h3 style="margin:0">💼 حركة الموظفين والمندوبين</h3></div>
    <table class="t"><thead><tr><th>الاسم</th><th>النوع</th><th class="n">أيام شغل</th><th class="n">صفحات فتحها</th><th>آخر حركة</th></tr></thead><tbody>
    @forelse ($staff as $st)<tr><td><b>{{ $users[$st->actor_id] ?? ('#' . $st->actor_id) }}</b></td><td>{{ $st->actor === 'courier' ? '🛵 مندوب' : '💼 موظف' }}</td><td class="n num">{{ $st->d }}</td><td class="n num">{{ number_format($st->h) }}</td><td class="note">{{ $st->l ? \Carbon\Carbon::parse($st->l)->diffForHumans() : '' }}</td></tr>
    @empty<tr><td colspan="5"><div class="empty">ما فيش</div></td></tr>@endforelse
    </tbody></table></div>
<div class="note" style="margin-top:10px">التسجيل بدا من يوم التحديث — الأرقام تكبر مع الأيام.</div>
@endsection
