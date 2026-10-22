@extends('sg.layout')
@section('title', 'الطلبات')
@section('bodyClass', 'soft')
@php
    use App\Services\TreasuryService as TS;
    $f = fn ($n) => TS::fmt($n);
    $stages = [['all', 'الكل', '#262c4a'], ['none', 'ليس في رحلة', '#e0a100'], ['prep', 'قيد التجهيز', '#6250d6'], ['way', 'في الطريق', '#2a78d6'], ['ready', 'جاهزة · في الرف', '#12805c'], ['debt', 'عليها باقي', '#c4352f'], ['done', 'تم التسليم', '#b7bccb'], ['cx', 'ملغاة', '#d5d9e4']];
    $cur = request('stage', 'all');
@endphp
@push('style')
<style>
.stages { display: grid; grid-template-columns: repeat(8, 1fr); gap: 8px; margin-bottom: 14px; }
.stg { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 12px 14px; position: relative; display: block; }
.stg small { display: block; font-size: 13px; color: var(--ink-2); }
.stg b { font-size: 24px; font-weight: 800; }
.stg .bar { position: absolute; bottom: 0; right: 14px; left: 14px; height: 3px; border-radius: 3px 3px 0 0; }
.stg.on { background: var(--brand); border-color: var(--brand); color: #fff; }
.stg.on small { color: #c9cde0; }
.stg.alert b { color: var(--down); } .stg.on.alert b { color: #ff9b94; }
.bulk { display: none; align-items: center; gap: 10px; background: var(--s1-bg); padding: 10px 16px; border-bottom: 1px solid #d4e3f7; font-size: 14px; flex-wrap: wrap; }
.bulk.show { display: flex; }
.rc b { font-family: InterNum; font-weight: 800; font-size: 14.5px; display: block; }
.rc small { color: var(--ink-3); font-size: 12px; margin-inline-end: 4px; }
.rc .pf { font-size: 10px; padding: 1px 6px; }
.trip-s { font-family: InterNum; font-size: 12px; color: var(--ink-3); display: block; margin-top: 3px; }
table.t tr.picked td { background: #f5f9ff; }
.om .om-btn { font-weight: 700; border: 1.5px solid var(--line); min-width: 104px; }
.om .menu { min-width: 220px; padding: 6px; }
/* خانة الأزرار: الأزرار كلها تبان، والقائمة تنفتح تحتهم ما تغطيش عليهم */
table.t td.oacts { white-space: normal; min-width: 250px; vertical-align: top; }
td.oacts > .btn, td.oacts > div { display: inline-flex; margin: 0 0 6px 4px !important; vertical-align: top; }
td.oacts .om { display: block !important; width: 100%; }
td.oacts .om > .menu { position: static; margin-top: 6px; box-shadow: none; border: 1px solid var(--line); }
.om .menu a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; font-size: 14.5px; border-radius: 10px; }
.om .menu a i { font-style: normal; width: 22px; text-align: center; font-size: 16px; }
.om .menu a small { margin-inline-start: auto; color: var(--ink-3); font-size: 11.5px; }
.om .menu a:hover { background: var(--line-soft, #f3f4f8); }
.om .menu a.g { color: #0f6b4c; font-weight: 700; } .om .menu a.r { color: var(--down); border-top: 1px solid var(--line); margin-top: 4px; }
@media (max-width: 1100px) { .stages { grid-template-columns: repeat(4, 1fr); } }
</style>
@endpush
@section('content')
@php $depPct = (float) \App\Services\AppSettings::get('deposit_pct', 30); $isAdm = auth()->user()->hasRole('Admin'); @endphp
<div class="top">
    <div><h1>الطلبات</h1><div class="sub">كل الإيصالات وأين وصلت</div></div>
    <div class="acts">
        @can('soldout-manage')@php $soldN = \Illuminate\Support\Facades\Schema::hasTable('order_soldouts') ? \App\Models\OrderSoldout::where('status', 'pending')->count() : 0; @endphp
        <a class="btn ghost" href="{{ route('sg.soldouts') }}">🚫 منتجات نفدت @if ($soldN)<span class="chip w">{{ $soldN }}</span>@endif</a>@endcan
        @php $issN = \Illuminate\Support\Facades\Schema::hasTable('order_issues') ? \App\Models\OrderIssue::where('status', 'open')->count() : 0; @endphp
        <a class="btn ghost" href="{{ route('sg.issues') }}">⚠ المشاكل @if ($issN)<span class="chip w">{{ $issN }}</span>@endif</a>
        @can('trip-purchase-create')<a href="{{ route('finance.trips.create') }}" class="btn ghost">✈ إضافة رحلة</a>@endcan
        @can('shein-card-sell')<a href="{{ route('sg.cards.sell') }}" class="btn ghost">💳 إيصال بيع بطاقات SHEIN</a>@endcan
        @can('order-create')<a href="{{ route('sg.orders.create') }}" class="btn">+ إيصال جديد</a>@endcan @can('order-create')<a href="{{ route('sg.sitepay') }}" class="btn ghost">🌐 دفع مواقع</a>@endcan
        <button class="sg-burger">☰</button>
    </div>
</div>

<div class="stages">
    @foreach ($stages as [$k, $t, $c])
        <a href="{{ request()->fullUrlWithQuery(['stage' => $k === 'all' ? null : $k, 'page' => null]) }}" class="stg {{ $cur === $k ? 'on' : '' }} {{ $k === 'debt' ? 'alert' : '' }}"><span class="dot" style="background:{{ $c }}"></span>{{ $t }}@if ($isAdm || !in_array($k, ['all', 'done'], true))<span class="n">{{ $f($counts[$k]) }}</span>@endif</a>
    @endforeach
</div>

<div class="card tw">
    <form method="get" class="tw-h">
        @if (request('stage'))<input type="hidden" name="stage" value="{{ request('stage') }}">@endif
        <div class="sx"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input name="q" id="liveQ" value="{{ request('q') }}" placeholder="اكتب: كود عميل، اسم، رقم إيصال، هاتف… واضغط Enter" autocomplete="off" data-nolive data-suggest="all"></div>
        <select name="platform" class="pill" data-autosubmit><option value="">المنصة: الكل</option>@foreach ($platforms as $pl)<option value="{{ $pl->id }}" @selected(request('platform') == $pl->id)>{{ $pl->name }}</option>@endforeach</select>
        <input name="trip" id="liveT" data-suggest="trip" class="pill" value="{{ request('trip') }}" placeholder="✈ رقم الرحلة…" autocomplete="off" inputmode="numeric" style="min-width:210px;font-size:15px;font-weight:700"><datalist id="tripList">@foreach ($trips as $t)<option value="{{ $t }}">@endforeach</datalist>
        <select name="user" class="pill" data-autosubmit><option value="">الموظف: الكل</option>@foreach ($users as $us)<option value="{{ $us->id }}" @selected(request('user') == $us->id)>{{ $us->name }}</option>@endforeach</select>
        <button class="btn" type="submit">🔍 بحث</button>@if (request('q') || request('trip'))<a class="btn ghost" href="{{ route('sg.orders.index') }}">✕ مسح</a>@endif
        @if (request()->hasAny(['q', 'platform', 'trip', 'user']))<a class="note" href="{{ route('sg.orders.index', array_filter(['stage' => request('stage')])) }}">✕ مسح</a>@endif
    </form>

    <form method="post" action="{{ route('sg.orders.bulk') }}" id="bulkForm">
        @csrf
        <div class="bulk" id="bulkBar">
            <b class="num" id="selN">0</b> إيصالات محددة
            <span style="margin-inline-start:auto;display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                @can('trip-purchase-create')<button name="action" value="trip" class="btn sm">✈ أضفهم لرحلة</button>@endcan
                <select name="status" class="pill" style="height:34px"><option value="">— الحالة —</option>@foreach ($statuses as $st)<option>{{ $st->name }}</option>@endforeach</select>
                <button name="action" value="status" class="btn ghost sm">تغيير الحالة</button>
                @can('order-edit')<input name="shelf" class="pill" style="height:34px;width:110px" placeholder="رقم الرف" list="shelvesAll"><button name="action" value="shelf" class="btn ghost sm" onclick="if(!this.form.shelf.value.trim()){alert('اكتب رقم الرف');return false}">🗄 حطهم في الرف</button>
                <datalist id="shelvesAll">@foreach (\App\Services\AppSettings::shelves() as $sh)<option value="{{ $sh }}">@endforeach</datalist>@endcan
                <button type="button" class="btn wa sm" data-modal="waModal">💬 واتساب</button>
            </span>
        </div>
        <div class="scroll-x">
        <table class="t">
            <thead><tr>
                <th style="width:36px"><input type="checkbox" id="all"></th><th>الإيصال</th><th>العميل</th><th>الحالة والرحلة</th>
                <th class="n">القطع</th><th class="n">القيمة</th><th class="n">المدفوع</th><th class="n">الباقي</th><th>بيانات الطرد</th><th></th>
            </tr></thead>
            <tbody>
            @php $openIss = \Illuminate\Support\Facades\Schema::hasTable('order_issues') ? \App\Models\OrderIssue::whereIn('order_id', $orders->pluck('id'))->where('status', 'open')->get()->groupBy('order_id') : collect();
                $soldN = \Illuminate\Support\Facades\Schema::hasTable('order_soldouts') ? \App\Models\OrderSoldout::whereIn('order_id', $orders->pluck('id'))->where('status', 'pending')->selectRaw('order_id, COUNT(*) n')->groupBy('order_id')->pluck('n', 'order_id') : collect(); @endphp
            @php $cartOrd = \App\Support\Fast::hasColumn('cart_quotes', 'order_id') ? \App\Models\CartQuote::whereIn('order_id', $orders->pluck('id'))->whereNotNull('source_url')->pluck('order_id')->flip() : collect(); @endphp
            @forelse ($orders as $o)
                @php $st = $o->stage(); $rem = $o->remaining(); @endphp
                <tr>
                    <td><input type="checkbox" class="pick" name="ids[]" value="{{ $o->id }}"></td>
                    <td class="rc"><a href="{{ route('sg.orders.show', $o) }}"><b>{{ $o->code }}</b></a><small>{{ $o->created_at->isToday() ? 'اليوم ' . $o->created_at->format('g:i') : $o->created_at->format('Y-m-d') }}</small> @if ($o->platform)<span class="pf {{ strtolower($o->platform->name) }}">{{ $o->platform->name }}</span>@endif
                        @if (isset($openIss[$o->id]))<details style="margin-top:4px"><summary class="chip d" style="cursor:pointer;list-style:none;display:inline-block">⚠ فيه مشكلة ({{ $openIss[$o->id]->count() }})</summary>
                            <div style="background:#fff4e5;color:#8a5a00;border-radius:10px;padding:8px 10px;margin-top:4px;font-size:12.5px;line-height:1.7;min-width:200px">@foreach ($openIss[$o->id] as $is)<div>• <b>{{ $is->typeLabel() }}</b>{{ $is->qty ? ' · ' . $is->qty . ' قطعة' : '' }}{{ (float) $is->value > 0 ? ' · ' . $f($is->value) . ' د.ل' : '' }}{{ $is->note ? ' — ' . $is->note : '' }} <small class="note">{{ optional($is->created_at)->format('j/n') }}</small></div>@endforeach
                            <a href="{{ route('sg.orders.show', $o) }}" style="color:#8a5a00;text-decoration:underline">فتح الإيصال</a> · تقدر تخصمها وقت التسليم</div></details>@endif</td>
                    <td class="cell2"><b>{{ optional($o->customer)->name }}@if (optional($o->customer)->is_vip) <span class="chip w" style="font-size:10.5px">⭐ VIP</span>@endif</b><small class="num">{{ optional($o->customer)->code }}</small></td>
                    <td><div style="display:flex;align-items:center;gap:10px"><span class="st {{ $st }}"><i></i>{{ $o->stageLabel() }}</span><div class="steps5">@for ($k = 1; $k <= 5; $k++)<i class="{{ $k <= $o->stepsDone() ? 'on' : '' }}"></i>@endfor</div></div>@if ($o->trip_details)<span class="trip-s">رحلة {{ $o->trip_details }}</span>@elseif ($o->no_trip ?? false)<span class="trip-s">🚫 بدون رحلة</span>@endif</td>
                    <td class="n num">{{ $o->received_qty !== null && $o->received_qty != $o->qty ? $o->received_qty . '/' : '' }}{{ $o->qty }}</td>
                    <td class="n num">{{ $f($o->total_price - $o->discount) }}@if ($o->ship_price > 0)<small style="display:block;color:var(--ink-3);font-size:11.5px">+ شحن {{ $f($o->ship_price) }}</small>@endif</td>
                    <td class="n num plus">{{ $f($o->paid_price) }}</td>
                    <td class="n num">@if ($st === 'cx')<span class="note">—</span>@elseif ($rem > 0.009)<b class="minus">{{ $f($rem) }}</b>@else<span class="note">✓</span>@endif</td>
                    <td style="font-size:12.5px;line-height:1.7;min-width:150px">@php $bg = $o->bags_num ?: $o->bags_qty; $rc = $o->received_qty ?? ($o->arrived ? $o->qty : null); @endphp
                        <div>نوع الطرد: <b>{{ $o->package_type ?: '—' }}</b></div>
                        <div>عدد الطرود: <b class="num">{{ $bg ?: '—' }}</b></div>
                        <div>الرف: @if ($o->storage_num)<span class="shelf">{{ $o->storage_num }}</span>@else<b>—</b>@endif</div>
                        <div>وصل: <b class="num">{{ $rc !== null ? $rc . ' من ' . $o->qty : '—' }}</b></div>
                        <div>سعر الشحن: <b class="num">{{ $o->ship_price > 0 ? $f($o->ship_price) : '—' }}</b></div></td>
                    <td class="oacts">@if (!$o->delivered && !$o->arrived && $st !== 'cx' && $o->customer && !$o->trip_details && !$o->storage_num && !($o->ship_price > 0) && !($o->weight ?? null))<button type="button" class="btn sm" style="background:#e8590c;color:#fff;margin-bottom:6px;white-space:nowrap" onclick="openDep({{ json_encode(['id' => $o->id, 'code' => $o->code, 'name' => optional($o->customer)->name, 'ccode' => optional($o->customer)->code, 'rem' => round($rem, 2), 'def' => $rem > 0.009 ? (max(0, min(round($rem, 2), ceil(($o->total_price - $o->discount) * $depPct / 100) - (float) $o->paid_price)) ?: round($rem, 2)) : 0]) }})">🛵 عربون مع مندوب</button>@endif
                    @php $canDel = !$o->delivered && $st !== 'cx' && $o->customer_id && !$o->delivery_id; $dJs = json_encode(['id' => $o->id, 'code' => $o->code, 'name' => optional($o->customer)->name, 'ccode' => optional($o->customer)->code]); @endphp
                    @php $missN = ((int) $o->missing_qty > 0 && !$o->missing_action ? 1 : 0) + (int) ($soldN[$o->id] ?? 0) + (isset($openIss[$o->id]) ? $openIss[$o->id]->where('type', 'missing')->count() : 0); @endphp
                    @if ($st !== 'cx')<button type="button" class="btn ghost sm" style="margin-bottom:6px;white-space:nowrap;{{ $missN ? 'color:#b3261e;border-color:#f3b8b2' : '' }}" data-miss="{{ $o->id }}" data-miss-code="{{ $o->code }}">{{ $missN ? '📉 نواقص (' . $missN . ')' : '📉 النواقص' }}</button>@endif
                    @can('delivery-create')@if ($canDel)<div style="display:flex;gap:5px;margin-bottom:6px;white-space:nowrap"><button type="button" class="btn ghost sm" onclick="openDeliver({{ $dJs }}, true)">🛵 مندوب</button><button type="button" class="btn green sm" onclick="openDeliver({{ $dJs }})">✅ تسليم</button></div>@endif @endcan
                    <div class="dropdown om"><button type="button" class="btn ghost sm om-btn" data-dd>⚙ الأوامر ▾</button><div class="menu">
                        <a href="{{ route('sg.orders.show', $o) }}"><i>👁</i>فتح الإيصال</a>
                        @if (!$o->delivered && $st !== 'cx' && $o->customer_id && !$o->delivery_id)<a href="#" class="g" onclick="event.preventDefault();openDeliver({{ $dJs }})"><i>📦</i>استلام الطلب <small>يتسلم هني من غير ما تطلع</small></a>@endif
                        @if ($rem > 0.009 && $st !== 'cx')@can('order-edit')<a href="{{ route('sg.orders.show', $o) }}#pay"><i>💵</i>دفع الباقي <small class="num">{{ $f($rem) }}</small></a>@endcan @endif
                        @can('order-edit')<a href="{{ route('sg.orders.edit', $o->id) }}"><i>✎</i>تعديل الإيصال</a>@endcan
                        @can('soldout-manage')@if (!$o->delivered && $st !== 'cx')<a href="#" onclick="event.preventDefault();openSold({{ json_encode(['id' => $o->id, 'code' => $o->code, 'name' => optional($o->customer)->name, 'ccode' => optional($o->customer)->code, 'items' => []]) }})"><i>🚫</i>نواقص <small>منتج نفد</small></a>@endif @endcan
                        <a href="{{ route('sg.orders.show', $o) }}#pays"><i>🧾</i>سجل الدفعات</a>
                        @can('delivery-undo')@if ($o->delivered && $o->kind !== 'card_sale' && $o->kind !== 'site_pay')<a href="#" class="r" onclick="event.preventDefault();openUndo({{ json_encode(['id' => $o->id, 'code' => $o->code, 'name' => optional($o->customer)->name, 'by' => optional($o->deliveredUser)->name, 'paid' => round((float) $o->paid_price, 2)]) }})"><i>↩</i>إلغاء التسليم <small>يرجع جاهز للاستلام</small></a>@endif @endcan
                        <a href="{{ route('order.invoice', $o->code) }}" target="_blank"><i>🖨</i>طباعة الإيصال</a>
                        @can('cart-list')@if (isset($cartOrd[$o->id]))<a href="#" class="g" onclick="event.preventDefault();if(confirm('نبعتو سلة الإيصال {{ $o->code }} لقروب الحجز (منتظرة)؟ الرابط + الكود + PDF لو فيه تكرار'))(function(u){const f=document.createElement('form');f.method='post';f.action=u;f.innerHTML='<input type=hidden name=_token value=\''+document.querySelector('meta[name=csrf-token]').content+'\'>';document.body.appendChild(f);f.submit()})('{{ route('sg.orders.togroup', $o) }}')"><i>📤</i>إرسال لقروب منتظرة <small>رابط + كود + PDF لو فيه تكرار</small></a>@endif @endcan
                        @can('order-edit')@if ($o->customer)<a href="#" class="g" onclick="event.preventDefault();if(confirm('نبعتو الإيصال {{ $o->code }} للعميل على الواتساب؟'))(function(u){const f=document.createElement('form');f.method='post';f.action=u;f.innerHTML='<input type=hidden name=_token value=\''+document.querySelector('meta[name=csrf-token]').content+'\'>';document.body.appendChild(f);f.submit()})('{{ route('sg.orders.send.receipt', $o) }}')"><i>📤</i>إرسال الإيصال للعميل <small>PDF على الواتساب</small></a>@endif @endcan
                        @if ($st === 'none' && !($o->no_trip ?? false))@can('trip-purchase-create')<a href="{{ route('finance.trips.create', ['ids' => $o->id]) }}"><i>✈</i>إضافة لرحلة</a>@endcan @endif
                        @if (!$o->delivered && $st !== 'cx' && !in_array($o->kind, ['shipping', 'card_sale', 'site_pay']) && (($o->no_trip ?? false) || !$o->trip_details))@can('order-edit')<a href="#" onclick="event.preventDefault();(function(u){const f=document.createElement('form');f.method='post';f.action=u;f.innerHTML='<input type=hidden name=_token value=\''+document.querySelector('meta[name=csrf-token]').content+'\'>';document.body.appendChild(f);f.submit()})('{{ route('sg.orders.notrip', $o) }}')"><i>{{ ($o->no_trip ?? false) ? '↩' : '🚫' }}</i>{{ ($o->no_trip ?? false) ? 'إلغاء «بدون رحلة»' : 'بدون رحلة' }} <small>{{ ($o->no_trip ?? false) ? 'ترجع الحالة العادية' : 'الزبون يشوف: سيصل قريباً' }}</small></a>@endcan @endif
                        @can('order-delete')<a href="{{ route('sg.orders.show', $o) }}#del" class="r"><i>🗑</i>حذف الإيصال <small>ترجيع القيمة اختياري</small></a>@endcan
                    </div></div></td>
                </tr>
            @empty
                <tr><td colspan="10"><div class="empty"><span class="ic">📭</span>لا يوجد إيصالات هنا</div></td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div class="modal-bg" id="waModal"><div class="modal">
            <h3>رسالة واتساب للمحددين</h3>
            <textarea name="message" class="inp" rows="6" placeholder="اهلا {الاسم}، طلبك {رقم_الإيصال}…"></textarea>
            <div class="note" style="margin:8px 0">يمكنك أن تستخدم: {الاسم} {رقم_الإيصال} {الرحلة} {الحالة} {الباقي}</div>
            <div style="display:flex;gap:8px"><button name="action" value="whatsapp" class="btn green">أرسل</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
        </div></div>
    </form>
    <div class="tw-f">
        @role('Admin')
        <span>المعروض <b class="num">{{ $orders->count() }}</b> من <b class="num">{{ $f($orders->total()) }}</b></span>
        <span>القيمة <b class="num">{{ $f($totals->v) }}</b></span>
        <span>المدفوع <b class="num plus">{{ $f($totals->p) }}</b></span>
        <span>الباقي <b class="num minus">{{ $f($remaining) }}</b></span>
        @endrole
        @include('sg.partials.pager', ['p' => $orders])
    </div>
</div>
@include('sg.soldouts.partials.quick')
<button type="button" id="depOpener" data-modal="depModal" hidden></button>
<div class="modal-bg" id="depModal"><div class="modal" style="max-width:440px">
    <h3>🛵 عربون مع المندوب · <span class="num" id="depCode"></span></h3>
    <div class="note mb" id="depWho"></div>
    <form method="post" id="depF" data-base="{{ route('sg.orders.depcourier', 0) }}">@csrf
        <div class="f"><label class="l">قداش يقبض عربون؟ (د.ل)</label><input name="amount" id="depAmt" class="inp numr" inputmode="decimal" required style="font-size:22px;font-weight:800;text-align:center"><div class="note" id="depRem"></div></div>
        <div class="grid g2 f"><div><label class="l">المندوب</label><select name="courier_id" class="inp"><option value="">— أوتوماتيك —</option>@foreach (\App\Services\CourierService::couriers() as $cu)<option value="{{ $cu->id }}">{{ $cu->name }}</option>@endforeach</select></div>
            <div><label class="l">أجرة المندوب (على العميل)</label><input name="fee" class="inp numr" value="{{ (float) \App\Services\AppSettings::get('courier_default_fee', 10) }}" inputmode="decimal"></div></div>
        <label class="ck"><input type="checkbox" name="notify" value="1" checked> رسالة للعميل إن المندوب جاي يقبض</label>
        <div style="display:flex;gap:8px;margin-top:10px"><button class="btn" style="background:#e8590c;color:#fff">🛵 ابعته للمندوب</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
        <div class="note mt">المندوب يشوفها في تطبيقه «تحصيل عربون — ما فيش طرد». لما يقبض، الدفعة تنزل على الإيصال ويوصل العميل إيصال.</div>
    </form>
</div></div>
<script>
window.openDep = function (o) {
  const f = document.getElementById('depF'); f.action = f.dataset.base.replace(/\/0\/deposit-courier$/, '/' + o.id + '/deposit-courier');
  document.getElementById('depCode').textContent = o.code; document.getElementById('depWho').textContent = (o.name || '') + (o.ccode ? ' · كود ' + o.ccode : '');
  document.getElementById('depAmt').value = o.def; document.getElementById('depRem').textContent = o.rem > 0 ? 'الباقي على الإيصال: ' + o.rem + ' د.ل' : '✓ الإيصال مدفوع كامل — اكتب 0 لو المندوب يقبض أجرته بس';
  document.getElementById('depOpener').click();
};
</script>
@include('sg.partials.undo-delivery')
@include('sg.partials.quick-deliver')
@include('sg.partials.missing-modal')
@endsection
@push('script')
<script>
(function () {
  const picks = [...document.querySelectorAll('.pick')], bar = document.getElementById('bulkBar'), n = document.getElementById('selN');
  function upd() { const c = picks.filter(p => p.checked).length; n.textContent = c; bar.classList.toggle('show', c > 0); picks.forEach(p => p.closest('tr').classList.toggle('picked', p.checked)); }
  picks.forEach(p => p.addEventListener('change', upd));
  document.getElementById('all').addEventListener('change', e => { picks.forEach(p => p.checked = e.target.checked); upd(); });
})();
// 🔍 بحث كيف المنظومة القديمة: تكتب وتضغط Enter أو «بحث» — ويدوّر في كل الطلبات (مش في التبويب المختار بس)
(function () {
  const q = document.getElementById('liveQ'); if (!q) return;
  const tr = document.getElementById('liveT');
  q.form.addEventListener('submit', () => {
    if (q.value.trim() || (tr && tr.value.trim())) { q.form.querySelectorAll('input[name=stage], input[name=page]').forEach(i => i.remove()); }
  });
})();
</script>
@include('sg.partials.suggest')
@endpush
