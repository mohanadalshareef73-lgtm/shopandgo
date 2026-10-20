@extends('sg.layout')
@section('title', 'منتجات نفدت')
@section('bodyClass', 'soft')
@php use App\Models\OrderSoldout as SO; use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); @endphp
@section('content')
<div class="crumb"><a href="{{ route('sg.orders.index') }}">الطلبات</a> › منتجات نفدت</div>
<div class="top"><div><h1>منتجات نفدت أثناء الشراء</h1><div class="sub">العميل يُبلَّغ بالمنتج وسعره، ثم يقرر: إلغاء (تُخصم القيمة) أو استبدال (إيصال جديد)</div></div><div class="acts"><button class="sg-burger">☰</button></div></div>
@can('soldout-manage')<div style="margin-bottom:12px"><button type="button" class="btn green" onclick="openSoldSearch()">➕ إضافة إيصال نواقص</button></div>@endcan
<div class="grid g3 mb">
    <div class="card kpi"><div class="lbl">بانتظار رد العميل</div><div class="v"><span class="num">{{ optional($counts->get('pending'))->n ?? 0 }}</span></div><div class="s">{{ $f(optional($counts->get('pending'))->v ?? 0) }} د.ل</div></div>
    <div class="card kpi"><div class="lbl">أُلغيت وخُصمت</div><div class="v"><span class="num">{{ optional($counts->get('cancelled'))->n ?? 0 }}</span></div><div class="s">{{ $f(optional($counts->get('cancelled'))->v ?? 0) }} د.ل</div></div>
    <div class="card kpi"><div class="lbl">استُبدلت بإيصالات جديدة</div><div class="v"><span class="num">{{ optional($counts->get('replaced'))->n ?? 0 }}</span></div><div class="s">{{ $f(optional($counts->get('replaced'))->v ?? 0) }} د.ل</div></div>
</div>
<div class="stages">
    @foreach (['pending' => 'بانتظار الرد', 'cancelled' => 'أُلغيت', 'replaced' => 'استُبدلت', 'all' => 'الكل'] as $k => $v)
        <a href="{{ route('sg.soldouts', ['status' => $k]) }}" class="stg {{ $status === $k ? 'on' : '' }}">{{ $v }}<span class="n">{{ $k === 'all' ? $counts->sum('n') : (optional($counts->get($k))->n ?? 0) }}</span></a>
    @endforeach
</div>
<div class="card tw">
    <form class="tw-h" method="get"><input type="hidden" name="status" value="{{ $status }}"><div class="sx" style="flex:1"><input name="q" value="{{ request('q') }}" placeholder="المنتج، رقم الإيصال، اسم العميل أو كوده…"></div></form>
    <div class="scroll-x"><table class="t">
        <thead><tr><th>الإيصال</th><th>العميل</th><th>المنتج</th><th class="n">القيمة</th><th>الحالة</th><th>الإبلاغ</th><th></th></tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr>
                <td><a class="code" href="{{ route('sg.orders.show', $r->order_id) }}#sold">{{ optional($r->order)->code }}</a></td>
                <td class="cell2"><b>{{ optional($r->customer)->name }}</b><small class="num">{{ optional($r->customer)->code }}</small></td>
                <td>{{ $r->item }}@if ($r->qty > 1) <span class="note">× {{ $r->qty }}</span>@endif @if ($r->status === 'replaced')<div class="note">⇄ {{ $r->replacement }} ({{ $f($r->replacement_price) }}) · <a class="code" href="{{ route('sg.orders.show', $r->new_order_id) }}">{{ optional($r->newOrder)->code }}</a></div>@endif</td>
                <td class="n"><b class="num">{{ $f($r->price) }}</b></td>
                <td><span class="chip {{ SO::STATUSES[$r->status][1] }}">{{ SO::STATUSES[$r->status][0] }}</span></td>
                <td class="note">{{ $r->notified_at ? $r->notified_at->diffForHumans() : 'لم يُبلَّغ' }}</td>
                <td>@if ($r->status === 'pending')<a class="btn ghost sm" href="{{ route('sg.orders.show', $r->order_id) }}#sold">اتخاذ القرار</a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty"><span class="ic">✓</span>لا توجد منتجات هنا</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@include('sg.partials.pager', ['p' => $rows])
@include('sg.soldouts.partials.quick')
@endsection
