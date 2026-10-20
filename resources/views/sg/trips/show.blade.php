@extends('sg.layout')
@section('title', 'رحلة ' . $trip->trip_code)
@section('bodyClass', 'soft')
@php
    use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n);
    $s = $trip->summary();
    $ords = $trip->orders->sortBy('id');
@endphp
@section('content')
<div class="crumb"><a href="{{ route('sg.trips.index') }}">الرحلات</a> › {{ $trip->trip_code }}</div>
<div class="top">
    <div style="display:flex;align-items:center;gap:12px"><h1 class="code">{{ $trip->trip_code }}</h1><span class="chip n" style="font-size:13.5px">{{ $s['status'] ?: '—' }}</span>@if ($trip->coupon_used)<span class="chip u">كوبون</span>@endif</div>
    <div class="acts">
        @can('trip-purchase-create')<a class="btn" href="{{ route('finance.trips.create') }}">+ إضافة رحلة أخرى</a>@endcan
        @can('order-receive')<a class="btn green" href="{{ route('sg.trips.receive', $trip->id) }}">📦 استلام البضاعة</a>@endcan
        <a class="btn ghost" href="{{ route('order.ready.print') }}" target="_blank">🖨 قائمة الطرود</a>
        <button class="sg-burger">☰</button>
    </div>
</div>

@if ($trip->legacy)
<div class="flash" style="background:var(--s1-bg);color:var(--ink);border-color:transparent">📥 رحلة مستوردة من المنظومة القديمة. تقدر تغيّر حالتها وتعدل إيصالاتها وعملاءها عادي. @if ($trip->unknownCost())<b>التكلفة غير مسجلة</b> — لو تبيها في تقارير الأرباح اكتبها من «تعديل بيانات الشراء» (ما تنخصم من الخزينة).@else التكلفة مسجلة ولا تنخصم من الخزينة.@endif</div>
@endif
<div class="kpis g4">
    @can('profit-view')
    <div class="card kpi accent"><div class="lbl">ربح الرحلة</div><div class="v">@if ($trip->unknownCost())<span style="font-size:20px">التكلفة غير مسجلة</span></div><div class="s">رحلة قديمة</div></div>@else<span class="num">{{ $f($s['profit_dinar']) }}</span> <small>د.ل</small></div><div class="s">{{ $f($s['profit_dollar']) }} $ · {{ $s['margin'] }}%</div></div>@endif
    @endcan
    <div class="card kpi"><div class="lbl">مجموع الإيصالات</div><div class="v"><span class="num">{{ $f($s['sales_dinar']) }}</span> <small>د.ل</small></div><div class="s">= {{ $f($s['sales_dollar']) }} $ بسعر {{ $trip->rate }}</div></div>
    <div class="card kpi"><div class="lbl">دفعنا للموقع</div><div class="v"><span class="num">{{ $f($trip->cost) }}</span> <small>{{ TS::sign($trip->cost_currency) }}</small></div><div class="s">= {{ $f($s['cost_dinar']) }} د.ل · من {{ optional($trip->payment)->name }}{{ $trip->cards_amount > 0 ? ' · ' . $f($trip->cards_amount) . ' $ ببطاقات SHEIN' : '' }}</div></div>
    <div class="card kpi"><div class="lbl">الإيصالات</div><div class="v num">{{ $s['orders'] }}</div><div class="s">{{ $s['pieces'] }} قطعة · {{ $ords->where('delivered', true)->count() }} تسلّمت</div></div>
</div>

