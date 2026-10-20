@extends('sg.layout')
@section('title', 'طلبات قديمة لم تُعلَّم')
@php use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); $nt = ($mode ?? '') === 'notrip'; @endphp
@section('content')
<div class="crumb"><a href="{{ route('sg.delivery') }}">التسليم</a> › طلبات قديمة</div>
<div class="top">
    <div><h1>طلبات قديمة لم تُعلَّم «تم التسليم»</h1><div class="sub">طلبات مسجلة «وصلت» من المنظومة القديمة وتسلّمها أصحابها فعلاً، لكنها لم تُعلَّم. علّمها مرة واحدة هنا</div></div>
    <div class="acts"><button class="sg-burger">☰</button></div>
</div>

<div class="seg" style="margin-bottom:14px;display:inline-flex"><a href="{{ route('sg.delivery.old') }}" class="{{ $nt ? '' : 'on' }}">وصلت ولم تُعلَّم</a><a href="{{ route('sg.delivery.old.notrip') }}" class="{{ $nt ? 'on' : '' }}">بدون رحلة ولم تُسلَّم</a></div>
@if ($nt)
<div class="card" style="margin-bottom:14px">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div><label class="l">عمر الطلب من (يوم)</label><input type="number" name="from" class="inp" value="{{ $from }}" style="width:130px"></div>
        <div><label class="l">إلى (يوم)</label><input type="number" name="to" class="inp" value="{{ $to }}" style="width:130px"></div>
        <button class="btn ghost">عرض</button>
    </form>
</div>
@else
<div class="kpis g5">
    <div class="card kpi accent"><div class="lbl">كل الطلبات «وصلت ولم تُسلَّم»</div><div class="v num">{{ $total }}</div><div class="s">في الرف حسب المنظومة</div></div>
    @foreach ($buckets as $label => $n)
        <div class="card kpi"><div class="lbl">{{ $label }}</div><div class="v num">{{ $n }}</div><div class="s">حسب تاريخ الوصول</div></div>
    @endforeach
</div>

@endif
@if (!$nt)
<div class="card" style="margin-bottom:14px">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div><label class="l">الطلبات التي وصلت قبل تاريخ</label><input type="date" name="before" class="inp" value="{{ $before }}"></div>
        <div><label class="l">أيها</label><select name="which" class="inp"><option value="all" @selected($which === 'all')>كلها (المدفوعة وغير المدفوعة)</option><option value="paid" @selected($which === 'paid')>المدفوعة بالكامل فقط</option></select></div>
        <button class="btn ghost">عرض</button>
    </form>
</div>
@endif

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px">
        <h3 style="margin:0">{{ $sel->count() }} طلب سيُعلَّم «تم التسليم»{{ $nt ? ' في رحلة OLD-NO-TRIP' : '' }}</h3>
        @if ($selUnpaid)<span class="chip w">{{ $selUnpaid }} منها عليه باقي ({{ $f($selDebt) }} د.ل) — يبقى ديناً على العميل</span>@endif
    </div>
    @if ($sel->count())
        <form method="post" action="{{ $nt ? route('sg.delivery.old.notrip.store') : route('sg.delivery.old.store') }}" style="background:var(--bg-2,#f4f5fa);border-radius:12px;padding:14px;margin-bottom:14px">
            @csrf
            @if ($nt)<input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}">@else<input type="hidden" name="before" value="{{ $before }}"><input type="hidden" name="which" value="{{ $which }}">@endif<input type="hidden" name="expected" value="{{ $sel->count() }}">
            <div style="font-size:14px;line-height:1.9">
                @if ($nt)✓ تنجمع في رحلة واحدة <b>OLD-NO-TRIP</b> (رحلة قديمة، حالتها وصلت).<br>@endif
                ✓ تتعلّم «تم التسليم» بتاريخ وصولها.<br>
                ✓ <b>لا تتحرك أي فلوس</b> في الخزينة.<br>
                ✓ الباقي على العميل (لو فيه) يبقى في الديون كما هو.<br>
                ✓ كل طلب يتسجل في سجل التعديلات.
            </div>
            <label style="display:flex;gap:8px;align-items:center;margin:12px 0;font-weight:700"><input type="checkbox" name="confirm" value="1" required style="width:18px;height:18px"> راجعت القائمة، وهذه الطلبات تسلّمها أصحابها فعلاً</label>
            <button class="btn">✓ علّم {{ $sel->count() }} طلب «تم التسليم»</button>
        </form>
        <div class="tw" style="overflow:auto;max-height:520px">
            <table class="t">
                <thead><tr><th>الإيصال</th><th>العميل</th><th>{{ $nt ? 'تاريخ الإيصال' : 'وصل' }}</th><th class="n">الباقي</th></tr></thead>
                <tbody>
                @foreach ($sel as $o)
                    <tr>
                        <td><a class="code" href="{{ route('sg.orders.show', $o->id) }}" target="_blank">{{ $o->code }}</a></td>
                        <td>{{ optional($o->customer)->name ?? '—' }} <span class="note num">{{ optional($o->customer)->code }}</span></td>
                        <td class="num">{{ $nt ? optional($o->created_at)->toDateString() . ' · ' . (int) optional($o->created_at)->diffInDays(now()) . ' يوم' : ($o->arrived_at ?: optional($o->updated_at)->toDateString()) }}</td>
                        <td class="n num">@if ($o->remaining() > 0.009)<b style="color:var(--down)">{{ $f($o->remaining()) }}</b>@else<span class="chip u">مدفوع</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="note">لا توجد طلبات بهذا الشرط</div>
    @endif
</div>
@endsection
