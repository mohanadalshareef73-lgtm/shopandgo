@extends('sg.layout')
@section('title', 'ربح العملاء')
@php $f = fn ($n) => \App\Services\TreasuryService::fmt($n, 0); @endphp
@section('content')
<div class="crumb"><a href="{{ route('sg.clients') }}">العملاء</a> › ربح العملاء</div>
<div class="top">
    <div><h1>ربح كل عميل</h1><div class="sub">ربح كل رحلة يتقسم على إيصالاتها حسب قيمتها — باش تعرف مين يجيبلك ربح فعلاً</div></div>
    <div class="acts">@include('sg.partials.period')<button class="sg-burger">☰</button></div>
</div>
<div class="kpis g3 mb">
    <div class="card kpi"><div class="lbl">ربح العملاء في الفترة</div><div class="v plus"><span class="num">{{ $f($tot['profit']) }}</span> <small>د.ل</small></div><div class="s">من مبيعات {{ $f($tot['sales']) }} د.ل</div></div>
    <div class="card kpi"><div class="lbl">متوسط الهامش</div><div class="v"><span class="num">{{ $tot['sales'] > 0 ? number_format($tot['profit'] / $tot['sales'] * 100, 1) : 0 }}%</span></div><div class="s">{{ $tot['n'] }} عميل</div></div>
    <div class="card kpi"><div class="lbl">أكثر 20% من العملاء يجيبوا</div><div class="v"><span class="num">{{ $tot['profit'] > 0 ? round($list->sortByDesc('profit')->take(max(1, (int) ceil($list->count() * .2)))->sum('profit') / $tot['profit'] * 100) : 0 }}%</span></div><div class="s">من الربح</div></div>
</div>
<div class="card tw">
    <div class="tw-h">
        @foreach (['profit' => 'الأكثر ربح', 'margin' => 'أعلى هامش', 'low' => 'الأقل ربح', 'due' => 'عليهم ديون'] as $k => $v)<a class="pill {{ $sort === $k ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => $k]) }}">{{ $v }}</a>@endforeach
    </div>
    <div class="scroll-x"><table class="t"><thead><tr><th>#</th><th>العميل</th><th class="n">إيصالات</th><th class="n">مبيعات</th><th class="n">الربح</th><th class="n">الهامش</th><th class="n">عليه توا</th><th class="n">مرتجعات</th><th>التقييم</th></tr></thead><tbody>
    @forelse ($list as $i => $r)
        @php $flag = $r->profit <= 0 ? ['خسارة', 'd'] : ($r->due > $r->profit * 2 ? ['ديونه أكثر من ربحه', 'w'] : ($r->margin >= 25 ? ['ممتاز', 'u'] : null)); @endphp
        <tr><td class="num note">{{ $i + 1 }}</td>
            <td class="cell2"><a href="{{ route('sg.clients', ['id' => $r->c->id]) }}"><b>{{ $r->c->name }}</b></a><small class="num">{{ $r->c->code }}</small></td>
            <td class="n num">{{ $r->orders }}</td><td class="n num">{{ $f($r->sales) }}</td>
            <td class="n"><b class="num {{ $r->profit >= 0 ? 'plus' : 'minus' }}">{{ $f($r->profit) }}</b></td>
            <td class="n num">{{ number_format($r->margin, 1) }}%</td>
            <td class="n num {{ $r->due > 0.009 ? 'minus' : '' }}">{{ $r->due > 0.009 ? $f($r->due) : '✓' }}</td>
            <td class="n num">{{ $r->ret_n ? $r->ret_n . ' · ' . $f($r->ret_a) : '—' }}</td>
            <td>@if ($flag)<span class="chip {{ $flag[1] }}">{{ $flag[0] }}</span>@endif</td></tr>
    @empty
        <tr><td colspan="9"><div class="empty"><span class="ic">📊</span>ما فيش رحلات محسوبة في الفترة هذي</div></td></tr>
    @endforelse
    </tbody></table></div>
</div>
@endsection