@can('trip-purchase-edit')
<h3 style="margin:6px 0 0" id="addCust">➕ إضافة عملاء للرحلة</h3>
@include('sg.trips.partials.picker')
@endcan
<div style="height:18px"></div>
<div class="side-lay">
    <div class="card tw">
        <div class="tw-h"><h3>الإيصالات في الرحلة</h3></div>
        <div class="scroll-x">
        <table class="t"><thead><tr><th>الإيصال</th><th>العميل</th><th class="n">القطع</th><th class="n">القيمة</th><th class="n">المدفوع</th><th class="n">الشحن</th><th class="n">الباقي</th><th>الحالة</th><th></th></tr></thead><tbody>
            @foreach ($ords as $o)
                @php $stg = $o->stage(); @endphp
                <tr>
                    <td><a href="{{ route('sg.orders.show', $o) }}" class="code">{{ $o->code }}</a></td>
                    <td class="cell2"><b>{{ optional($o->customer)->name }}</b><small class="num">{{ optional($o->customer)->code }}</small></td>
                    <td class="n num">{{ $o->received_qty !== null ? $o->received_qty . '/' : '' }}{{ $o->qty }}</td>
                    <td class="n num">{{ $f($o->total_price) }}</td>
                    <td class="n num plus">{{ $f($o->paid_price) }}</td>
                    <td class="n num">{{ $o->ship_price ? $f($o->ship_price) : '—' }}</td>
                    <td class="n num {{ $o->remaining() > 0.009 ? 'minus' : '' }}">{{ $o->remaining() > 0.009 ? $f($o->remaining()) : '✓' }}</td>
                    <td><span class="st {{ $stg }}"><i></i>{{ $stg === 'ready' ? 'في الرف ' . $o->storage_num : $o->stageLabel() }}</span></td>
                    <td style="display:flex;gap:6px"><a class="btn ghost sm" href="{{ route('order.invoice', $o->code) }}" target="_blank">🧾 الفاتورة</a>@can('trip-purchase-edit')@if (!$o->arrived)<form method="post" action="{{ route('finance.trips.detach', [$trip->id, $o->id]) }}" data-confirm="نطلعو الإيصال من الرحلة؟">@csrf @method('DELETE')<button class="btn ghost sm" style="color:var(--down)">إخراج</button></form>@endif @endcan</td>
                </tr>
            @endforeach
        </tbody></table>
        </div>

    </div>

    <div>
        @can('trip-purchase-edit')
        <div class="card mb">
            <h3>حالة الرحلة</h3>
            <form method="post" action="{{ route('sg.trips.status', $trip->id) }}">
                @csrf
                <select name="trip_status" class="inp mb">@foreach ($statuses as $st)<option @selected($s['status'] == $st->name)>{{ $st->name }}</option>@endforeach</select>
                <label class="ck"><input type="checkbox" name="send_whatsapp" value="1" checked> أرسل للعملاء رسالة بالحالة الجديدة</label>
                <button class="btn block mt">حفظ الحالة</button>
            </form>
        </div>
        <div class="card mb">
            <h3>💳 بطاقات SHEIN <span class="h-note">{{ $f($trip->cards_amount) }} $ من {{ $f($trip->cost) }} $</span></h3>
            @foreach ($trip->cardUses as $u)
                <div class="ln" style="font-size:13.5px"><a class="num" href="{{ route('sg.shein', ['id' => $u->card_id]) }}">{{ optional($u->card)->masked() }}</a><span><b class="num">{{ $f($u->amount) }} $</b> <span class="note">= {{ $f($u->cost_dinar) }} د.ل</span></span></div>
            @endforeach
            @if ($trip->cost_currency === 'dollar' && $trip->cards_amount < $trip->cost - 0.009 && $cards->count())
                @can('shein-card-use')
                <form method="post" action="{{ route('sg.shein.use') }}" style="margin-top:8px">
                    @csrf <input type="hidden" name="trip_purchase_id" value="{{ $trip->id }}">
                    <div style="display:flex;gap:6px"><select class="inp" style="height:38px" onchange="this.form.querySelector('.ua').name='amounts['+this.value+']'">@foreach ($cards as $c)<option value="{{ $c->id }}">{{ $c->masked() }} · {{ $f($c->balance) }} $</option>@endforeach</select><input class="inp numr ua" style="height:38px;width:90px" name="amounts[{{ $cards->first()->id }}]" placeholder="$"><button class="btn sm">استخدام</button></div>
                    <div class="note mt">الجزء هذا يرجع للخزينة ({{ optional($trip->payment)->name }}) لأنه دُفع بالبطاقة</div>
                </form>
                @endcan
            @elseif (!$trip->cardUses->count())
                <div class="note">لم نستخدم بطاقات في الرحلة هذه</div>
            @endif
        </div>
        <div class="card mb">
            <h3>بيانات الشراء</h3>
            @if ($trip->legacy)<div class="note" style="margin-bottom:8px">رحلة قديمة: تعديل التكلفة هنا للتقارير فقط، <b>ما ينخصم من الخزينة</b>.</div>@endif
            @role('Admin')
            <form method="post" action="{{ route('sg.trips.to-legacy', $trip->id) }}" data-confirm="{{ $trip->legacy ? 'تعليم إيصالات الرحلة القديمة تم التسليم؟ (الديون تبقى)' : 'تحويل الرحلة لرحلة قديمة؟ المبلغ يرجع للخزينة وتطلع من تقارير الأرباح' }}" style="background:var(--s1-bg);border-radius:12px;padding:10px 12px;margin-bottom:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                @csrf
                <label style="display:flex;gap:6px;align-items:center;font-size:13.5px;flex:1;min-width:200px"><input type="checkbox" name="deliver" value="1" checked> علّم إيصالاتها الأقدم من <input type="number" name="days" value="30" min="0" class="inp" style="width:70px;height:32px;padding:0 8px"> يوم «تم التسليم» (الديون تبقى)</label>
                <button class="btn ghost sm">{{ $trip->legacy ? '✓ تطبيق' : '↩ تحويل لرحلة قديمة' }}</button>
            </form>
            @endrole
            <form method="post" action="{{ route('finance.trips.update', $trip->id) }}">
                @csrf @method('PUT')
                <div class="grid g2">
                    <div><label class="l">كود الرحلة</label><input name="trip_code" class="inp" value="{{ $trip->trip_code }}"></div>
                    <div><label class="l">التاريخ</label><input type="date" name="purchased_at" class="inp" value="{{ $trip->purchased_at->format('Y-m-d') }}"></div>
                    <div><label class="l">دفعت (دولار)</label><input name="cost" class="inp numr" value="{{ $trip->cost }}"></div>
                    <div><label class="l">بكم الدولار</label><input name="rate" class="inp numr" value="{{ $trip->rate }}"></div>
                </div>
                <input type="hidden" name="cost_currency" value="{{ $trip->cost_currency }}">
                <label class="l mt">دفعت من</label><select name="payment_id" class="inp">@foreach ($payments as $p)<option value="{{ $p->id }}" @selected($trip->payment_id == $p->id)>{{ $p->name }}</option>@endforeach</select>
                <label class="ck mt"><input type="checkbox" name="coupon_used" value="1" @checked($trip->coupon_used)> كوبون</label>
                <input name="note" class="inp mt" value="{{ $trip->note }}" placeholder="ملاحظة">
                <button class="btn ghost block mt">حفظ التعديل</button>
            </form>
            @can('trip-purchase-delete')
                <form method="post" action="{{ route('finance.trips.destroy', $trip->id) }}" data-confirm="حذف تسجيل الشراء وإرجاع المبلغ للخزينة؟" style="margin-top:8px">@csrf @method('DELETE')<button class="btn red block">حذف تسجيل الشراء</button></form>
            @endcan
            <div class="note mt">سجّلها {{ optional($trip->createdUser)->name }} · {{ $trip->created_at->format('Y-m-d g:i') }}</div>
        </div>
        @endcan
    </div>
</div>
@endsection
