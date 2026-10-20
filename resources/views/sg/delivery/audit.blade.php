@extends('sg.layout')
@section('title', 'جرد الرفوف')
@php use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); @endphp
@section('content')
<div class="crumb"><a href="{{ route('sg.delivery') }}">التسليم</a> › جرد الرفوف</div>
<div class="top">
    <div><h1>جرد الرفوف</h1><div class="sub">اكتب أو امسح أكواد الإيصالات اللي قدامك في الرف، ونقارنوها بالمنظومة</div></div>
    <div class="acts"><button class="sg-burger">☰</button></div>
</div>

<div class="side-lay" style="grid-template-columns:380px 1fr">
    <div class="card">
        <form method="post" action="{{ route('sg.delivery.audit') }}">
            @csrf
            <div class="f"><label class="l">الرف <span class="note">(اختياري — فاضي = كل الرفوف)</span></label>
                <select name="shelf" class="inp"><option value="">كل الرفوف</option>@foreach ($shelves as $s)<option @selected($shelf === (string) $s)>{{ $s }}</option>@endforeach</select></div>
            <div class="f"><label class="l">الأكواد</label><textarea name="codes" class="inp numr" rows="12" placeholder="كود في كل سطر، أو مفصولة بمسافة" autofocus>{{ $codes }}</textarea></div>
            <button class="btn block">قارن</button>
        </form>
    </div>
    <div>
    @if ($res)
        <div class="kpis g3 mb">
            <div class="card kpi"><div class="lbl">✓ مطابقة</div><div class="v plus"><span class="num">{{ $res['ok']->count() }}</span></div><div class="s">من {{ $res['count'] }} كود كتبته</div></div>
            <div class="card kpi"><div class="lbl">⚠ في المنظومة ومش في الرف</div><div class="v minus"><span class="num">{{ $res['missing']->count() }}</span></div><div class="s">راجعها</div></div>
            <div class="card kpi"><div class="lbl">⚠ في الرف ومش مسجلة</div><div class="v minus"><span class="num">{{ $res['extra']->count() }}</span></div><div class="s">راجعها</div></div>
        </div>
        @if ($res['missing']->count())
        <div class="card tw mb"><div class="tw-h"><h3>المنظومة تقول قاعدة في الرف، وانت ما لقيتهاش — راجعها</h3></div><div class="scroll-x">
            <table class="t"><thead><tr><th>الإيصال</th><th>العميل</th><th>الرف</th><th class="n">القطع</th><th>وصلت</th></tr></thead><tbody>
            @foreach ($res['missing'] as $o)<tr><td><a class="code" href="{{ route('sg.orders.show', $o) }}">{{ $o->code }}</a></td><td class="cell2"><b>{{ optional($o->customer)->name }}</b><small class="num">{{ optional($o->customer)->code }}</small></td><td>{{ $o->storage_num ?: '—' }}</td><td class="n num">{{ $o->qty }}</td><td class="note num">{{ $o->arrived_at }}</td></tr>@endforeach
            </tbody></table></div></div>
        @endif
        @if ($res['extra']->count())
        <div class="card tw mb"><div class="tw-h"><h3>قاعدة في الرف ومش مسجلة في المنظومة — راجعها</h3></div><div class="scroll-x">
            <table class="t"><thead><tr><th>الكود</th><th>العميل</th><th>السبب</th></tr></thead><tbody>
            @foreach ($res['extra'] as $x)<tr><td>@if ($x->order)<a class="code" href="{{ route('sg.orders.show', $x->order) }}">{{ $x->code }}</a>@else<b class="num">{{ $x->code }}</b>@endif</td><td class="cell2">@if ($x->order)<b>{{ optional($x->order->customer)->name }}</b><small class="num">{{ optional($x->order->customer)->code }}</small>@else — @endif</td><td><span class="chip w">{{ $x->why }}</span></td></tr>@endforeach
            </tbody></table></div></div>
        @endif
        @if ($res['ok']->count())
        <div class="card tw"><div class="tw-h"><h3>✓ مطابقة</h3></div><div class="scroll-x">
            <table class="t"><thead><tr><th>الإيصال</th><th>العميل</th><th>الرف</th><th class="n">القطع</th></tr></thead><tbody>
            @foreach ($res['ok'] as $o)<tr><td class="code">{{ $o->code }}</td><td class="cell2"><b>{{ optional($o->customer)->name }}</b><small class="num">{{ optional($o->customer)->code }}</small></td><td>{{ $o->storage_num ?: '—' }}</td><td class="n num">{{ $o->qty }}</td></tr>@endforeach
            </tbody></table></div></div>
        @endif
    @else
        <div class="card"><div class="empty"><span class="ic">📋</span>اكتب الأكواد على اليمين واضغط «قارن»</div></div>
    @endif
    </div>
</div>
@endsection
