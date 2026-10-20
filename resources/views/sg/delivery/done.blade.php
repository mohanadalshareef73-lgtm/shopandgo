@extends('sg.layout')
@section('title', 'الطلبيات المستلمة')
@section('bodyClass', 'soft')
@php
    use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n);
    $typeShort = ['توصيل شخصي' => ['شخصي من المحل', 'n', 'var(--brand)'], 'توصيل عن طريق مندوب' => ['مندوب توصيل', 'b', 'var(--s1)'], 'توصيل عن طريق مكتب' => ['مكتب توصيل', 'u', 'var(--s3)']];
    $tt = max(1, $byType->sum());
    $mx = max(1, $byUser->max() ?? 1);
    $cols = ['var(--s2)', 'var(--s1)', 'var(--s3)', 'var(--brand)', 'var(--s7)', 'var(--s4)'];
@endphp
@push('style')
<style>
.mth { display: flex; height: 14px; border-radius: 7px; overflow: hidden; gap: 2px; margin: 4px 0 12px; }
.lg div { display: flex; justify-content: space-between; font-size: 14px; padding: 5px 0; }
.lg i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-inline-end: 8px; }
.age .it { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--line-soft); }
.age .it .d { margin-inline-start: auto; }
.pay2 { display: block; font-size: 11.5px; color: var(--ink-3); font-family: Somar; }
</style>
@endpush
@section('content')
<div class="crumb"><a href="{{ route('sg.delivery') }}">التسليم</a> › الطلبيات المستلمة</div>
<div class="top">
    <div><h1>الطلبيات المستلمة</h1><div class="sub">كل طلبية طلعت من المحل: من استلمها، متى، ومن سلّمها، وكم قبضنا</div></div>
    <div class="acts">@include('sg.partials.period')<a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">⬇ تصدير Excel</a><button class="sg-burger">☰</button></div>
</div>

<div class="kpis g5">
    <div class="card kpi accent"><div class="lbl">سلّمنا {{ $period->label }}</div><div class="v"><span class="num">{{ $count }}</span> <small>طلبية</small></div><div class="s">@include('sg.partials.chg', ['cur' => $count, 'prev' => $prevCount]) {{ $period->prevLabel }} {{ $prevCount }}</div></div>
    <div class="card kpi"><div class="lbl">قبضنا عند التسليم</div><div class="v"><span class="num">{{ $f($collected) }}</span> <small>د.ل</small></div><div class="s">نقداً {{ $f($cash) }} · مصرف {{ $f($bank) }}</div></div>
    <div class="card kpi"><div class="lbl">عملاء استلموا</div><div class="v num">{{ $customers }}</div><div class="s">{{ $multi }} منهم استلموا أكثر من إيصال</div></div>
    <div class="card kpi"><div class="lbl">متوسط البقاء في الرف</div><div class="v"><span class="num">{{ $avgShelf ?? '—' }}</span> <small>يوم</small></div><div class="s">من الوصول للتسليم</div></div>
    <div class="card kpi {{ $old->count() ? 'red' : '' }}"><div class="lbl">في الرف أكثر من 7 أيام</div><div class="v num">{{ $old->count() }}</div><div class="s">يجب أن نتواصل معهم</div></div>
</div>

