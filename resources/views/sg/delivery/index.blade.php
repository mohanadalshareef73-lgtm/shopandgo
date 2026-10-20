@extends('sg.layout')
@section('title', 'تسليم طلبية')
@section('bodyClass', 'soft')
@php use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); @endphp
@push('style')
<style>
.search { position: relative; margin-bottom: 14px; }
.search input { width: 100%; height: 66px; border: 2px solid var(--brand); border-radius: 16px; padding: 0 60px 0 20px; font: inherit; font-size: 22px; background: #fff; }
.search svg { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); width: 26px; height: 26px; color: var(--brand); }
.search .hint2 { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); font-size: 13px; color: var(--ink-3); }
.queue { display: flex; flex-direction: column; gap: 0; margin-bottom: 16px; font-size: 14px; color: var(--ink-2); background: #fff; border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
.queue .qh { padding: 10px 14px; font-weight: 800; color: var(--ink); background: var(--line-soft); }
.queue table.qt { margin: 0; }
.queue table.qt td, .queue table.qt th { padding: 8px 10px; font-size: 13.5px; }
.queue table.qt tr:hover td { background: var(--line-soft); }
.queue .c { font-family: InterNum; font-weight: 800; background: var(--line-soft); border-radius: 8px; padding: 2px 10px; font-size: 13px; min-width: 64px; text-align: center; }
.queue .nm { flex: 1; font-weight: 700; }
.queue .ct { font-size: 12.5px; color: var(--ink-3); }
.queue > .note { padding: 10px 14px; border-top: 1px solid var(--line); }
.cust { display: flex; align-items: center; gap: 16px; margin-bottom: 14px; flex-wrap: wrap; }
.cust .stats { margin-inline-start: auto; display: flex; gap: 22px; }
.cust .stats div { text-align: center; } .cust .stats small { display: block; font-size: 12px; color: var(--ink-3); } .cust .stats b { font-size: 20px; }
.rec { border: 2px solid var(--line); border-radius: 16px; background: #fff; margin-bottom: 12px; overflow: hidden; display: block; cursor: pointer; }
.rec.sel { border-color: var(--up); }
.rec input.pick { display: none; }
.rec .r-top { display: flex; align-items: center; gap: 14px; padding: 14px 16px; }
.ckb { width: 26px; height: 26px; border-radius: 8px; border: 2px solid #c9cdd8; display: grid; place-items: center; flex: none; color: #fff; font-size: 16px; }
.rec.sel .ckb { background: var(--up); border-color: var(--up); }
.rec .shelf2 { margin-inline-start: auto; text-align: center; background: var(--brand); color: #fff; border-radius: 14px; padding: 8px 18px; }
.rec .shelf2 small { display: block; font-size: 11.5px; color: #b9bfd8; }
.rec .shelf2 b { font-family: InterNum; font-size: 26px; font-weight: 800; }
.rec .r-mid { display: grid; grid-template-columns: repeat(4, 1fr); border-top: 1px solid var(--line-soft); }
.rec .r-mid div { padding: 10px 16px; border-left: 1px solid var(--line-soft); font-size: 14px; }
.rec .r-mid div:last-child { border-left: 0; }
.rec .r-mid small { display: block; font-size: 12px; color: var(--ink-3); }
.rec .r-mid b { font-size: 17px; }
.rec .r-money { display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: #f8f9fb; border-top: 1px solid var(--line-soft); font-size: 14px; flex-wrap: wrap; }
.rec .r-money .rem2 { margin-inline-start: auto; } .rec .r-money .rem2 b { font-size: 22px; color: var(--down); }
.r-part { display: flex; align-items: center; gap: 8px; padding: 10px 16px; border-top: 1px solid var(--line-soft); font-size: 13.5px; flex-wrap: wrap; cursor: default; }
.r-part .ci { width: 64px; height: 36px; border: 1px solid var(--line); border-radius: 9px; padding: 0 8px; font-family: InterNum; font-weight: 700; font-size: 15px; direction: ltr; text-align: right; }
.later { background: transparent; border: 2px dashed var(--line); border-radius: 16px; padding: 12px 16px; display: flex; align-items: center; gap: 14px; margin-bottom: 8px; opacity: .85; }
.pay { position: sticky; top: 16px; padding: 18px 20px; }
.pay .big b { display: block; font-size: 44px; font-weight: 800; line-height: 1.1; }
.pay .fld { background: rgba(255,255,255,.07); border-radius: 12px; padding: 10px 12px; margin-top: 10px; }
.pay .fld label.t { font-size: 13px; color: #c9cde0; }
.pay .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 6px; }
.pay .in { position: relative; }
.pay .in input { width: 100%; height: 48px; border-radius: 10px; border: 0; padding: 0 12px; font-family: InterNum; font-size: 19px; font-weight: 700; direction: ltr; text-align: right; }
.pay .in span { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 12px; color: var(--ink-3); }
.pay .opts span { background: transparent; border-color: rgba(255,255,255,.2); color: #dfe2ef; }
.pay .opts input:checked + span { background: #fff; color: var(--brand); border-color: #fff; }
.pay .ck { color: #dfe2ef; }
.pay select.inp { height: 40px; margin-top: 6px; }
@media (max-width: 900px) { .rec .r-mid { grid-template-columns: repeat(2, 1fr); } }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>تسليم طلبية</h1><div class="sub">العميل جاء ليستلم؟ اكتب كوده أو رقم هاتفه</div></div>
    <div class="acts"><span class="chip u" style="font-size:13.5px;padding:6px 12px">سلّمنا اليوم <span class="num">{{ $todayCount }}</span> طلبية</span><a class="btn ghost" href="{{ route('sg.delivery.done') }}">الطلبيات المستلمة</a><a class="btn ghost" href="{{ route('sg.delivery.audit') }}">📋 جرد الرفوف</a>@role('Admin')<a class="btn ghost" href="{{ route('sg.delivery.old') }}">🧹 طلبات قديمة</a>@endrole<button class="sg-burger">☰</button></div>
</div>

<form method="get" class="search no-embed">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
    <input name="q" id="dq" value="{{ $q }}" autofocus autocomplete="off" placeholder="كود العميل أو رقم هاتفه…">
    <span class="hint2">كود · هاتف · اسم · رقم إيصال</span>
    <div id="dSug" hidden style="position:absolute;top:100%;right:0;left:0;z-index:30;background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.12);max-height:360px;overflow:auto;margin-top:6px"></div>
</form>
<div class="queue no-embed"><div class="qh">📦 جاهزين يستلموا ومستنيينهم</div>
    @if ($queue->isEmpty())
        <span class="note">لا يوجد أحد</span>
    @else
    <div class="scroll-x"><table class="t qt">
        <thead><tr><th>الكود</th><th>العميل</th><th class="n">إيصالات</th><th class="n">القطع</th><th class="n">الطرود</th><th>نوع الطرد</th><th>الرف</th><th class="n">القيمة</th><th class="n">المتبقي بالشحن</th><th></th></tr></thead>
        <tbody>
        @foreach ($queue as $g)
            @php
                $qc = $g->first()->customer; $qCode = optional($qc)->code;
                $qPcs = $g->sum(fn ($o) => (int) ($o->received_qty ?? $o->qty));
                $qBags = $g->sum(fn ($o) => (int) ($o->bags_num ?: 1));
                $qTypes = $g->pluck('package_type')->filter()->unique()->implode('، ');
                $qShelf = $g->pluck('storage_num')->filter()->unique()->implode('، ');
                $qVal = $g->sum(fn ($o) => $o->grandTotal());
                $qRem = $g->sum(fn ($o) => max(0, $o->remaining()));
            @endphp
            <tr>
                <td><span class="c">{{ $qCode }}</span></td>
                <td class="nm">{{ optional($qc)->name }}</td>
                <td class="n num">{{ $g->count() }}</td>
                <td class="n num">{{ $qPcs }}</td>
                <td class="n num">{{ $qBags }}</td>
                <td>{{ $qTypes ?: '—' }}</td>
                <td><b>{{ $qShelf ?: '—' }}</b></td>
                <td class="n num">{{ \App\Services\TreasuryService::fmt($qVal) }}</td>
                <td class="n num" style="{{ $qRem > 0.009 ? 'color:var(--down);font-weight:800' : 'color:var(--up)' }}">{{ $qRem > 0.009 ? \App\Services\TreasuryService::fmt($qRem) : '✓ خالص' }}</td>
                <td style="white-space:nowrap"><a class="btn ghost sm" href="?q={{ urlencode($qCode) }}&courier=1">🛵 مندوب</a> <a class="btn green sm" href="?q={{ urlencode($qCode) }}">✅ تسليم</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
    @if ($queueMore)<span class="note">+{{ $queueMore }} عميل</span>@endif
</div>

@if ($q !== '' && !$customer)
    <div class="card"><div class="empty"><span class="ic">🔍</span>لم نجد عميل بـ "{{ $q }}"</div></div>
@elseif ($customer)
    @if ($matches->count() > 1)<div class="hint mb">فيه أكثر من عميل بالاسم هذا — اختار: @foreach ($matches as $m)<a class="chip {{ $m->id === $customer->id ? 'u' : 'n' }}" style="margin:2px;text-decoration:none" href="{{ request()->fullUrlWithQuery(['q' => $m->code ?: $m->phone_1]) }}">{{ $m->name }} · {{ $m->code }}</a>@endforeach</div>@endif
    <div class="card cust">
        <span class="av" style="width:58px;height:58px;font-size:24px">{{ mb_substr($customer->name, 0, 1) }}</span>
        <div><div style="font-size:21px;font-weight:800">{{ $customer->name }}@if ($customer->is_vip) <span class="chip w" style="font-size:12px;vertical-align:middle">⭐ VIP</span>@endif</div><div class="note" style="font-size:13.5px"><span class="code" style="color:var(--ink);background:var(--line-soft);padding:2px 9px;border-radius:7px">{{ $customer->code }}</span> <span class="num">{{ $customer->phone_1 }}</span> · {{ $custOrders }} طلبية</div></div>
        <div class="stats">
            <div><small>{{ $showAll ? 'ما تسلمتش' : 'جاهزة للاستلام' }}</small><b class="num plus">{{ $ready->count() }}</b></div>
            <div><small>في الطريق</small><b class="num">{{ $later->count() }}</b></div>
            <div><small>عليه الكل</small><b class="num minus">{{ $f($due) }}</b></div>
            @if ($customer->credit > 0)<div><small>رصيد لدينا</small><b class="num" style="color:var(--s1)">{{ $f($customer->credit) }}</b></div>@endif
        </div>
    </div>

    @if (!empty($missing))
        @php $mvAll = array_sum(array_column($missing, 'value')); $mqAll = array_sum(array_column($missing, 'qty')); @endphp
        <div class="warnbox mb" style="background:#fdecea;color:#b3261e;border:2px solid #f3b8b2;font-size:15px;font-weight:700">⚠️ انتبه: العميل عنده نواقص في {{ count($missing) }} إيصال · {{ $mqAll }} قطعة بقيمة <span class="num">{{ $f($mvAll) }}</span> د.ل
            <div style="font-weight:400;font-size:13px;margin-top:4px">اضغط «📉 إظهار النواقص» في الإيصال تشوف التفاصيل، وتقدر تخصمها من الإيصال وقت التسليم (اختياري)</div></div>
    @endif
    <form method="post" action="{{ route('sg.delivery.store') }}" id="df">@if (request('embed'))<input type="hidden" name="embed" value="1">@endif
    @csrf
    <input type="hidden" name="customer_id" value="{{ $customer->id }}">
    <div class="side-lay" style="grid-template-columns:1fr 390px">
        <div>
            @foreach ($withCourier as $dl)
                <div class="warnbox mb" style="background:var(--s1-bg);color:#1d4f8f">🚚 {{ $dl->code }} · {{ $dl->statusLabel() }}{{ $dl->courier ? ' مع ' . $dl->courier->name : '' }} · {{ $dl->orders->pluck('code')->implode('، ') }} · <a href="{{ route('sg.couriers', ['d' => $dl->id]) }}" style="text-decoration:underline">تفاصيل</a></div>
            @endforeach
            <div style="display:flex;justify-content:space-between;margin:2px 2px 10px"><h3 style="margin:0">{{ $showAll ? 'طلبياته اللي ما تسلمتش' : 'جاهزة للاستلام' }}</h3><span class="note">اختر الذي بيستلمه الآن</span></div>
            @forelse ($ready as $o)
                @php $rem = max(0, $o->remaining()); $have = $o->received_qty ?? $o->qty; $unit = $o->qty ? ($o->total_price / $o->qty) : 0; @endphp
                <label class="rec sel" data-raw="{{ round($o->remaining() - (float) $o->ship_price, 2) }}" data-rem="{{ $rem }}" data-unit="{{ round($unit, 2) }}" data-have="{{ $have }}" data-qty="{{ $o->qty }}">
                    <input type="checkbox" name="order_ids[]" value="{{ $o->id }}" class="pick" @checked(request('pick') == $o->id)>
                    <div class="r-top">
                        <span class="ckb">✓</span>
                        <div><div class="code" style="font-size:18px">{{ $o->code }} <a class="noTog" href="{{ route('order.invoice', $o->code) }}" target="_blank" style="font-family:Somar;font-size:12px;font-weight:400;color:var(--s1)">🧾 الفاتورة</a></div><div class="note" style="font-size:13px">رحلة {{ $o->trip_details }} · {{ optional($o->platform)->name }} · وصلت {{ $o->shelfDays() === 0 ? 'اليوم' : 'من ' . $o->shelfDays() . ' يوم' }}</div></div>
                        <div class="shelf2"><small>الرف</small><b>{{ $o->storage_num ?: '—' }}</b></div>
                    </div>
                    @if (!$o->arrived)<div style="margin:-2px 0 8px"><span class="chip w">⏳ مش جاهزة للاستلام بعد · {{ $o->stageLabel() }}</span> <span class="note">تقدر تسلمها عادي</span></div>@endif
                    <div class="r-mid">
                        <div><small>القطع</small><b class="num">{{ $have }}</b> <span class="note">من {{ $o->qty }}</span> @if ($have >= $o->qty)<span class="chip u">كاملة</span>@endif</div>
                        <div><small>الطرود</small><b class="num">{{ $o->bags_num ?: 1 }}</b></div>
                        <div><small>نوع الطرد</small><b>{{ $o->package_type ?: '—' }}</b></div>
                        <div><small>الوزن</small><b class="num">{{ $o->kilo ?: '—' }}</b> كيلو</div>
                    </div>
                    <div class="r-part noTog">
                        <span>سيستلم</span><input class="ci qty" name="rows[{{ $o->id }}][qty]" value="{{ $have }}" inputmode="numeric"><span class="note">قطعة من {{ $o->qty }}</span>
                        <span class="mdw" hidden>· خصم القطع الناقصة <input class="ci md" name="rows[{{ $o->id }}][missing_discount]" value="0" inputmode="decimal"> د.ل <span class="note sug"></span></span>
                    </div>
                    @if (isset($missing[$o->id]))
                    @php $ms = $missing[$o->id]; @endphp
                    <div class="r-part noTog" style="display:block;background:#fdecea;color:#b3261e;border-radius:12px;padding:8px 10px">
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><b>📉 عنده نواقص: {{ $ms['qty'] }} قطعة · <span class="num">{{ $f($ms['value']) }}</span> د.ل</b>
                            <button type="button" class="btn ghost sm" onclick="const b=this.closest('.r-part').querySelector('.msl');b.hidden=!b.hidden;this.textContent=b.hidden?'📉 إظهار النواقص':'إخفاء'">📉 إظهار النواقص</button>
                            @if ($ms['value'] > 0)<label class="ck" style="margin:0;color:#b3261e"><input type="checkbox" class="msd" name="miss_deduct[{{ $o->id }}]" value="1" data-v="{{ $ms['value'] }}"> اخصم النواقص من الإيصال (<span class="num">{{ $f($ms['value']) }}</span> د.ل)</label>
                            @else<span>سعرها مش متسجل — لو تبي تخصمها اكتب قيمتها: <input class="ci isd" name="miss_val[{{ $o->id }}]" value="0" inputmode="decimal"> د.ل</span>@endif</div>
                        <div class="msl" hidden style="margin-top:6px;font-size:13px;line-height:1.8">@foreach ($ms['rows'] as $mr)<div>• {{ $mr['t'] }} — {{ $mr['n'] }} قطعة · <span class="num">{{ $f($mr['v']) }}</span> د.ل</div>@endforeach</div>
                    </div>
                    @endif
                    @if (isset($issues[$o->id]))
                    <div class="r-part noTog" style="background:#fff4e5;color:#8a5a00;border-radius:12px;padding:8px 10px;display:block">
                        @foreach ($issues[$o->id] as $is)
                            <div style="margin:3px 0">⚠ <b>مشكلة: {{ $is->typeLabel() }}</b>{{ $is->qty ? ' · ' . $is->qty . ' قطعة' : '' }}{{ $is->note ? ' — ' . $is->note : '' }}
                                <br>تبي تخصمها؟ <input class="ci isd" name="issue_deduct[{{ $is->id }}]" value="0" inputmode="decimal"> د.ل @if ((float) $is->value > 0)<a href="#" class="noTog" style="color:#8a5a00;text-decoration:underline" onclick="event.preventDefault();const i=this.previousElementSibling;i.value='{{ (float) $is->value }}';i.dispatchEvent(new Event('input',{bubbles:true}))">اخصم {{ $f($is->value) }}</a>@endif <span class="note">(لو خصمتها تتقفل المشكلة)</span></div>
                        @endforeach
                    </div>
                    @endif
                    @if (\App\Services\ShippingService::isDual($o))
                    @php $dp = \App\Services\ShippingService::dualPrices($o); $pay = $o->ship_pay ?: 'cash'; @endphp
                    <div class="r-part noTog" style="display:block;background:#eef6ff;border-radius:12px;padding:8px 10px">
                        <div style="font-weight:700;margin-bottom:4px">🚚 سعر الشحن <span class="num">{{ $f($o->usd_amount) }}$</span> — كيف بيدفعه؟</div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <label class="ck" style="margin:0"><input type="radio" name="ship_pay[{{ $o->id }}]" value="cash" data-p="{{ $dp['cash'] }}" onchange="shipPick(this)" @checked($pay === 'cash')> 💵 كاش · <b class="num">{{ $f($dp['cash']) }}</b> د.ل</label>
                            <label class="ck" style="margin:0"><input type="radio" name="ship_pay[{{ $o->id }}]" value="bank" data-p="{{ $dp['bank'] }}" onchange="shipPick(this)" @checked($pay === 'bank')> 🏦 مصرف · <b class="num">{{ $f($dp['bank']) }}</b> د.ل</label>
                        </div>
                        <div class="note" style="font-size:12px;margin-top:3px">لو بيدفع دولار اختر السعر المتفق عليه من الاثنين</div>
                    </div>
                    @endif
                    <div class="r-money">
                        <span>الفاتورة <b class="num">{{ $f($o->total_price - $o->discount) }}</b></span><span class="note">+</span>
                        <span>الشحن <b class="num shipv">{{ $f($o->ship_price) }}</b></span><span class="note">−</span>
                        <span>المدفوع <b class="num plus">{{ $f($o->paid_price) }}</b></span>
                        <span class="rem2">الباقي <b class="num rr">{{ $f($rem) }}</b> د.ل</span>
                    </div>
                </label>
            @empty
                <div class="card"><div class="empty"><span class="ic">📦</span>{{ $showAll ? 'ما عنده حتى طلبية ما تسلمتش' : 'ليس لديه بضاعة جاهزة الآن' }}@if ($delivered->count())<br><small class="note">👇 هذي آخر طلبياته</small>@endif</div></div>
            @endforelse

            @if ($showAll)<div class="hint mb">تعرض كل الطلبيات حتى اللي مش جاهزة · <a href="{{ request()->fullUrlWithQuery(['all' => 0]) }}">رجوع للجاهزة بس</a></div>@endif
            @if ($delivered->count())
                <div style="display:flex;justify-content:space-between;margin:18px 2px 10px"><h3 style="margin:0">مستلمة</h3><span class="note">لو استلمتها بالغلط رجّعها</span></div>
                @foreach ($delivered as $o)
                    <div class="later" style="opacity:1"><span class="code">{{ $o->code }}</span><span class="note">استلم {{ optional($o->delivered_time)->format('Y-m-d H:i') ?: $o->delivered_at }} · {{ $o->delivery_type ?: '—' }}</span>
                        <span style="margin-inline-start:auto">@can('delivery-undo')@if ($o->delivered)<button type="button" class="btn ghost sm" style="color:var(--down)" onclick="openUndo({{ json_encode(['id' => $o->id, 'code' => $o->code, 'name' => optional($o->customer)->name, 'by' => optional($o->deliveredUser)->name, 'paid' => round((float) $o->paid_price, 2)]) }})">↩ إلغاء التسليم</button>@endif @endcan</span></div>
                @endforeach
            @endif

            @if ($later->count())
                <div style="display:flex;justify-content:space-between;margin:18px 2px 10px"><h3 style="margin:0">لم تصل بعد</h3><a class="btn ghost sm" href="{{ request()->fullUrlWithQuery(['all' => 1]) }}">📦 سلّمهم برضو</a></div>
                @foreach ($later as $o)
                    <div class="later"><span class="code">{{ $o->code }}</span><span class="note">رحلة {{ $o->trip_details ?: '—' }} · الباقي {{ $f($o->remaining()) }} (بدون الشحن)</span><span class="st {{ $o->stage() }}" style="margin-inline-start:auto"><i></i>{{ $o->stageLabel() }}</span></div>
                @endforeach
            @endif
        </div>

        @if ($ready->count())
        <div class="card dark pay">
            <h3>الحساب</h3>
            <div class="fld">
                <label class="t">تخفيض وقت التسليم</label>
                <div class="row2"><div class="in"><input name="discount" id="disc" value="{{ old('discount', 0) }}"><span>د.ل</span></div><input name="discount_reason" class="inp" style="height:48px" placeholder="السبب (اختياري)" value="{{ old('discount_reason') }}"></div>
                @if ($pointsOn && $customer->points > 0)
                    <div class="in" style="margin-top:6px"><input name="points" id="pts" value="0" data-val="{{ $pointValue }}" data-max="{{ $customer->points }}" data-min="{{ $pointsMin }}"><span>⭐ نقاط (لديه {{ $customer->points }} = {{ $f($customer->points * $pointValue) }} د.ل)</span></div>
                @endif
            </div>
            <div class="big"><small style="color:#c9cde0;font-size:13.5px" id="bigSub"></small><b><span class="num" id="dueTxt">0</span> <em style="font-style:normal;font-size:17px;font-weight:400;color:#c9cde0">د.ل</em></b></div>

            <div class="fld">
                <label class="t">التسليم</label>
                <div class="opts" style="margin-top:6px">
                    <label><input type="radio" name="delivery_type" value="توصيل شخصي" checked><span>يستلم بنفسه</span></label>
                    <label><input type="radio" name="delivery_type" value="توصيل عن طريق مندوب"><span>🚚 مندوب</span></label>
                    <label><input type="radio" name="delivery_type" value="توصيل عن طريق مكتب"><span>مكتب توصيل</span></label>
                </div>
                <input name="delivery_name" id="dname" class="inp" style="height:40px;margin-top:6px" placeholder="اسم المكتب (اختياري)" hidden>
            </div>

            <div id="payBox" class="fld">
                <label class="t" id="payT">دفع كيف؟ (يمكنك أن تقسمها)</label>
                <div class="row2">
                    <div class="in"><input name="cash" id="cash" value="0"><span>نقداً</span></div>
                    <div class="in"><input name="bank" id="bank" value="0"><span>مصرف</span></div>
                </div>
                @if ($banks->count())<select name="bank_id" class="inp">@foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>@endif
                @if ($customer->credit > 0)<div class="in" style="margin-top:6px"><input name="credit" id="credit" value="0"><span>من رصيده ({{ $f($customer->credit) }})</span></div>@endif
                <div style="display:flex;justify-content:space-between;font-size:13.5px;margin-top:10px"><span id="okTxt" style="color:#5fd3a3"></span><span style="color:#c9cde0">الباقي عليه بعد التسليم: <b class="num" id="leftTxt">0</b></span></div>
            </div>

            <div id="courierBox" class="fld" hidden>
                <label class="t">المندوب</label>
                @if ($couriers->count())
                    <select name="courier_id" id="courierSel" class="inp" style="height:42px;margin-top:6px">@foreach ($couriers as $c)<option value="{{ $c->id }}" data-office="{{ $c->isOffice() ? 1 : 0 }}">{{ $c->isOffice() ? '🏢 ' : '' }}{{ $c->name }}{{ $c->courier_zone ? ' · ' . $c->courier_zone : '' }}</option>@endforeach</select>
                @else
                    <div class="warnbox" style="margin-top:6px">لا يوجد مندوبين — <a href="{{ route('sg.couriers') }}" style="text-decoration:underline">أضف مندوب</a></div>
                @endif
                <div class="row2"><input name="recipient_name" class="inp" style="height:42px" placeholder="اسم المستلم" value="{{ $customer->name }}"><input name="recipient_phone" class="inp numr" style="height:42px" placeholder="رقم المستلم" value="{{ $customer->phone_1 }}"></div>
                <input name="address" class="inp" style="height:42px;margin-top:6px" placeholder="العنوان" value="{{ $customer->address }}">
                <input name="location_url" class="inp" style="height:42px;margin-top:6px;direction:ltr" placeholder="رابط الموقع من Google Maps" value="{{ $customer->location_url }}">
                <label class="ck" style="margin-top:8px;color:#fff"><input type="checkbox" id="allPaid"> ✅ خالص — دفع ثمن الطلبية كله توا (المندوب ما يقبضش ثمنها)</label>
                <div class="row2"><div class="in"><input name="fee" id="fee" value="{{ $courierFee }}"><span>أجرة التوصيل</span></div><select name="pay_type" class="inp" style="height:48px">@foreach (\App\Models\Delivery::PAY_TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="row2"><select name="fee_payer" id="feePayer" class="inp" style="height:42px" title="من يدفع أجرة التوصيل">@foreach (\App\Models\Delivery::FEE_PAYERS as $k => $v)<option value="{{ $k }}">أجرة التوصيل {{ $v }}</option>@endforeach</select><div class="note" id="feeNote" style="align-self:center;color:#c9cde0;font-size:12.5px">العميل يدفع الأجرة للمندوب مع المبلغ</div></div>
                <input name="courier_note" class="inp" style="height:42px;margin-top:6px" placeholder="ملاحظة للمندوب (مثلا: اتصل قبل بساعة)">
                <div style="font-size:13.5px;margin-top:10px;color:#c9cde0">المندوب يقبض: <b class="num" style="color:#fff;font-size:17px" id="toCollect">0</b> د.ل · المبلغ تبقى في رصيده حتى أُقفل معه</div>
            </div>

            <div class="fld">
                <input name="customer_note" class="inp" style="height:42px" placeholder="📝 ملاحظة للعميل (تُرسل واتساب وتظهر في حسابه)">
            </div>
            <label class="ck mt"><input type="checkbox" name="print" value="1" checked> <span id="printLbl">اطبع إيصال الاستلام</span></label><br>
            <label class="ck" style="margin-top:6px"><input type="checkbox" name="send_whatsapp" value="1" checked> أرسل واتساب للعميل</label>
            <button class="btn green lg block mt" id="goBtn">تسليم وقبض</button>
            <div class="note" style="text-align:center;margin-top:10px;color:#c9cde0" id="footNote">الكاش يدخل لدرجك · الطلبية تُسجَّل "تم التسليم"</div>
            <div style="margin-top:14px;color:#c9cde0;font-size:13.5px">الإيصالات</div>
            <div id="lines" style="max-height:340px;overflow:auto"></div>
        </div>
        @endif
    </div>
    </form>
@endif
@if (session('print_url'))<script>window.open(@json(session('print_url')), '_blank');</script>@endif
@include('sg.partials.undo-delivery')
@endsection
@push('script')
<script>
(function () {
  const q = document.getElementById('dq'), box = document.getElementById('dSug'); if (!q) return;
  q.form.style.position = 'relative';
  let t, ctl;
  q.addEventListener('input', () => {
    clearTimeout(t);
    const v = q.value.trim(); if (!v) { box.hidden = true; return; }
    t = setTimeout(async () => {
      try {
        if (ctl) ctl.abort(); ctl = new AbortController();
        const r = await (await fetch(@json(route('sg.delivery.suggest')) + '?q=' + encodeURIComponent(v), { signal: ctl.signal })).json();
        box.innerHTML = r.length ? r.map(c => `<a href="?q=${encodeURIComponent(c.code)}" style="display:flex;gap:10px;align-items:center;padding:10px 14px;border-bottom:1px solid var(--line)"><b class="num" style="min-width:60px">${c.code || ''}</b><span style="flex:1">${c.name || ''}</span><small class="note num">${c.phone || ''}</small>${c.ready ? `<span class="chip u">${c.ready} جاهز</span>` : ''}${c.open > c.ready ? `<span class="chip w">${c.open - c.ready} مش جاهزة</span>` : ''}${!c.open ? '<span class="note" style="font-size:11.5px">ما عنده شي</span>' : ''}</a>`).join('') : '<div class="note" style="padding:12px">لا يوجد عميل</div>';
        box.hidden = false;
      } catch (e) {}
    }, 250);
  });
  document.addEventListener('click', e => { if (!q.form.contains(e.target)) box.hidden = true; });
})();
</script>
<script>
// 💵/🏦 تغيير سعر الشحن (كاش/مصرف) على إيصال شحنة: يتحدث المستحق وبعدها يحسب calc
window.shipPick = function (inp) {
  const r = inp.closest('.rec'), v = parseFloat(inp.dataset.p) || 0;
  r.dataset.rem = Math.max(0, (parseFloat(r.dataset.raw) || 0) + v);
  const sv = r.querySelector('.shipv'); if (sv) sv.textContent = SG.fmt(v);
};
</script>
<script>
(function () {
  const recs = [...document.querySelectorAll('.rec[data-rem]')];
  if (!recs.length) return;
  const $ = id => document.getElementById(id);
  const cash = $('cash'), bank = $('bank'), credit = $('credit'), disc = $('disc'), pts = $('pts'), fee = $('fee');
  const num = el => el ? (parseFloat(el.value) || 0) : 0;
  // 🏢 «مكتب توصيل»: لو عندك مكاتب مسجلة تختار المكتب، والطلبية تتسلم له طول وتتسجل عليه
  const hasOffice = !!document.querySelector('#courierSel option[data-office="1"]');
  const isOfficeMode = () => document.querySelector('input[name=delivery_type]:checked').value.includes('مكتب') && hasOffice;
  const isCourier = () => document.querySelector('input[name=delivery_type]:checked').value.includes('مندوب') || isOfficeMode();
  const filterCouriers = () => { const s = $('courierSel'); if (!s) return; const off = isOfficeMode();
    [...s.options].forEach(o => o.hidden = (o.dataset.office === '1') !== off);
    if (s.selectedOptions[0] && s.selectedOptions[0].hidden) { const f = [...s.options].find(o => !o.hidden); if (f) s.value = f.value; } };
  let lastDue = -1;
  function rowDue(r) {
    const have = +r.dataset.have, q = Math.min(have, parseInt(r.querySelector('.qty').value || have, 10));
    const short = q < +r.dataset.qty;
    const w = r.querySelector('.mdw'); w.hidden = !short;
    if (short) r.querySelector('.sug').textContent = '(المقترح ' + SG.fmt((+r.dataset.qty - q) * +r.dataset.unit) + ')';
    const md = short ? num(r.querySelector('.md')) : 0;
    const isd = [...r.querySelectorAll('.isd')].reduce((a, i) => a + (parseFloat(i.value) || 0), 0) // ⚠ خصم المشكلة
      + [...r.querySelectorAll('.msd:checked')].reduce((a, i) => a + (parseFloat(i.dataset.v) || 0), 0); // 📉 خصم النواقص
    const d = Math.max(0, +r.dataset.rem - md - isd);
    r.querySelector('.rr').textContent = SG.fmt(d);
    return d;
  }
  function calc(autofill) {
    recs.forEach(r => r.classList.toggle('sel', r.querySelector('.pick').checked));
    const sel = recs.filter(r => r.querySelector('.pick').checked);
    let d = sel.reduce((a, r) => a + rowDue(r), 0);
    const ptsVal = pts ? Math.min(num(pts), +pts.dataset.max) * +pts.dataset.val : 0;
    d = Math.max(0, d - num(disc) - (pts && num(pts) >= +pts.dataset.min ? ptsVal : 0));
    $('lines').innerHTML = sel.map(r => `<div class="ln"><span>${r.querySelector('.code').textContent}</span><b class="num">${r.querySelector('.rr').textContent}</b></div>`).join('')
      + (num(disc) ? `<div class="ln"><span>تخفيض</span><b class="num">−${SG.fmt(num(disc))}</b></div>` : '')
      + (ptsVal && num(pts) >= +pts.dataset.min ? `<div class="ln"><span>⭐ نقاط</span><b class="num">−${SG.fmt(ptsVal)}</b></div>` : '');
    $('bigSub').textContent = sel.length ? 'المطلوب الآن · ' + sel.length + ' إيصال' : '👆 اختار الإيصال اللي بيستلمه (اضغط عليه)';
    const gb = $('goBtn'); if (gb) gb.disabled = !sel.length;
    $('dueTxt').textContent = SG.fmt(d);
    const c = isCourier();
    filterCouriers(); $('payBox').hidden = false; $('courierBox').hidden = !c;
    $('payT').textContent = c ? '💵 دفع توا قبل ما يطلع مع المندوب (اختياري)' : 'دفع كيف؟ (يمكنك أن تقسمها)'; $('dname').hidden = !document.querySelector('input[name=delivery_type]:checked').value.includes('مكتب') || hasOffice;
    $('printLbl').textContent = c ? 'اطبع ورقة المندوب (بيانات المستلم)' : 'اطبع إيصال الاستلام';
    $('footNote').textContent = c ? 'الإيصالات تظهر من الرف "مع المندوب" · يصلك تنبيه أول ما يسلّم' : 'الكاش يدخل لدرجك · الطلبية تُسجَّل "تم التسليم"';
    if (c) {
      const co = $('feePayer') && $('feePayer').value === 'company';
      if (autofill || Math.abs(d - lastDue) > 0.001) { cash.value = $('allPaid') && $('allPaid').checked ? Math.max(0, Math.round((d - num(bank) - num(credit)) * 100) / 100) : 0; }
      const paidNow = num(cash) + num(bank) + num(credit), rest = Math.max(0, d - paidNow);
      $('leftTxt').textContent = SG.fmt(rest);
      $('okTxt').textContent = paidNow > d + 0.01 ? '⚠ أكثر من المطلوب' : (paidNow > 0.009 ? (rest <= 0.01 ? '✓ خالص' : 'دفع جزء توا') : 'المندوب يقبض كل شي');
      $('okTxt').style.color = paidNow > d + 0.01 ? '#ff9b94' : '#5fd3a3';
      $('toCollect').textContent = SG.fmt(rest + (co ? 0 : num(fee)));
      if ($('feeNote')) $('feeNote').textContent = co ? 'الأجرة علينا: تُخصم من حساب المندوب/المكتب ولا تُطلب من العميل' : 'العميل يدفع الأجرة للمندوب مع المبلغ';
      $('goBtn').textContent = isOfficeMode() ? '🏢 سلّم للمكتب (يتسجل عليه)' : 'سلّم للمندوب';
      $('goBtn').disabled = !sel.length || !document.querySelector('select[name=courier_id]') || paidNow > d + 0.01;
      lastDue = d; return;
    }
    if (autofill || Math.abs(d - lastDue) > 0.001) { cash.value = Math.max(0, Math.round((d - num(bank) - num(credit)) * 100) / 100); }
    lastDue = d;
    const paid = num(cash) + num(bank) + num(credit), left = d - paid;
    $('leftTxt').textContent = SG.fmt(Math.max(0, left));
    $('okTxt').textContent = left < -0.01 ? '⚠ أكثر من المطلوب' : (left <= 0.01 ? '✓ المبلغ كامل' : 'دفع جزء');
    $('okTxt').style.color = left < -0.01 ? '#ff9b94' : '#5fd3a3';
    $('goBtn').textContent = 'تسليم وقبض ' + SG.fmt(paid) + ' د.ل';
    $('goBtn').disabled = !sel.length || left < -0.01;
  }
  recs.forEach(r => {
    r.addEventListener('click', e => { if (e.target.closest('.noTog')) return; e.preventDefault(); const i = r.querySelector('.pick'); i.checked = !i.checked; calc(true); });
    r.querySelectorAll('.noTog input').forEach(i => { i.addEventListener('input', () => calc(false)); i.addEventListener('change', () => calc(false)); });
  });
  [credit, disc, pts, fee].filter(Boolean).forEach(i => i.addEventListener('input', () => calc(false)));
  if ($('feePayer')) $('feePayer').addEventListener('change', () => calc(false));
  cash.addEventListener('input', () => calc(false));
  bank.addEventListener('input', () => { if (!isCourier() || ($('allPaid') && $('allPaid').checked)) cash.value = Math.max(0, Math.round((lastDue - num(bank) - num(credit)) * 100) / 100); calc(false); });
  document.querySelectorAll('input[name=delivery_type]').forEach(i => i.addEventListener('change', () => calc(true)));
  if ($('allPaid')) $('allPaid').addEventListener('change', () => calc(true));
  // 🛵 جا من زر «مندوب» في القائمة: نختارو التوصيل بالمندوب طول
  if (new URLSearchParams(location.search).get('courier') === '1') { const cr = document.querySelector('input[name=delivery_type][value*="مندوب"]'); if (cr) { cr.checked = true; cr.dispatchEvent(new Event('change')); } }
  calc(true);
})();
</script>
@endpush
