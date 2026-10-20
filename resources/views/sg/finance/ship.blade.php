@extends('sg.layout')
@section('title', 'المالية · مصروفات الشحن')
@php
    use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n);
    $inc = $r['shipIncome']['dinar']; $cost = $r['shipCost']['dinar']; $pr = $inc - $cost;
    $dinarBal = collect($balances)->map(fn ($b) => $b['dinar']);
    $mx = max(1, $byCo->max() ?? 1);
@endphp
@section('content')
@include('sg.finance._head')

<div class="kpis g3">
    <div class="card kpi accent"><div class="lbl">دفعنا لشركة الشحن {{ $period->label }}</div><div class="v"><span class="num">{{ $f($cost) }}</span> <small>د.ل</small></div><div class="s">{{ $charges->count() }} دفعة</div></div>
    <div class="card kpi"><div class="lbl">قبضنا شحن من العملاء</div><div class="v"><span class="num">{{ $f($inc) }}</span> <small>د.ل</small></div><div class="s">من الإيصالات التي وصلت</div></div>
    @can('profit-view')
    <div class="card kpi {{ $pr >= 0 ? 'green' : 'red' }}"><div class="lbl">ربح الشحن</div><div class="v"><span class="num">{{ $f($pr) }}</span> <small>د.ل</small></div><div class="s">{{ $inc ? round($pr / $inc * 100, 1) . '%' : '' }}</div></div>
    @endcan
</div>

<div class="side-lay">
    <div>
        <div class="grid g2 mb">
            <div class="card">
                <h3>حسب الشركة <span class="h-note">{{ $period->label }}</span></h3>
                @forelse ($byCo as $name => $sum)
                    <div class="hb"><div class="tt"><span>{{ $name }}</span><b class="num">{{ $f($sum) }}</b></div><div class="b"><i style="width:{{ $sum / $mx * 100 }}%;background:var(--s2)"></i></div></div>
                @empty
                    <div class="note">لا يوجد مصروفات شحن</div>
                @endforelse
            </div>
            <div class="card">
                <h3>آخر 6 شهور</h3>
                <svg class="chart" data-bars data-h="200" data-w="420" data-chart='{{ json_encode(array_merge($six, ['unit' => 'د.ل', 'color' => '#eb6834', 'soft' => '#f5b89c', 'highlightLast' => true])) }}'></svg>
            </div>
        </div>
        <div class="card tw">
            <div class="tw-h"><h3>مصروفات الشحن</h3>
                <a class="pill {{ !request('co') ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['co' => null]) }}">الكل</a>
                @foreach ($companies as $co)<a class="pill {{ request('co') == $co->id ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['co' => $co->id]) }}">{{ $co->name }}</a>@endforeach
            </div>
            <div class="scroll-x">
            <table class="t"><thead><tr><th>التاريخ</th><th>الشركة</th><th>ملاحظة</th><th>من خزينة</th><th class="n">المبلغ</th><th></th></tr></thead><tbody>
            @forelse ($charges as $c)
                <tr><td class="note num">{{ \Illuminate\Support\Str::of($c->date)->limit(10, '') }}</td><td>{{ $c->company ?: '—' }}</td><td class="note wrap">{{ $c->note }}</td><td><span class="chip n">{{ optional($c->payment)->name }}</span></td><td class="n"><b class="num minus">−{{ $f($c->cost) }}</b> <span class="note">{{ $c->currency_sign }}</span></td><td>@can('shippingCharge-delete')<form method="post" action="{{ route('sg.finance.ship.destroy', $c) }}" data-confirm="حذف المصروف وإرجاع المبلغ للخزينة؟">@csrf @method('DELETE')<button class="more">×</button></form>@endcan</td></tr>
            @empty
                <tr><td colspan="6"><div class="empty"><span class="ic">🚚</span>لا يوجد مصروفات شحن في الفترة</div></td></tr>
            @endforelse
            </tbody></table>
            </div>
            <div class="tw-f"><span>المجموع <b class="num minus">{{ $f($charges->sum('cost')) }}</b> د.ل</span></div>
        </div>
    </div>

    <div>
        @can('shippingCharge-create')
        <form class="card mb sticky" method="post" action="{{ route('sg.finance.ship.store') }}">
            @csrf
            <h3>دفعت لشركة الشحن</h3>
            <div class="note mb">يُسجَّل كمصروف شحن، ولا يحتاج رحلة أو وزن</div>
            <div class="f"><label class="l">المبلغ</label><input name="cost" id="sC" class="inp big" required inputmode="decimal"></div>
            <div class="f"><label class="l">الشركة</label><select name="company_id" class="inp">@foreach ($companies as $co)<option value="{{ $co->id }}">{{ $co->name }}</option>@endforeach<option value="">شركة أخرى</option></select></div>
            <div class="grid g2 f">
                <div><label class="l">من خزينة</label><select name="payment_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == $cashId)>{{ $pm->name }} · {{ $f($dinarBal[$pm->id] ?? 0) }}</option>@endforeach</select></div>
                <div><label class="l">التاريخ</label><input type="date" name="date" class="inp" value="{{ now()->toDateString() }}"></div>
            </div>
            <div class="f"><label class="l">ملاحظة (اختياري)</label><input name="note" class="inp" placeholder="مثلا: شحنة الأسبوع"></div>
            <button class="btn block lg">حفظ</button>
        </form>
        @endcan
        <div class="card">
            <h3>شركات الشحن</h3>
            @forelse ($companies as $co)
                <div style="display:flex;gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid var(--line-soft)"><span style="font-size:22px">{{ $co->icon ?: '🚚' }}</span><div style="flex:1"><b>{{ $co->name }}</b><div class="note">{{ $co->route }}</div></div></div>
            @empty
                <div class="note">لا يوجد شركات مسجلة</div>
            @endforelse
            @can('shippingCharge-create')
            <form method="post" action="{{ route('sg.finance.companies.store') }}" style="display:grid;grid-template-columns:48px 1fr auto;gap:6px;margin-top:12px">
                @csrf
                <input name="icon" class="inp" placeholder="🚚" style="height:38px;text-align:center"><input name="name" class="inp" placeholder="اسم الشركة" required style="height:38px"><button class="btn ghost sm">+ إضافة</button>
            </form>
            @endcan
        </div>
    </div>
</div>
@endsection
