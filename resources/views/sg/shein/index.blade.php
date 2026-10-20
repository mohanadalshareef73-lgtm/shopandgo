@extends('sg.layout')
@section('title', 'بطاقات SHEIN')
@php
    use App\Services\TreasuryService as TS; use App\Models\SheinCard;
    $f = fn ($n) => TS::fmt($n); $f2 = fn ($n) => TS::fmt($n, 2);
    $tabs = ['active' => 'جديدة', 'partial' => 'مستعملة جزئياً', 'sold' => 'مباعة لعملاء', 'used' => 'استُخدمت', 'lost' => 'انتهت / خطأ', 'all' => 'الكل'];
    $canPin = auth()->user()->can('shein-card-pin');
@endphp
@push('style')
<style>
.gc { display: inline-flex; align-items: center; gap: 10px; }
.gc .ic { width: 46px; height: 30px; border-radius: 6px; background: linear-gradient(135deg, #111 0%, #3a3a3a 100%); color: #fff; font-family: InterNum; font-weight: 800; font-size: 10px; display: grid; place-items: center; letter-spacing: .5px; flex: none; }
.gc .ic.used { background: #c9cdd8; } .gc .ic.lost { background: linear-gradient(135deg, #c4352f, #8d231e); }
.bal { min-width: 120px; }
.bal .b { height: 6px; background: var(--line-soft); border-radius: 3px; overflow: hidden; margin-top: 4px; }
.bal .b i { display: block; height: 100%; background: #111; border-radius: 3px; }
.big-card { background: linear-gradient(135deg, #111 0%, #2b2b2b 60%, #3d3d3d 100%); color: #fff; border-radius: 18px; padding: 18px 20px; position: relative; overflow: hidden; }
.big-card::after { content: "SHEIN"; position: absolute; left: 16px; top: 12px; font-family: InterNum; font-weight: 900; letter-spacing: 3px; opacity: .9; }
.big-card .no { font-family: InterNum; font-size: 20px; letter-spacing: 2px; margin-top: 26px; direction: ltr; text-align: right; }
.big-card .row { display: flex; justify-content: space-between; margin-top: 14px; font-size: 13px; color: #c9cdd8; }
.big-card .row b { display: block; color: #fff; font-size: 20px; }
.cm { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.uses .it { display: flex; gap: 10px; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--line-soft); font-size: 13.5px; }
.pick { display: flex; gap: 8px; align-items: center; padding: 8px 10px; border: 1px solid var(--line); border-radius: 12px; margin-bottom: 6px; }
.pick input.amt { width: 90px; height: 36px; }
.save-chip { background: var(--up-bg); color: var(--up); border-radius: 10px; padding: 8px 10px; font-size: 13px; margin-top: 8px; }
</style>
@endpush
@push('style')
<style>
.cr-h { display: grid; grid-template-columns: 1fr 92px 30px; gap: 8px; font-size: 12.5px; font-weight: 700; color: var(--ink-2); margin-bottom: 6px; }
.cr-h small { color: var(--ink-3); font-weight: 400; }
.cr { display: grid; grid-template-columns: 1fr 92px 30px; gap: 8px; margin-bottom: 8px; align-items: start; }
.cr input { height: 48px; border: 1.5px solid var(--line); border-radius: 12px; padding: 0 10px; font-family: InterNum; font-size: 15.5px; font-weight: 700; letter-spacing: .5px; min-width: 0; direction: ltr; text-align: left; width: 100%; background: #fff; color: var(--ink); }
.cr input.pin { text-align: center; letter-spacing: 6px; }
.cr input:focus { outline: 0; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(38,44,74,.08); }
.cr input.bad { border-color: var(--down); background: #fff8f7; }
.cr input.ok { border-color: #9fd8bf; }
.cr .x { width: 30px; height: 48px; border: 0; background: none; color: var(--ink-3); font-size: 18px; cursor: pointer; }
.cr .msg { grid-column: 1 / -1; font-size: 12.5px; margin-top: -4px; }
.cr .msg.bad { color: var(--down); font-weight: 700; } .cr .msg.ok { color: var(--up); }
.cr .cnt { font-family: InterNum; font-size: 11px; color: var(--ink-3); }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>بطاقات SHEIN</h1><div class="sub">البطاقات التي شريناها: رصيدها، وأين استعملناها في الرحلات والإيصالات</div></div>
    <div class="acts">@include('sg.partials.period')<a class="btn ghost" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">⬇ Excel</a>@can('shein-card-sell')<a class="btn green" href="{{ route('sg.cards.sell') }}">🛍 بيع بطاقة لعميل</a>@endcan @can('shein-card-use')<button class="btn" data-modal="mUse">💳 استخدام على رحلة</button>@endcan<button class="sg-burger">☰</button></div>
</div>

<div class="kpis g5">
    <div class="card kpi accent"><div class="lbl">رصيد البطاقات الشغالة</div><div class="v"><span class="num">{{ $f2($kpi['balance']) }}</span> <small>$</small></div><div class="s">{{ $kpi['count'] }} بطاقة@can('profit-view') · كلفتنا {{ $f($kpi['balanceDinar']) }} د.ل@endcan</div></div>
    @can('profit-view')
    <div class="card kpi"><div class="lbl">الدولار في البطاقات كلفنا</div><div class="v"><span class="num">{{ $kpi['avgRate'] ? number_format($kpi['avgRate'], 3) : '—' }}</span> <small>د.ل</small></div><div class="s">سعر USDT {{ $kpi['marketRate'] ? number_format($kpi['marketRate'], 2) : '—' }}@if ($kpi['saving'] > 0) · <span class="chip u">وفرنا {{ $f($kpi['saving']) }}</span>@endif</div></div>
    @endcan
    <div class="card kpi"><div class="lbl">شرينا {{ $period->label }}</div><div class="v"><span class="num">{{ $f($kpi['bought']) }}</span> <small>$</small></div><div class="s">{{ $kpi['boughtN'] }} بطاقة · {{ $f($kpi['boughtCost']) }} د.ل</div></div>
    <div class="card kpi green"><div class="lbl">استعملنا {{ $period->label }}</div><div class="v"><span class="num">{{ $f($kpi['used']) }}</span> <small>$</small></div><div class="s">{{ $kpi['usedN'] }} مرة على رحلات وإيصالات</div></div>
    <div class="card kpi {{ $kpi['expiring'] || $kpi['lost'] ? 'red' : '' }}"><div class="lbl">تنتهي خلال 30 يوم</div><div class="v num">{{ $kpi['expiring'] }}</div><div class="s">خسارة {{ $period->label }}: {{ $f($kpi['lost']) }} د.ل</div></div>
</div>

@if ($sel)
<div class="side-lay mb" style="grid-template-columns:360px 1fr">
    <div>
        <div class="big-card">
            <div class="no" id="cardNo">{{ $sel->masked() }} <button type="button" class="cpy" data-copy="{{ $sel->card_no }}" title="نسخ رقم البطاقة (بدون الرمز)">⧉ نسخ الرقم</button></div>
            <div class="row"><span>الرصيد<b class="num">{{ $f2($sel->balance) }} $</b></span><span>من<b class="num">{{ $f2($sel->face_value) }} $</b></span><span>الحالة<b style="font-size:15px">{{ $sel->statusLabel() }}</b></span></div>
        </div>
        @if ($canPin)<button class="btn ghost block mt" id="pinBtn" data-url="{{ route('sg.shein.pin', $sel) }}">👁 اكشف الكود والرقم السري</button><div class="hint mt" id="pinBox" hidden></div>@endif
        <div class="card mt">
            @can('profit-view')
            <div class="ln"><span>كلفتنا</span><b class="num">{{ $f2($sel->cost) }} {{ TS::sign($sel->cost_currency) }} = {{ $f($sel->cost_dinar) }} د.ل</b></div>
            @endcan
            @can('profit-view')
            <div class="ln"><span>الدولار كلفنا</span><b class="num">{{ number_format($sel->costPerDollar(), 3) }} د.ل</b></div>
            @endcan
            @can('profit-view')
            <div class="ln"><span>الرصيد الباقي يسوى</span><b class="num">{{ $f($sel->balanceDinar()) }} د.ل</b></div>
            @endcan
            <div class="ln"><span>شريناها</span><b>{{ $sel->purchased_at->format('Y-m-d') }} · {{ optional($sel->payment)->name }}</b></div>
            <div class="ln"><span>سجّلها</span><b>{{ optional($sel->createdUser)->name }}</b></div>
            @if ($sel->sold_order_id)
                @php $cuS = \App\Services\CardSaleService::costUsd($sel, (float) $sel->sale_rate ?: null); @endphp
                <div class="hint" style="margin:8px 0">🛍 <b>بيعت</b> للعميل <a href="{{ route('sg.clients', ['id' => $sel->sold_customer_id]) }}" style="text-decoration:underline">{{ optional($sel->soldCustomer)->name }}</a>
                    في الإيصال <a class="code" href="{{ route('sg.orders.show', $sel->sold_order_id) }}">{{ optional($sel->soldOrder)->code }}</a> · {{ optional($sel->sold_at)->format('Y-m-d g:i a') }} · بواسطة {{ optional($sel->soldUser)->name }}
                    <div>سعر البيع <b class="num">{{ $f($sel->sale_price) }} د.ل</b>{{ $sel->sale_usd ? ' ≈ ' . $f($sel->sale_usd) . ' $' : '' }}@can('profit-view') · الربح <b class="num {{ $sel->sale_price >= $sel->cost_dinar ? 'plus' : 'minus' }}">{{ $f($sel->sale_price - $sel->cost_dinar) }} د.ل</b>{{ $sel->sale_usd && $cuS !== null ? ' ≈ ' . $f($sel->sale_usd - $cuS) . ' $' : '' }}@endcan</div>
                    <div class="note">{{ $sel->sent_at ? '📨 أُرسلت للعميل ' . $sel->sent_at->diffForHumans() : 'لم تُرسل للعميل عبر واتساب' }}</div></div>
            @endif
            @if ($sel->batch)<div class="ln"><span>الدفعة</span><a class="code" href="{{ route('sg.shein', ['tab' => 'all', 'q' => $sel->batch]) }}">{{ $sel->batch }}</a></div>@endif
            @if ($sel->lost_dinar > 0)<div class="ln"><span class="minus">خسارة</span><b class="num minus">{{ $f($sel->lost_dinar) }} د.ل</b></div>@endif
        </div>
    </div>
    <div>
        <div class="grid g2">
            @can('shein-card-create')
            <form class="card" method="post" action="{{ route('sg.shein.update', $sel) }}">
                @csrf @method('PUT')
                <h3>بيانات البطاقة <a href="{{ route('sg.shein', ['tab' => $tab]) }}" class="note">✕ إغلاق</a></h3>
                <div class="grid g2 f"><div><label class="l">المورد</label><input name="supplier" class="inp" value="{{ $sel->supplier }}" list="sups"></div><div><label class="l">تنتهي</label><input type="date" name="expires_at" class="inp" value="{{ optional($sel->expires_at)->toDateString() }}"></div></div>
                <div class="f"><label class="l">حساب SHEIN الذي انشحنت فيه</label><input name="account" class="inp" style="direction:ltr" value="{{ $sel->account }}" placeholder="email@…"></div>
                @if ($canPin)<div class="f"><label class="l">تغيير الرقم السري</label><input name="pin" class="inp" placeholder="اتركه فارغاً لعدم التغيير"></div>@endif
                <div class="f"><label class="l">ملاحظة</label><input name="note" class="inp" value="{{ $sel->note }}"></div>
                <button class="btn">حفظ</button>
            </form>
            @endcan
            <div class="card">
                <h3>استخدام البطاقة</h3>
                @if ($sel->isUsable())
                    @can('shein-card-use')
                    <form method="post" action="{{ route('sg.shein.use') }}">
                        @csrf
                        <div class="f"><label class="l">كم دولار؟ (الرصيد {{ $f2($sel->balance) }})</label><input name="amounts[{{ $sel->id }}]" class="inp big" value="{{ $sel->balance }}"></div>
                        <div class="f"><label class="l">على رحلة</label><select name="trip_purchase_id" class="inp"><option value="">— بدون رحلة —</option>@foreach ($trips as $t)<option value="{{ $t->id }}">{{ $t->trip_code }} · {{ $t->purchased_at->format('j/n') }} · باقي {{ $f2($t->cost - $t->cards_amount) }} $</option>@endforeach</select></div>
                        <div class="f"><label class="l">أو على إيصال</label><input name="order_code" class="inp numr" placeholder="SG-20412"></div>
                        <input name="note" class="inp mb" placeholder="ملاحظة">
                        <button class="btn green block">استخدام</button>
                    </form>
                    @endcan
                @else
                    <div class="hint">البطاقة {{ $sel->statusLabel() }}</div>
                @endif
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:12px;padding-top:12px;border-top:1px dashed var(--line)">
                    @can('shein-card-use')@if ($sel->isUsable())
                        <form method="post" action="{{ route('sg.shein.status', $sel) }}" data-confirm="البطاقة انتهت؟ الرصيد الباقي ({{ $f2($sel->balance) }} $) يُحسب خسارة">@csrf<input type="hidden" name="status" value="expired"><button class="btn ghost sm">⌛ انتهت</button></form>
                        <form method="post" action="{{ route('sg.shein.status', $sel) }}" data-confirm="البطاقة طلعت خطأ / مرفوضة؟ الرصيد يُحسب خسارة">@csrf<input type="hidden" name="status" value="invalid"><button class="btn ghost sm">⛔ طلعت خطأ</button></form>
                    @endif @endcan
                    @can('shein-card-delete')@if (!$sel->uses->count())<form method="post" action="{{ route('sg.shein.destroy', $sel) }}" data-confirm="حذف البطاقة وإرجاع المبلغ للخزينة؟">@csrf @method('DELETE')<button class="btn red sm">حذف</button></form>@endif @endcan
                </div>
            </div>
        </div>
        <div class="card mt uses">
            <h3>أين استعملناها <span class="h-note">{{ $sel->uses->count() }} مرة</span></h3>
            @forelse ($sel->uses as $u)
                <div class="it">
                    <b class="num" style="width:80px">{{ $f2($u->amount) }} $</b>
                    <div style="flex:1">@if ($u->trip)رحلة <a class="code" href="{{ route('finance.trips.show', $u->trip->id) }}">{{ $u->trip->trip_code }}</a>@endif @if ($u->order) إيصال <a class="code" href="{{ route('sg.orders.show', $u->order) }}">{{ $u->order->code }}</a> <span class="note">{{ optional($u->order->customer)->name }}</span>@endif<div class="note">{{ $u->created_at->format('Y-m-d g:i') }} · {{ optional($u->createdUser)->name }} · تكلفة {{ $f($u->cost_dinar) }} د.ل {{ $u->note ? '· ' . $u->note : '' }}</div></div>
                    @can('shein-card-use')<form method="post" action="{{ route('sg.shein.reverse', $u) }}" data-confirm="إرجاع {{ $f2($u->amount) }} $ للبطاقة؟{{ $u->trip ? ' (الرحلة ترجع تُخصم من الخزينة)' : '' }}">@csrf @method('DELETE')<button class="btn ghost sm">↩ رجوع</button></form>@endcan
                </div>
            @empty
                <div class="note">غير المستخدمة لا يزال</div>
            @endforelse
        </div>
    </div>
</div>
@endif

<div class="side-lay">
    <div class="card tw">
        <form class="tw-h" method="get">
            <input type="hidden" name="tab" value="{{ $tab }}"><input type="hidden" name="p" value="{{ $period->preset }}">
            @foreach ($tabs as $k => $v)<a class="pill {{ $tab === $k ? 'on' : '' }}" href="{{ route('sg.shein', ['tab' => $k]) }}">{{ $v }} <span class="num">{{ $counts[$k] }}</span></a>@endforeach
            <div class="sx"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input name="q" value="{{ request('q') }}" placeholder="{{ request('by') === 'pin' ? 'اكتب الرمز…' : (request('by') === 'card' ? 'رقم البطاقة أو جزء منه…' : 'رقم البطاقة، المورد، الحساب…') }}"></div>
            <select name="by" class="inp" style="height:38px;width:auto" onchange="this.form.submit()"><option value="">بحث في الكل</option><option value="card" @selected(request('by') === 'card')>برقم البطاقة</option>@can('shein-card-pin')<option value="pin" @selected(request('by') === 'pin')>بالرمز</option>@endcan</select>
            <select name="sort" class="pill" data-autosubmit>@foreach (['smart' => 'الجديدة أول · الأقل لوطى', 'old' => 'الأقدم أول', 'new' => 'الأحدث', 'balance' => 'الأكبر رصيد', 'expiry' => 'الأقرب انتهاء'] as $k => $v)<option value="{{ $k }}" @selected(request('sort', 'smart') === $k)>{{ $v }}</option>@endforeach</select>
        </form>
        <div class="scroll-x">
        <table class="t"><thead><tr><th>البطاقة</th><th>الرصيد</th>@can('profit-view')<th class="n">كلفتنا</th><th class="n">الدولار بـ</th>@endcan<th>المورد</th><th>شريناها</th><th>تنتهي</th><th>الحالة</th></tr></thead><tbody>
        @forelse ($cards as $c)
            @php $pc = $c->face_value > 0 ? $c->balance / $c->face_value * 100 : 0; $soon = $c->expires_at && $c->isUsable() && $c->expires_at->lte(now()->addDays(30)); @endphp
            <tr class="{{ $sel && $sel->id === $c->id ? 'on' : '' }}" style="cursor:pointer;{{ $c->sold_order_id ? 'background:#fdecea;color:#b3261e' : '' }}" onclick="location.href='{{ request()->fullUrlWithQuery(['id' => $c->id]) }}'">
                <td><button type="button" class="cpy sm" onclick="event.stopPropagation();SG.copy('{{ $c->card_no }}',this)" title="نسخ رقم البطاقة (بدون الرمز)">⧉</button><span class="gc"><span class="ic {{ $c->status === 'used' ? 'used' : (in_array($c->status, ['expired', 'invalid']) ? 'lost' : '') }}">SHEIN</span><span class="cell2"><b class="num">{{ $c->masked() }}</b><small>{{ $c->account ?: ($c->batch ? 'دفعة ' . $c->batch : '') }}</small></span></span></td>
                <td class="bal"><b class="num">{{ $f2($c->balance) }}</b> <span class="note">/ {{ $f($c->face_value) }} $</span><div class="b"><i style="width:{{ $pc }}%"></i></div></td>
                @can('profit-view')<td class="n num">{{ $f($c->cost_dinar) }} <span class="note">د.ل</span></td>
                <td class="n num">{{ number_format($c->costPerDollar(), 2) }}</td>@endcan
                <td class="note">{{ $c->supplier ?: '—' }}</td>
                <td class="note num">{{ $c->purchased_at->format('Y-m-d') }}</td>
                <td>@if ($c->expires_at)<span class="{{ $soon ? 'chip w' : 'note num' }}">{{ $c->expires_at->format('Y-m-d') }}</span>@else<span class="note">—</span>@endif</td>
                <td>@if ($c->sold_order_id)<span class="chip d" style="background:#e03131;color:#fff">🔴 مبيوعة</span> @endif<span class="chip {{ $c->statusChip() }}">{{ $c->statusLabel() }}</span> @if ($c->uses_count)<span class="note">{{ $c->uses_count }}×</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty"><span class="ic">💳</span>لا يوجد بطاقات هنا — سجّل أول بطاقة من اليسار</div></td></tr>
        @endforelse
        </tbody></table>
        </div>
        <div class="tw-f"><span>المعروض <b class="num">{{ $cards->count() }}</b> من <b class="num">{{ $cards->total() }}</b></span>@include('sg.partials.pager', ['p' => $cards])</div>

        @if ($recentUses->count())
        <div class="tw-h" style="border-top:1px solid var(--line-soft)"><h3 style="font-size:15px">آخر الاستخدامات</h3></div>
        <table class="t"><tbody>
            @foreach ($recentUses as $u)
                <tr><td class="note num">{{ $u->created_at->format('j/n g:i') }}</td><td><a class="num" href="?id={{ $u->card_id }}">{{ optional($u->card)->masked() }}</a></td><td>@if ($u->trip)رحلة <a class="code" href="{{ route('finance.trips.show', $u->trip->id) }}">{{ $u->trip->trip_code }}</a>@endif @if ($u->order)<a class="code" href="{{ route('sg.orders.show', $u->order) }}">{{ $u->order->code }}</a>@endif</td><td class="note">{{ optional($u->createdUser)->name }}</td><td class="n"><b class="num">{{ $f2($u->amount) }} $</b></td></tr>
            @endforeach
        </tbody></table>
        @endif
    </div>

    @can('shein-card-create')
    <form class="card sticky" method="post" action="{{ route('sg.shein.store') }}" id="buyF">
        @csrf
        <h3>تسجيل بطاقات جديدة</h3>
        @php $cd = (int) \App\Services\AppSettings::get('shein_card_digits', 16); $pd = (int) \App\Services\AppSettings::get('shein_pin_digits', 4); @endphp
        <div class="f">
            <div class="cr-h"><span>رقم البطاقة <small class="num">({{ $cd }} رقم)</small></span><span>الرمز <small class="num">({{ $pd }})</small></span></div>
            <div id="cardRows" data-cd="{{ $cd }}" data-pd="{{ $pd }}" data-check="{{ route('sg.shein.check') }}"></div>
            <div style="display:flex;gap:8px;margin-top:8px"><button type="button" class="btn ghost sm" id="addRow">+ بطاقة ثانية</button><button type="button" class="btn ghost sm" id="pasteBtn">📋 لصق مجموعة</button></div>
            <textarea name="codes" id="codes" hidden>{{ old('codes') }}</textarea>
            <div class="note" id="cnt">0 بطاقة</div>
        </div>
        <div class="grid g2 f">
            <div><label class="l">قيمة البطاقة ($)</label><input name="face_value" id="face" class="inp numr" value="{{ old('face_value', 100) }}" required></div>
            <div><label class="l">المورد</label><input name="supplier" class="inp" value="{{ old('supplier') }}" list="sups" placeholder="من أين؟"></div>
        </div>
        <div class="f"><label class="l">كم دفعت</label>
            <div class="cm"><input name="cost" id="cost" class="inp numr" value="{{ old('cost') }}" required placeholder="0"><select name="cost_currency" id="ccur" class="inp"><option value="dinar">دينار</option><option value="dollar">دولار (USDT)</option></select></div>
            <div class="opts mt"><label><input type="radio" name="cost_mode" value="each" checked><span>للبطاقة الوحدة</span></label><label><input type="radio" name="cost_mode" value="total"><span>للكل</span></label></div>
        </div>
        <div class="f" id="rateBox" hidden><label class="l">سعر الدولار (لحسابها بالدينار)</label><input name="rate" id="rate" class="inp numr" value="{{ number_format($kpi['marketRate'], 3, '.', '') }}"></div>
        <div class="f"><label class="l">دفعت من خزينة</label><select name="payment_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == $cashId)>{{ $pm->name }} · {{ $f($balances[$pm->id]['dinar'] ?? 0) }} د.ل / {{ $f($balances[$pm->id]['dollar'] ?? 0) }} $</option>@endforeach<option value="">لم يخرج من الخزينة</option></select></div>
        <div class="grid g2 f"><div><label class="l">تاريخ الشراء</label><input type="date" name="purchased_at" class="inp" value="{{ now()->toDateString() }}"></div><div><label class="l">تنتهي (اختياري)</label><input type="date" name="expires_at" class="inp"></div></div>
        <div class="f"><input name="account" class="inp" style="direction:ltr" placeholder="حساب SHEIN (اختياري)"></div>
        <div class="hint" id="calc">—</div>
        <button class="btn block lg mt" id="buyBtn">حفظ البطاقات</button>
        <div class="note mt" style="text-align:center">المبلغ يُصرف من الخزينة، والبطاقة تُحسب في تكلفة الرحلة بسعرها الحقيقي لما تستخدمها</div>
    </form>
    @endcan
</div>
<datalist id="sups">@foreach ($suppliers as $s)<option value="{{ $s }}">@endforeach</datalist>

@can('shein-card-use')
<div class="modal-bg" id="mUse"><div class="modal" style="max-width:560px">
    <h3>استخدام بطاقات على رحلة</h3>
    <form method="post" action="{{ route('sg.shein.use') }}">
        @csrf
        <div class="f"><label class="l">الرحلة</label><select name="trip_purchase_id" id="uTrip" class="inp"><option value="">— اختر —</option>@foreach ($trips as $t)<option value="{{ $t->id }}" data-left="{{ round($t->cost - $t->cards_amount, 2) }}">{{ $t->trip_code }} · {{ $t->purchased_at->format('j/n') }} · تكلفة {{ $f2($t->cost) }} $ · باقي {{ $f2($t->cost - $t->cards_amount) }} $</option>@endforeach</select></div>
        <div class="f"><label class="l">أو إيصال</label><input name="order_code" class="inp numr" placeholder="SG-20412"></div>
        <label class="l">البطاقات <span class="note">(الأقدم أول) — اكتب كم من كل وحدة</span></label>
        <div style="max-height:260px;overflow:auto">
        @forelse ($usable as $c)
            <div class="pick"><span class="gc"><span class="ic">SHEIN</span></span><div style="flex:1"><b class="num">{{ $c->masked() }}</b><div class="note">رصيد {{ $f2($c->balance) }} $ · الدولار بـ {{ number_format($c->costPerDollar(), 2) }}</div></div><input name="amounts[{{ $c->id }}]" class="inp numr amt" data-bal="{{ $c->balance }}" value="0"></div>
        @empty
            <div class="hint">لا يوجد بطاقات فيها رصيد</div>
        @endforelse
        </div>
        <div class="hint mt" id="uSum">المجموع 0 $</div>
        <input name="note" class="inp mt" placeholder="ملاحظة">
        <div style="display:flex;gap:8px;margin-top:12px"><button class="btn green">استخدام</button><button type="button" class="btn ghost" id="uAuto">املأ أوتوماتيك</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
@endcan
@endsection
@push('script')
<script>
(function () {
  const $ = id => document.getElementById(id);
  // فورم الشراء: كل بطاقة مربع رقم + مربع رمز، والأرقام محدودة
  if ($('buyF')) {
    const rows = $('cardRows'), CD = +rows.dataset.cd, PD = +rows.dataset.pd, checkUrl = rows.dataset.check;
    const digits = v => v.replace(/\D/g, '');
    let timers = new WeakMap();
    const lines = () => [...rows.querySelectorAll('.cr')].map(r => [r.querySelector('.no').value, r.querySelector('.pin').value]).filter(x => x[0]);
    const validate = () => {
      const seen = {}; let okAll = true, n = 0;
      rows.querySelectorAll('.cr').forEach(r => {
        const no = r.querySelector('.no'), pin = r.querySelector('.pin'), msg = r.querySelector('.msg');
        if (!no.value && !pin.value) { no.classList.remove('bad', 'ok'); pin.classList.remove('bad'); msg.textContent = ''; return; }
        n++;
        let err = '';
        if (no.value.length !== CD) err = 'رقم البطاقة يجب ' + CD + ' رقم (كاتب ' + no.value.length + ')';
        else if (seen[no.value]) err = 'البطاقة مكررة في القائمة';
        else if (r.dataset.exists === no.value) err = '⚠ البطاقة هذه موجودة من قبل في النظام';
        else if (pin.value.length !== PD) err = 'الرمز يجب ' + PD + ' أرقام';
        seen[no.value] = 1;
        no.classList.toggle('bad', !!err && !err.startsWith('الرمز')); no.classList.toggle('ok', !err);
        pin.classList.toggle('bad', err.startsWith('الرمز'));
        msg.className = 'msg ' + (err ? 'bad' : 'ok'); msg.textContent = err || (no.value.length === CD ? '✓ تمام' : '');
        if (err) okAll = false;
      });
      $('codes').value = lines().map(x => x.join(' ')).join('\n');
      $('buyBtn').disabled = !okAll || !n;
      return n;
    };
    const check = r => {
      const no = r.querySelector('.no').value;
      if (no.length !== CD) return;
      fetch(checkUrl + '?card_no=' + encodeURIComponent(no), { headers: { Accept: 'application/json' } }).then(x => x.json()).then(d => {
        if (r.querySelector('.no').value !== no) return;
        r.dataset.exists = d.exists ? no : '';
        if (d.exists) { alert('⚠ البطاقة ' + no + ' موجودة من قبل في النظام' + (d.card ? '\n(' + d.card + ')' : '') + '\nلا تستطيع أن تدخلها مرة ثانية'); }
        validate(); upd();
      }).catch(() => {});
    };
    const addRow = (no = '', pin = '') => {
      const r = document.createElement('div'); r.className = 'cr';
      r.innerHTML = '<input class="no" inputmode="numeric" autocomplete="off" maxlength="' + CD + '" placeholder="' + '•'.repeat(Math.min(CD, 16)) + '"><input class="pin" inputmode="numeric" autocomplete="off" maxlength="' + PD + '" placeholder="' + '•'.repeat(PD) + '"><button type="button" class="x" title="شيل">✕</button><div class="msg"></div>';
      const ni = r.querySelector('.no'), pi = r.querySelector('.pin');
      ni.value = digits(no).slice(0, CD); pi.value = digits(pin).slice(0, PD);
      ni.addEventListener('input', () => { ni.value = digits(ni.value).slice(0, CD); r.dataset.exists = ''; validate(); upd(); if (ni.value.length === CD) { check(r); pi.focus(); } });
      pi.addEventListener('input', () => { pi.value = digits(pi.value).slice(0, PD); validate(); upd(); if (pi.value.length === PD && r === rows.lastElementChild) { /* جاهز */ } });
      ni.addEventListener('paste', e => { const t = (e.clipboardData || window.clipboardData).getData('text'); const p = t.trim().split(/[\s,;|،]+/); if (p.length > 1) { e.preventDefault(); ni.value = digits(p[0]).slice(0, CD); pi.value = digits(p[1] || '').slice(0, PD); validate(); upd(); check(r); } });
      r.querySelector('.x').onclick = () => { if (rows.children.length > 1) r.remove(); else { ni.value = ''; pi.value = ''; } validate(); upd(); };
      rows.appendChild(r); if (ni.value) check(r); return r;
    };
    $('addRow').onclick = () => addRow().querySelector('.no').focus();
    $('pasteBtn').onclick = () => {
      const t = prompt('الصق البطاقات (كل بطاقة في سطر: الرقم ثم الرمز)'); if (!t) return;
      const ls = t.split(/\n/).map(x => x.trim()).filter(Boolean);
      if (rows.children.length === 1 && !rows.querySelector('.no').value) rows.innerHTML = '';
      ls.forEach(l => { const p = l.split(/[\s,;|،]+/); addRow(p[0] || '', p[1] || ''); }); validate(); upd();
    };
    const old = $('codes').value.trim();
    if (old) old.split(/\n/).forEach(l => { const p = l.trim().split(/\s+/); addRow(p[0], p[1] || ''); }); else addRow();
    const upd = () => {
      const n = lines().length, face = parseFloat($('face').value) || 0, cost = parseFloat($('cost').value) || 0;
      const each = document.querySelector('input[name=cost_mode]:checked').value === 'each', cur = $('ccur').value, rate = parseFloat($('rate').value) || 0;
      const total = each ? cost * n : cost, lyd = cur === 'dinar' ? total : total * rate, faceAll = face * n;
      $('rateBox').hidden = cur === 'dinar';
      $('cnt').textContent = n + ' بطاقة · ' + SG.fmt(faceAll) + ' $';
      $('calc').innerHTML = n && faceAll ? 'إجمالي: <b class="num">' + SG.fmt2(total) + (cur === 'dinar' ? ' د.ل' : ' $') + '</b> · الدولار في البطاقة كلفك <b class="num">' + (lyd / faceAll).toFixed(3) + '</b> د.ل' + (cur === 'dollar' ? ' (خصم ' + ((1 - total / faceAll) * 100).toFixed(1) + '%)' : '') : 'اكتب البطاقات والمبلغ';
      $('buyBtn').textContent = 'حفظ ' + (n || '') + ' بطاقة';
    };
    ['face', 'cost', 'ccur', 'rate'].forEach(id => $(id).addEventListener('input', upd));
    document.querySelectorAll('input[name=cost_mode]').forEach(r => r.addEventListener('change', upd));
    $('buyF').addEventListener('submit', e => { if (!validate()) { e.preventDefault(); alert('اكتب رقم بطاقة واحدة على الأقل'); } else if ($('buyBtn').disabled) e.preventDefault(); });
    validate(); upd();
  }
  // نافذة الاستخدام
  if ($('mUse')) {
    const amts = [...document.querySelectorAll('#mUse .amt')];
    const sum = () => { $('uSum').textContent = 'المجموع ' + SG.fmt2(amts.reduce((a, i) => a + (parseFloat(i.value) || 0), 0)) + ' $'; };
    amts.forEach(i => i.addEventListener('input', sum));
    $('uAuto').onclick = () => { let left = parseFloat(($('uTrip').selectedOptions[0] || {}).dataset?.left || 0); amts.forEach(i => { const v = Math.min(left, +i.dataset.bal); i.value = v > 0 ? Math.round(v * 100) / 100 : 0; left -= v; }); sum(); };
    $('uTrip').addEventListener('change', () => $('uAuto').click());
  }
  // كشف الرقم السري
  if ($('pinBtn')) $('pinBtn').onclick = () => fetch($('pinBtn').dataset.url, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(d => {
    $('cardNo').textContent = d.card_no; $('pinBox').hidden = false; $('pinBox').textContent = '';
    const line = (lbl, v) => { const r = document.createElement('div'); r.append(lbl + ': '); const b = document.createElement('b'); b.className = 'num'; b.textContent = v || '—'; r.append(b); $('pinBox').append(r); };
    line('الكود', d.card_no); line('الرقم السري', d.pin);
    const btn = document.createElement('button'); btn.type = 'button'; btn.className = 'btn ghost sm mt'; btn.textContent = 'نسخ الكود'; btn.onclick = () => navigator.clipboard.writeText(d.card_no); $('pinBox').append(btn);
  });
})();
</script>
@endpush