<div class="side-lay">
    <div class="card tw">
        <form class="tw-h" method="get">
            <input type="hidden" name="p" value="{{ $period->preset }}">
            <h3>المستلمة {{ $period->label }}</h3>
            <div class="sx"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input name="q" value="{{ request('q') }}" placeholder="رقم إيصال، كود عميل، اسم…"></div>
            <select name="type" class="pill" data-autosubmit><option value="">طريقة التسليم</option>@foreach ($typeShort as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v[0] }}</option>@endforeach</select>
            <select name="by" class="pill" data-autosubmit><option value="">سلّمها: الكل</option>@foreach ($byUser as $uid => $n)<option value="{{ $uid }}" @selected(request('by') == $uid)>{{ $users[$uid] ?? '—' }}</option>@endforeach</select>
        </form>
        <div class="scroll-x">
        <table class="t"><thead><tr><th>الوقت</th><th>الإيصال</th><th>العميل</th><th>الرحلة</th><th>التسليم</th><th>سلّمها</th><th class="n">قبضنا</th><th class="n">الباقي</th><th></th></tr></thead><tbody>
        @forelse ($orders as $o)
            @php
                $pays = $o->paids->filter(fn ($p) => $o->delivered_time && $p->created_at && abs($p->created_at->diffInMinutes($o->delivered_time)) <= 5);
                $got = $pays->sum('paid_price');
                $ty = $typeShort[$o->delivery_type] ?? [$o->delivery_type ?: '—', 'n'];
                $rem = $o->remaining();
            @endphp
            <tr>
                <td class="note num">{{ $o->delivered_time ? $o->delivered_time->format($period->preset === 'day' ? 'g:i' : 'j/n g:i') : $o->delivered_at }}</td>
                <td><a class="code" href="{{ route('sg.orders.show', $o) }}">{{ $o->code }}</a></td>
                <td class="cell2"><b>{{ optional($o->customer)->name }}</b><small class="num">{{ optional($o->customer)->code }}</small></td>
                <td><span class="code" style="font-size:12.5px;color:var(--ink-2)">{{ $o->trip_details ?: '—' }}</span></td>
                <td><span class="chip {{ $ty[1] }}">{{ $ty[0] }}{{ $o->delivery_name ? ' · ' . $o->delivery_name : '' }}</span></td>
                <td class="note">{{ optional($o->deliveredUser)->name ?: '—' }}</td>
                <td class="n"><b class="num" style="color:{{ $got ? 'var(--up)' : 'var(--ink-3)' }}">{{ $got ? '+' . $f($got) : '—' }}</b><span class="pay2">{{ $got ? $pays->map(fn ($p) => optional($p->paymentMethod)->name)->unique()->implode(' · ') : 'كان مدفوع' }}</span></td>
                <td class="n">@if ($rem > 0.009)<b class="num minus">{{ $f($rem) }}</b>@else<span class="note">✓</span>@endif</td>
                <td style="display:flex;gap:6px"><a class="btn ghost sm" target="_blank" href="{{ route('order.invoice', $o->code) }}">🧾 الفاتورة</a><a class="btn ghost sm" target="_blank" href="{{ route('sg.delivery.slip', ['ids' => $o->id, 'paid' => $got]) }}">🖨 إيصال الاستلام</a> @can('delivery-undo')@if ($o->delivered)<button type="button" class="btn ghost sm" style="color:var(--down)" onclick="openUndo({{ json_encode(['id' => $o->id, 'code' => $o->code, 'name' => optional($o->customer)->name, 'by' => optional($o->deliveredUser)->name, 'paid' => round((float) $o->paid_price, 2)]) }})">↩ إلغاء التسليم</button>@endif @endcan</td>
            </tr>
        @empty
            <tr><td colspan="9"><div class="empty"><span class="ic">📭</span>ما سلّمناش شيء في الفترة هذه</div></td></tr>
        @endforelse
        </tbody></table>
        </div>
        <div class="tw-f"><span>المعروض <b class="num">{{ $orders->count() }}</b> من <b class="num">{{ $orders->total() }}</b></span><span>قبضنا <b class="num plus">{{ $f($collected) }}</b> د.ل</span>@include('sg.partials.pager', ['p' => $orders])</div>
    </div>

    <div>
        <div class="card mb">
            <h3>كيف استلموا؟</h3>
            <div class="mth">@foreach ($typeShort as $k => $v)@if ($byType[$k] ?? 0)<span style="width:{{ $byType[$k] / $tt * 100 }}%;background:{{ $v[2] }}"></span>@endif @endforeach</div>
            <div class="lg">@foreach ($typeShort as $k => $v)<div><span><i style="background:{{ $v[2] }}"></i>{{ $v[0] }}</span><b class="num">{{ $byType[$k] ?? 0 }}</b></div>@endforeach</div>
        </div>
        <div class="card mb">
            <h3>من سلّم {{ $period->label }}</h3>
            @forelse ($byUser->take(6) as $uid => $n)
                <div class="hb"><div class="tt"><span>{{ $users[$uid] ?? '—' }}</span><b class="num">{{ $n }}</b></div><div class="b"><i style="width:{{ $n / $mx * 100 }}%;background:{{ $cols[$loop->index] }}"></i></div></div>
            @empty
                <div class="note">لا يوجد</div>
            @endforelse
        </div>
        <div class="card age">
            <h3>نسيوا بضاعتهم؟ <span class="h-note">في الرف أكثر من 7 أيام</span></h3>
            @forelse ($old->take(6) as $o)
                <div class="it"><span class="shelf">{{ $o->storage_num ?: '—' }}</span><div><b>{{ optional($o->customer)->name }}</b><div class="note">{{ $o->code }} · {{ $o->remaining() > 0.009 ? 'باقي ' . $f($o->remaining()) : 'مدفوع ✓' }}</div></div><div class="d"><span class="chip {{ $o->shelfDays() > 10 ? 'd' : 'w' }}"><span class="num">{{ $o->shelfDays() }}</span> يوم</span></div></div>
            @empty
                <div class="note">لا يوجد، الكل استلم ✓</div>
            @endforelse
            @if ($old->count())
                <form method="post" action="{{ route('sg.delivery.remind') }}">@csrf<button class="btn green block mt">💬 ذكّرهم الكل على واتساب ({{ $old->pluck('customer_id')->unique()->count() }})</button></form>
            @endif
        </div>
    </div>
</div>
@include('sg.partials.undo-delivery')
@endsection
