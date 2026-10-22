@extends('sg.layout')
@section('title', 'إيصال ' . $order->code)
@section('bodyClass', 'soft')
@php
    use App\Services\TreasuryService as TS;
    $f = fn ($n) => TS::fmt($n);
    $st = $order->stage();
    $c = $order->customer;
    $trip = $order->tripPurchase;
    $steps = [
        ['تسجّل', $order->created_at->format('j/n') . ' · ' . optional($order->createdUser)->name, true],
        [$order->trip_details ? 'دخل رحلة ' . $order->trip_details : 'دخل رحلة', $trip ? $trip->purchased_at->format('j/n') : '', (bool) $order->trip_details],
        ['انشرى', $trip ? $f($trip->cost) . ' ' . TS::sign($trip->cost_currency) . ' للرحلة' : '', (bool) $trip],
        [in_array($st, ['way', 'ready', 'done']) ? ($order->trip_status ?: 'في الطريق') : 'في الطريق', '', in_array($st, ['way', 'ready', 'done'])],
        ['وصل · في الرف', $order->arrived ? (($order->received_at ? $order->received_at->format('j/n') : $order->arrived_at) . ($order->storage_num ? ' · ' . $order->storage_num : '')) : '', (bool) $order->arrived],
        ['تسلّم', $order->delivered ? ($order->delivered_at . ' · ' . optional($order->deliveredUser)->name) : ($order->shelfDays() !== null ? 'من ' . $order->shelfDays() . ' يوم في الرف' : ''), (bool) $order->delivered],
    ];
    $soldCards = $order->kind === 'card_sale' ? \App\Services\CardSaleService::cardsOf($order) : collect();
    if ($order->kind === 'card_sale') {
        $sentAt = $soldCards->max('sent_at');
        $steps = [
            ['تسجّل', $order->created_at->format('j/n') . ' · ' . optional($order->createdUser)->name, true],
            ['بيع ' . $soldCards->count() . ' بطاقة SHEIN', $f($soldCards->sum('face_value')) . ' $', true],
            ['أُرسلت للعميل', $sentAt ? \Carbon\Carbon::parse($sentAt)->format('j/n g:i') : 'لم تُرسل بعد', (bool) $sentAt],
            ['خالص', $order->remaining() > 0.009 ? 'يبقى ' . $f($order->remaining()) : '', $order->remaining() <= 0.009],
        ];
    }
    $nowStep = collect($steps)->search(fn ($s) => !$s[2]);
    $hasMissing = $order->received_qty !== null && $order->received_qty < $order->qty && !$order->missing_action;
    $missingQty = $hasMissing ? $order->qty - $order->received_qty : 0;
    $unit = $order->qty ? ($order->total_price / $order->qty) : 0;
    $events = collect();
    foreach ($order->notes as $n) { $events->push(['t' => $n->created_at, 'ic' => $n->visible ? '📤' : '📝', 'bg' => $n->visible ? 'var(--wa-bg)' : 'var(--warn-bg)', 'x' => ($n->visible ? 'للعميل: ' : '') . $n->note, 'by' => optional($n->user)->name]); }
    foreach ($messages as $m) { $events->push(['t' => $m->created_at, 'ic' => '💬', 'bg' => '#e7f8ee', 'x' => 'رسالة ' . (optional(\App\Services\MessageService::template($m->type))->title ?? $m->type) . ($m->status === 'sent' ? '' : ' (لم تصل)'), 'by' => $m->status === 'sent' ? '✓✓ وصلت' : '✕']); }
    foreach ($logs as $l) { $events->push(['t' => $l->created_at, 'ic' => '•', 'bg' => 'var(--line-soft)', 'x' => trim($l->log), 'by' => optional($l->user)->name]); }
    $events = $events->sortByDesc('t')->take(25);
@endphp
@push('style')
<style>
.hd { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.hd .cd { font-family: InterNum; font-size: 28px; font-weight: 800; }
.hd .acts { margin-inline-start: auto; display: flex; gap: 8px; flex-wrap: wrap; }
.tl { display: grid; grid-template-columns: repeat(6, 1fr); padding: 18px 20px; margin-bottom: 14px; }
.tl .s { position: relative; text-align: center; }
.tl .s::before { content: ""; position: absolute; top: 15px; left: 50%; width: 100%; height: 3px; background: #e2e5ec; }
.tl .s:first-child::before { display: none; }
.tl .s.done::before, .tl .s.now::before { background: var(--up); }
.tl .dot { width: 32px; height: 32px; border-radius: 50%; background: #e2e5ec; color: #fff; display: grid; place-items: center; margin: 0 auto 8px; position: relative; z-index: 1; font-weight: 800; }
.tl .s.done .dot { background: var(--up); }
.tl .s.now .dot { background: #fff; border: 3px solid var(--up); color: var(--up); box-shadow: 0 0 0 5px var(--up-bg); }
.tl b { display: block; font-size: 14px; }
.tl small { display: block; font-size: 12px; color: var(--ink-3); margin-top: 2px; }
.tl .s.todo b { color: var(--ink-3); }
.arr { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
.arr div { background: var(--line-soft); border-radius: 12px; padding: 10px 12px; }
.arr small { display: block; font-size: 12px; color: var(--ink-3); }
.arr b { font-size: 18px; }
.arr .sh { background: var(--brand); color: #fff; } .arr .sh small { color: #b9bfd8; }
.money .rem small { color: #c9cde0; font-size: 13px; }
.money .rem b { display: block; font-size: 40px; font-weight: 800; color: #ff9b94; }
.ev { display: flex; gap: 10px; padding: 9px 0; border-bottom: 1px dashed var(--line); font-size: 13.5px; align-items: center; }
.ev:last-child { border-bottom: 0; }
.ev .ic { width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; font-size: 13px; flex: none; }
.ev small { display: block; color: var(--ink-3); font-size: 12px; }
.missbox { background: rgba(255,193,7,.12); border: 1px solid rgba(255,193,7,.35); border-radius: 12px; padding: 10px 12px; margin-top: 10px; font-size: 13px; }
@media (max-width: 900px) { .tl { grid-template-columns: repeat(3, 1fr); row-gap: 16px; } .arr { grid-template-columns: repeat(2, 1fr); } }
</style>
@endpush
@section('content')
<div class="crumb"><a href="{{ route('sg.orders.index') }}">الطلبات</a> › إيصال {{ $order->code }}</div>
<div class="hd">
    <span class="cd">{{ $order->code }}</span>
    <span class="st {{ $st }}" style="font-size:13.5px;padding:5px 12px"><i></i>{{ $order->stageLabel() }}</span>
    @if ($order->rating)<span class="chip {{ $order->rating <= 2 ? 'd' : 'u' }}" title="{{ $order->rating_note }}">{{ str_repeat('★', $order->rating) }}{{ str_repeat('☆', 5 - $order->rating) }} تقييم العميل{{ $order->rating_note ? ': ' . \Illuminate\Support\Str::limit($order->rating_note, 40) : '' }}</span>@endif
    @if ($order->platform)<span class="pf {{ strtolower($order->platform->name) }}">{{ $order->platform->name }}</span>@endif
    <span class="note">سُجّل {{ $order->created_at->format('Y-m-d g:i') }} · بواسطة {{ optional($order->createdUser)->name }}</span>
    <div class="acts">
        <a class="btn ghost" href="{{ route('order.invoice', $order->code) }}" target="_blank">🖨 طباعة الفاتورة</a>
        @php $missN = \App\Http\Controllers\Sg\MissingController::openCount($order); @endphp
        <button type="button" class="btn ghost" style="{{ $missN ? 'color:#b3261e;border-color:#f3b8b2' : '' }}" data-miss="{{ $order->id }}" data-miss-code="{{ $order->code }}">{{ $missN ? '📉 نواقص (' . $missN . ')' : '📉 النواقص' }}</button>
        @can('order-edit')@if ($order->customer)<form method="post" action="{{ route('sg.orders.send.receipt', $order) }}" style="display:inline" onsubmit="return confirm('نبعتو الإيصال للعميل على الواتساب؟')">@csrf<button class="btn ghost">📤 إرسال الإيصال للعميل</button></form>@endif @endcan
        @can('delivery-undo')@if ($order->delivered)<button type="button" class="btn ghost" style="color:var(--down)" onclick="openUndo({{ json_encode(['id' => $order->id, 'code' => $order->code, 'name' => optional($order->customer)->name, 'by' => optional($order->deliveredUser)->name, 'paid' => round((float) $order->paid_price, 2)]) }})">↩ إلغاء التسليم</button>@endif @endcan
        <a class="btn ghost" href="https://wa.me/{{ \App\Services\MessageService::normalize((string) optional($c)->phone_1) }}" target="_blank">💬 واتساب</a>
        @can('order-edit')@if ($st !== 'cx')<button class="btn ghost" data-issue="{{ route('sg.issues.store', $order) }}" data-issue-code="{{ $order->code }}">⚠ مشكلة</button>@endif @endcan
        @can('soldout-manage')@if ($st !== 'cx' && !$order->delivered)<button class="btn ghost" data-modal="mSold">🚫 منتجات نفدت</button>@endif @endcan
        @can('return-create')@if ($st !== 'cx')<button class="btn ghost" data-modal="mRet">↩ مرتجع / استبدال</button>@endif @endcan
        @can('order-edit')<a class="btn ghost" href="{{ route('sg.orders.edit', $order->id) }}">✎ تعديل</a>
        @if (!$order->delivered && $st !== 'cx')<form method="post" action="{{ route('sg.orders.cancel', $order) }}" data-confirm="هل تريد إلغاء الإيصال؟">@csrf<button class="btn red">إلغاء</button></form>@endif @endcan
        @can('order-delete')<button class="btn red" data-modal="mDel">🗑 حذف</button>@endcan
        <button class="sg-burger">☰</button>
    </div>
</div>

<div class="card tl">
    @foreach ($steps as $i => $s)
        <div class="s {{ $s[2] ? 'done' : ($i === $nowStep ? 'now' : 'todo') }}"><div class="dot">{{ $s[2] ? '✓' : $i + 1 }}</div><b>{{ $s[0] }}</b><small>{{ $s[1] }}</small></div>
    @endforeach
</div>

<div class="side-lay">
    <div>
        <div class="card mb">
            <div class="who">
                <span class="av" style="width:46px;height:46px;font-size:18px">{{ mb_substr(optional($c)->name, 0, 1) }}</span>
                <div style="flex:1"><b style="font-size:16.5px">{{ optional($c)->name }}</b><div class="note"><span class="num">{{ optional($c)->code }}</span> · <span class="num">{{ optional($c)->phone_1 }}</span> · {{ optional($c)->address }} · {{ $custOrders }} طلبية</div></div>
                <a class="btn ghost sm" href="tel:{{ optional($c)->phone_1 }}">📞 اتصال</a>
                <a class="btn ghost sm" href="{{ route('sg.clients', ['id' => optional($c)->id]) }}">ملف العميل ←</a>
            </div>
        </div>

        <div class="card mb">
            <h3>المنتجات <span class="h-note">{{ $order->qty }} قطعة طلبها @if ($order->received_qty !== null)· {{ $order->received_qty }} وصلت @endif</span></h3>
            @if ($order->items->count())
                <table class="t"><thead><tr><th>المنتج</th><th class="n">العدد</th><th class="n">سعر القطعة</th><th class="n">المجموع</th></tr></thead><tbody>
                    @foreach ($order->items as $it)
                        <tr><td class="wrap">{{ $it->name }}</td><td class="n num">{{ $it->quantity }}</td><td class="n num">{{ $f($it->quantity ? $it->price / $it->quantity : $it->price) }}</td><td class="n num">{{ $f($it->price) }}</td></tr>
                    @endforeach
                </tbody></table>
            @else
                <div class="hint">الإيصال متسجل بالمجموع فقط (بدون تفاصيل المنتجات).</div>
            @endif
            @php $rcvOn = $order->items->count() && \Illuminate\Support\Facades\Schema::hasColumn('order_items', 'received_qty') && \Illuminate\Support\Facades\Schema::hasTable('order_item_receives'); @endphp
            @if ($rcvOn)
                @php
                    $rGot = (int) $order->items->sum('received_qty'); $rAll = (int) $order->items->sum('quantity');
                    $rHist = \Illuminate\Support\Facades\DB::table('order_item_receives')->where('order_id', $order->id)->orderByDesc('id')->get();
                @endphp
                <details style="margin-top:12px;border:1px solid var(--line);border-radius:12px;padding:10px 12px" {{ $rHist->isEmpty() ? '' : 'open' }}>
                    <summary style="cursor:pointer;font-weight:800">📦 استلام المنتجات <span class="chip {{ $rGot >= $rAll ? 'u' : ($rGot ? 'w' : 'n') }}">{{ $rGot }} من {{ $rAll }} وصلت{{ $rGot >= $rAll ? ' ✓ بالكامل' : ' · باقي ' . ($rAll - $rGot) }}</span></summary>
                    @can('order-edit')
                    @if ($rGot < $rAll)
                    <form method="post" action="{{ route('sg.orders.items.receive', $order) }}" style="margin-top:10px">@csrf
                        <table class="t"><thead><tr><th>الصنف</th><th class="n">طلب</th><th class="n">استلم</th><th class="n">باقي</th><th class="n" style="width:90px">وصل توا</th></tr></thead><tbody>
                        @foreach ($order->items as $it)
                            @php $iLeft = max(0, (int) $it->quantity - (int) $it->received_qty); @endphp
                            <tr><td class="wrap">{{ $it->name }}</td><td class="n num">{{ $it->quantity }}</td><td class="n num">{{ (int) $it->received_qty }}</td><td class="n num" style="{{ $iLeft ? 'color:var(--down);font-weight:800' : '' }}">{{ $iLeft }}</td>
                                <td class="n">@if ($iLeft)<input class="inp numr" name="got[{{ $it->id }}]" type="number" min="0" max="{{ $iLeft }}" placeholder="0" style="height:38px;width:80px">@else<span class="chip u">✓</span>@endif</td></tr>
                        @endforeach
                        </tbody></table>
                        <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;align-items:center"><button type="button" class="btn ghost sm" onclick="this.form.querySelectorAll('input[name^=got]').forEach(i=>i.value=i.max)">✓ كل الباقي وصل</button>
                            <span class="note">💵 دفع توا (اختياري)</span><input class="inp numr" name="paid" placeholder="0" style="height:38px;width:110px">
                            <select name="payment_id" class="inp" style="height:38px;width:150px">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == \App\Services\AppSettings::cashPaymentId())>{{ $pm->name }}</option>@endforeach</select></div>
                        <label style="display:flex;gap:6px;align-items:center;margin-top:8px"><input type="checkbox" name="send_whatsapp" value="1" checked> ابعت للعميل ملف PDF بكم استلم وكم باقي وكم دفع</label>
                        <textarea name="note" class="inp" rows="2" placeholder="ملاحظة تتكتب في الملف وتحته (اختياري)" style="margin-top:6px"></textarea>
                        <button class="btn green" style="margin-top:8px" onclick="return confirm('نسجلو الاستلام؟')">📦 سجل الاستلام</button>
                    </form>
                    @endif
                    @endcan
                    @if ($rHist->isNotEmpty())
                        <div style="margin-top:10px"><b style="font-size:13.5px">🗂 مرات الاستلام</b>
                        @foreach ($rHist as $rh)
                            <div class="ev" style="flex-wrap:wrap"><span class="note num">{{ \Carbon\Carbon::parse($rh->created_at)->format('m-d g:i a') }}</span>
                                <span>{{ collect(json_decode((string) $rh->lines, true) ?: [])->map(fn ($l) => $l['name'] . ' × ' . $l['n'])->implode('، ') ?: '—' }}</span>
                                @if ($rh->paid > 0)<span class="chip u">💵 {{ $f($rh->paid) }}</span>@endif
                                @if ($rh->sent)<span class="chip n">📨 وصلت الرسالة</span>@endif
                                <span class="note">{{ optional(\App\Models\User::find($rh->user_id))->name }}</span></div>
                        @endforeach
                        </div>
                    @endif
                </details>
            @endif
            <div style="display:flex;justify-content:space-between;border-top:1px solid var(--line);margin-top:6px;padding-top:10px"><span class="note">مجموع المنتجات</span><b class="num">{{ $f($order->total_price) }} د.ل</b></div>
            @if ($order->note)<div class="warnbox" style="margin-top:10px">📝 {{ $order->note }}</div>@endif
        </div>

        @if ($order->arrived)
            <div class="card mb">
                <h3>بيانات الوصول <span class="h-note">{{ optional($order->receivedUser)->name }} {{ $order->received_at ? '· ' . $order->received_at->format('Y-m-d g:i') : '' }}</span></h3>
                <div class="arr">
                    <div class="sh"><small>الرف</small><b class="num" style="font-size:24px">{{ $order->storage_num ?: '—' }}</b></div>
                    <div><small>القطع</small><b class="num">{{ $order->received_qty ?? $order->qty }}</b> <span class="note">من {{ $order->qty }}</span></div>
                    <div><small>الطرود</small><b class="num">{{ $order->bags_num ?: '—' }}</b></div>
                    <div><small>نوع الطرد</small><b>{{ $order->package_type ?: '—' }}</b></div>
                    <div><small>الوزن</small><b class="num">{{ $order->kilo ?: '—' }}</b> <span class="note">كيلو</span></div>
                </div>
            </div>
        @endif

        <div class="card">
            <h3>كل الذي صار <span class="h-note">ملاحظات وحركات ورسائل</span></h3>
            @forelse ($events as $e)
                <div class="ev"><span class="ic" style="background:{{ $e['bg'] }}">{{ $e['ic'] }}</span><div style="flex:1">{{ $e['x'] }}<small>{{ $e['t']->format('Y-m-d g:i') }} · {{ $e['by'] }}</small></div></div>
            @empty
                <div class="note">لا يوجد حركات</div>
            @endforelse
            <form method="post" action="{{ route('sg.orders.note', $order) }}" style="margin-top:10px">@csrf<div style="display:flex;gap:8px"><input class="inp" name="note" placeholder="اكتب ملاحظة على الإيصال…" required><button class="btn">إضافة</button></div><label class="ck" style="margin-top:8px"><input type="checkbox" name="to_customer" value="1"> 📤 ابعثها للعميل (واتساب + تظهر في حسابه)</label></form>
        </div>
    </div>

    <div>
        <div class="card dark money mb" id="pay">
            <h3>الحساب</h3>
            <div class="ln"><span>المنتجات</span><b class="num">{{ $f($order->total_price) }}</b></div>
            <div class="ln"><span>+ الشحن {{ $order->kilo ? '(' . $order->kilo . ' كيلو)' : '' }}</span><b class="num">{{ $f($order->ship_price) }}</b></div>
            @if ($order->discount > 0)<div class="ln"><span>− {{ $order->discount_reason ?: 'تخفيض' }}</span><b class="num" style="color:#5fd3a3">−{{ $f($order->discount) }}</b></div>@endif
            <div class="ln"><span>= الإجمالي</span><b class="num">{{ $f($order->grandTotal()) }}</b></div>
            <div class="ln"><span>− المدفوع</span><b class="num" style="color:#5fd3a3">{{ $f($order->paid_price) }}</b></div>
            <div class="rem" style="margin:14px 0 4px"><small>الباقي عليه</small><b class="num">{{ $f(max(0, $order->remaining())) }} <span style="font-size:16px;font-family:Somar;font-weight:400;color:#c9cde0">د.ل</span></b></div>
            @if ($hasMissing)
                <form method="post" action="{{ route('sg.orders.missing', $order) }}" class="missbox">
                    @csrf
                    <div style="color:#ffd27a;font-weight:700">⚠ {{ $missingQty }} قطع لم يصلوا</div>
                    <input type="hidden" name="missing_qty" value="{{ $missingQty }}">
                    <div style="display:flex;gap:6px;margin:8px 0;align-items:center"><span style="color:#c9cde0">قيمتهم</span><input class="inp numr" name="missing_value" value="{{ round($missingQty * $unit) }}" style="height:38px;width:110px"><span style="color:#c9cde0">د.ل</span></div>
                    <input type="hidden" name="payment_id" value="{{ \App\Services\AppSettings::cashPaymentId() }}">
                    <div style="display:flex;gap:6px;flex-wrap:wrap"><button name="action" value="deduct" class="btn sm" style="background:#fff;color:var(--brand)">خصم من الباقي</button><button name="action" value="credit" class="btn sm" style="background:rgba(255,255,255,.12)">رصيد للعميل</button><button name="action" value="refund" class="btn sm" style="background:rgba(255,255,255,.12)">إرجاع أموال</button></div>
                </form>
            @elseif ($order->missing_action)
                <div class="missbox" style="color:#ffd27a">قطع ناقصة: {{ $order->missing_qty }} بقيمة {{ $f($order->missing_value) }} · {{ ['deduct' => 'خُصمت من الباقي', 'credit' => 'أصبحت رصيداً للعميل', 'refund' => 'رجعنا المبلغ'][$order->missing_action] }}</div>
            @endif
            @if ($st !== 'cx' && $order->remaining() > 0.009)
                @can('order-edit')
                <form method="post" action="{{ route('sg.orders.pay', $order) }}" style="margin-top:12px">
                    @csrf
                    <div style="display:flex;gap:6px"><input class="inp numr" name="amount" value="{{ round($order->remaining(), 2) }}" style="height:44px"><select name="payment_id" class="inp" style="height:44px;width:140px">@foreach ($payments as $pm)<option value="{{ $pm->id }}">{{ $pm->name }}</option>@endforeach</select></div>
                    <label class="ck" style="color:#dfe2ef;margin-top:8px"><input type="checkbox" name="send_whatsapp" value="1" checked> أرسل له "استلمنا دفعتك"</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px">
                        <button class="btn" style="background:#fff;color:var(--brand)">دفع</button>
                        @if ($st === 'ready')<a class="btn green" href="{{ route('sg.delivery', ['q' => optional($c)->code]) }}">تسليم ←</a>@endif
                    </div>
                </form>
                <form method="post" action="{{ route('sg.orders.coupon', $order) }}" style="display:flex;gap:6px;margin-top:10px">@csrf<input name="code" class="inp" placeholder="🎟 كود كوبون" style="height:40px;direction:ltr;text-transform:uppercase"><button class="btn ghost sm" style="background:rgba(255,255,255,.12);color:#fff">تطبيق</button></form>
                @endcan
            @elseif ($st === 'ready')
                <a class="btn green block" style="margin-top:12px" href="{{ route('sg.delivery', ['q' => optional($c)->code]) }}">تسليم ←</a>
            @endif
        </div>

        <div class="card mb" id="pays">
            @php $paidSum = $order->paids->sum('paid_price'); $run = 0; @endphp
            <h3>الدفعات <span class="h-note">{{ $order->paids->count() }} دفعة · {{ $f($paidSum) }} د.ل</span></h3>
            @if ($order->paid_price - $paidSum > 0.009)
                <div class="ev"><span class="chip n">قبل</span><div style="flex:1"><b style="font-size:13.5px">مدفوع وقت تسجيل الإيصال / رصيد</b></div><b class="num plus">{{ $f($order->paid_price - $paidSum) }}</b></div>
                @php $run = $order->paid_price - $paidSum; @endphp
            @endif
            @forelse ($order->paids->sortBy('created_at') as $p)
                @php $run += $p->paid_price; $who = $p->receiverName(); @endphp
                <div class="ev" style="align-items:flex-start">
                    <span class="num" style="width:24px;height:24px;border-radius:50%;background:var(--up-bg);color:var(--up);display:grid;place-items:center;font-size:12px;font-weight:800;flex:none">{{ $loop->iteration }}</span>
                    <div style="flex:1">
                        <b style="font-size:13.5px">{{ $p->note ?: 'دفعة' }}</b> <span class="chip u" style="font-size:11px">{{ optional($p->paymentMethod)->name }}</span>
                        <div class="note"><span class="num">{{ $p->created_at->format('Y-m-d') }}</span> · الساعة <span class="num">{{ $p->created_at->format('g:i a') }}</span>{{ $who ? ' · قبضها ' . $who : '' }}</div>
                        <div class="note">الباقي بعدها: <b class="num">{{ $f(max(0, $order->grandTotal() - $run)) }}</b></div>
                    </div>
                    <div style="text-align:left"><b class="num plus">{{ $f($p->paid_price) }}</b>
                    @can('order-edit')@if (!str_contains((string) $p->note, 'مندوب'))
                    <details style="margin-top:4px"><summary class="note" style="cursor:pointer;color:var(--s1)">✎ تعديل</summary>
                        <form method="post" action="{{ route('sg.orders.pay.edit', [$order->id, $p->id]) }}" style="display:flex;flex-direction:column;gap:6px;margin-top:6px;min-width:170px" onsubmit="return confirm('نعدلو الدفعة؟ الخزينة تتعدل بالفرق')">@csrf
                            <input name="amount" class="inp numr" value="{{ (float) $p->paid_price }}" inputmode="decimal" required style="height:36px">
                            <select name="payment_id" class="inp" style="height:36px">@foreach (\App\Models\PaymentMethod::where('status', true)->get() as $pm)<option value="{{ $pm->id }}" @selected((int) $pm->id === (int) $p->payment_id)>{{ $pm->name }}</option>@endforeach</select>
                            <button class="btn sm">حفظ</button>
                            <span class="note" style="font-size:11.5px">اكتب 0 باش تحذفها</span>
                        </form>
                    </details>
                    @endif @endcan
                    </div>
                </div>
            @empty
                @if ($order->paid_price <= 0.009)<div class="note">لا يوجد دفعات</div>@endif
            @endforelse
        </div>

        @if ($order->kind === 'shipping')
            @php $og = \App\Services\AppSettings::origins()[$order->ship_origin] ?? []; @endphp
            <div class="card mb">
                <h3>📦 شحنة {{ $og['flag'] ?? '' }} {{ $og['name'] ?? '' }} <span class="h-note">{{ \App\Services\ShippingService::STAGES[\App\Services\ShippingService::stageOf($order)][0] }}</span></h3>
                <div class="ln"><span>رقم التتبع</span><b class="num">{{ $order->tracking_no ?: '—' }}</b></div>
                <div class="ln"><span>الطرود</span><b>{{ $order->bags_num }} {{ $order->package_type }}</b></div>
                <div class="ln"><span>الوزن</span><b class="num">{{ $order->kilo ? $f($order->kilo) . ' كغ' : '—' }}</b></div>
                <div class="ln"><span>سعر الكيلو</span><b class="num">{{ $f($order->usd_per_kg - $order->usd_discount) }}$ @if ($order->usd_discount > 0)<span class="note">(بعد تخفيض {{ $f($order->usd_discount) }}$)</span>@endif</b></div>
                @if (\App\Services\ShippingService::isDual($order))
                    @php $dp = \App\Services\ShippingService::dualPrices($order); @endphp
                    <div class="ln"><span>الشحن</span><b class="num">{{ $f($order->usd_amount) }}$</b></div>
                    <div class="ln"><span>💵 كاش @if ($order->ship_pay !== 'bank')<span class="chip u">المعتمد</span>@endif</span><b class="num">{{ $f($dp['cash']) }} د.ل</b></div>
                    <div class="ln"><span>🏦 مصرف @if ($order->ship_pay === 'bank')<span class="chip u">المعتمد</span>@endif</span><b class="num">{{ $f($dp['bank']) }} د.ل</b></div>
                @else
                    <div class="ln"><span>الشحن</span><b class="num">{{ $f($order->usd_amount) }}$ ≈ {{ $f($order->ship_price) }} د.ل</b></div>
                @endif
                @if ($order->ship_platform)<div class="ln"><span>المنصة</span><b>{{ $order->ship_platform }}</b></div>@endif
                @can('shipping-edit')<a class="btn ghost block mt" href="{{ route('sg.shipping.edit', $order) }}">✎ تعديل الشحنة</a>
                <button type="button" class="btn green block mt" data-ship-ready="{{ route('sg.shipping.ready', $order) }}" data-preview="{{ route('sg.shipping.ready.preview', $order) }}" data-code="{{ $order->code }}">{{ $order->arrived_notified_at ? '📩 ابعت «جاهز للاستلام» تاني' : '✅ جاهز للاستلام + رسالة' }}</button>
                @include('sg.shipping.ready-modal')@endcan
            </div>
        @endif

        @php $issues = \Illuminate\Support\Facades\Schema::hasTable('order_issues') ? \App\Models\OrderIssue::with(['creator', 'resolver'])->where('order_id', $order->id)->latest()->get() : collect(); @endphp
        @if ($issues->count())
            <div class="card mb" id="issues">
                <h3>⚠ المشاكل <span class="h-note">{{ $issues->where('status', 'open')->count() }} مفتوحة</span></h3>
                @foreach ($issues as $is)
                    <div class="ev" style="align-items:flex-start;flex-wrap:wrap">
                        <span class="chip {{ $is->status === 'open' ? 'w' : 'u' }}">{{ $is->status === 'open' ? 'مفتوحة' : 'محلولة' }}</span>
                        <div style="flex:1;min-width:160px"><b style="font-size:13.5px">{{ \App\Models\OrderIssue::TYPES[$is->type][1] ?? '' }} {{ $is->typeLabel() }}</b>@if ($is->qty) <span class="note">{{ $is->qty }} قطعة</span>@endif @if ($is->value) <b class="num">{{ $f($is->value) }}</b> <span class="note">د.ل</span>@endif
                            @if ($is->note)<div style="font-size:13px">{{ $is->note }}</div>@endif
                            <div class="note" style="font-size:12px">{{ optional($is->creator)->name }} · {{ $is->created_at->format('Y-m-d g:i a') }}@if ($is->resolution) · ✓ {{ $is->resolution }} ({{ optional($is->resolver)->name }})@endif</div></div>
                        @if ($is->status === 'open')@can('order-edit')
                            @php $dd = (int) request('deduct') === $is->id; @endphp<details style="width:100%" @if ($dd) open @endif><summary class="note" style="cursor:pointer">حل المشكلة…</summary>
                                <form method="post" action="{{ route('sg.issues.resolve', $is) }}" class="mt">@csrf
                                    <div class="grid g2"><input name="value" class="inp numr" placeholder="قيمة (اختياري)" value="{{ $dd && $is->value ? $is->value : '' }}" @if ($dd) autofocus @endif><select name="action" class="inp">@foreach (\App\Models\OrderIssue::ACTIONS as $k => $v)<option value="{{ $k }}" @selected($dd && $k === 'deduct')>{{ $v }}</option>@endforeach</select></div>
                                    <select name="payment_id" class="inp mt">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == \App\Services\AppSettings::cashPaymentId())>للإرجاع النقدي: {{ $pm->name }}</option>@endforeach</select>
                                    <input name="resolution" class="inp mt" placeholder="كيف حُلّت؟ (مثلاً: وصلت مع الرحلة التالية)">
                                    <label class="ck mt"><input type="checkbox" name="send_whatsapp" value="1" checked> إبلاغ العميل</label>
                                    <button class="btn green sm mt">✓ حُلّت</button>
                                </form></details>
                        @endcan @endif
                    </div>
                @endforeach
            </div>
        @endif
        @if ($order->kind === 'card_sale')
            <div class="card mb">
                <h3>💳 بطاقات SHEIN المباعة <span class="h-note">{{ $soldCards->count() }} · {{ $f($soldCards->sum('face_value')) }} $</span></h3>
                @foreach ($soldCards as $sc)
                    @php $cu = \App\Services\CardSaleService::costUsd($sc, (float) $sc->sale_rate ?: null); @endphp
                    <div class="ln" style="align-items:flex-start"><span class="num">{{ $sc->masked() }} · {{ $f($sc->face_value) }}$</span>
                        <div style="text-align:left;font-size:13px"><div>بيع <b class="num">{{ $f($sc->sale_price) }} د.ل</b>{{ $sc->sale_usd ? ' ≈ ' . $f($sc->sale_usd) . ' $' : '' }}</div>
                        @can('profit-view')<div class="note">تكلفة {{ $f($sc->cost_dinar) }} د.ل{{ $cu !== null ? ' ≈ ' . $f($cu) . ' $' : '' }}</div>
                        <div class="{{ $sc->sale_price >= $sc->cost_dinar ? 'plus' : 'minus' }}">ربح {{ $f($sc->sale_price - $sc->cost_dinar) }} د.ل{{ $sc->sale_usd && $cu !== null ? ' ≈ ' . $f($sc->sale_usd - $cu) . ' $' : '' }}</div>@endcan</div></div>
                @endforeach
                <div class="note mt">{{ $soldCards->max('sent_at') ? '📨 أُرسلت للعميل ' . \Carbon\Carbon::parse($soldCards->max('sent_at'))->diffForHumans() : '⚠ لم تُرسل للعميل بعد' }}</div>
                @can('shein-card-sell')
                <div style="display:flex;gap:8px;flex-wrap:wrap" class="mt">
                    <form method="post" action="{{ route('sg.cards.resend', $order) }}">@csrf<button class="btn ghost sm">📨 {{ $soldCards->max('sent_at') ? 'إعادة الإرسال' : 'إرسال عبر واتساب' }}</button></form>
                    @can('shein-card-pin')<button type="button" class="btn ghost sm" id="cardCopy" data-url="{{ route('sg.cards.reveal', $order) }}">📋 نسخ البطاقة (للإرسال يدوياً)</button>@endcan
                </div>
                <script>
                (function () { const b = document.getElementById('cardCopy'); if (!b) return;
                  b.addEventListener('click', async () => { const r = await fetch(b.dataset.url, { headers: { 'Accept': 'application/json' } }); if (!r.ok) return alert('غير مسموح');
                    const t = (await r.json()).text; try { await navigator.clipboard.writeText(t); b.textContent = '✓ نُسخت'; } catch (e) { prompt('انسخ النص:', t); }
                    const wa = @json('https://wa.me/' . \App\Services\MessageService::normalize((string) optional($c)->phone_1)); window.open(wa + '?text=' + encodeURIComponent(t), '_blank'); });
                })();
                </script>
                @endcan
            </div>
        @endif
        @if ($replacedFrom)
            <div class="hint mb">⇄ هذا الإيصال بديل لمنتج نفد («{{ $replacedFrom->item }}») من الإيصال <a class="code" href="{{ route('sg.orders.show', $replacedFrom->order_id) }}">{{ optional($replacedFrom->order)->code }}</a></div>
        @endif
        @if ($soldouts->count())
            @php $pendSold = $soldouts->where('status', 'pending'); @endphp
            <div class="card mb" id="sold">
                <h3>🚫 منتجات نفدت <span class="h-note">{{ $soldouts->count() }} · {{ $f($soldouts->sum('price')) }} د.ل</span></h3>
                @foreach ($soldouts as $so)
                    <div class="ev" style="align-items:flex-start;flex-wrap:wrap">
                        <span class="chip {{ \App\Models\OrderSoldout::STATUSES[$so->status][1] }}">{{ \App\Models\OrderSoldout::STATUSES[$so->status][0] }}</span>
                        <div style="flex:1;min-width:160px"><b style="font-size:13.5px">{{ $so->item }}</b>@if ($so->qty > 1) <span class="note">× {{ $so->qty }}</span>@endif <b class="num">{{ $f($so->price) }}</b> <span class="note">د.ل</span>
                            <div class="note" style="font-size:12px">{{ $so->notified_at ? '📨 أُبلغ العميل ' . $so->notified_at->diffForHumans() : 'لم يُبلَّغ العميل بعد' }}
                                @if ($so->status === 'replaced') · البديل: {{ $so->replacement }} ({{ $f($so->replacement_price) }}) في <a class="code" href="{{ route('sg.orders.show', $so->new_order_id) }}">{{ optional($so->newOrder)->code }}</a>@endif
                                @if ($so->credited > 0) · رصيد للعميل {{ $f($so->credited) }}@endif @if ($so->refunded > 0) · أُرجع {{ $f($so->refunded) }}@endif
                                @if ($so->decided_at) · {{ optional($so->decider)->name }} {{ $so->decided_at->format('Y-m-d') }}@endif</div>
                        </div>
                        @if ($so->status === 'pending')
                            @can('soldout-manage')<button class="btn ghost sm" data-modal="mDec" data-sold="{{ json_encode(['id' => $so->id, 'item' => $so->item, 'qty' => $so->qty, 'price' => (float) $so->price, 'url' => route('sg.soldouts.decide', $so->id)], JSON_UNESCAPED_UNICODE) }}">قرار العميل</button>@endcan
                        @endif
                    </div>
                @endforeach
                @if ($pendSold->count())
                    @can('soldout-manage')<form method="post" action="{{ route('sg.soldouts.notify', $order) }}" class="mt">@csrf<button class="btn ghost sm block">📨 {{ $pendSold->whereNotNull('notified_at')->count() ? 'إعادة إبلاغ العميل' : 'إبلاغ العميل عبر واتساب' }} ({{ $pendSold->count() }})</button></form>@endcan
                @endif
            </div>
        @endif
        @if ($order->returns->count())
            <div class="card mb">
                <h3>↩ طلبيات مسترجعة <span class="h-note">{{ $order->returns->count() }}</span></h3>
                @foreach ($order->returns as $r)
                    <div class="ev" style="align-items:flex-start"><span class="chip {{ $r->type === 'exchange' ? 'n' : 'd' }}">{{ $r->typeLabel() }}</span><div style="flex:1"><b style="font-size:13.5px">{{ $r->items ?: '—' }}</b>@if ($r->qty) <span class="note">({{ $r->qty }} قطعة)</span>@endif<div class="note">{{ $r->methodLabel() }}{{ $r->refunded > 0 ? ' · ' . $f($r->refunded) . ' من ' . optional($r->payment)->name : '' }}{{ $r->new_items ? ' · بدالها: ' . $r->new_items : '' }}</div><small>{{ $r->created_at->format('Y-m-d g:i') }} · {{ optional($r->createdUser)->name }}</small></div><b class="num minus">{{ $f($r->amount) }}</b></div>
                @endforeach
            </div>
        @endif

        @if ($trip)
            @php $ts = $trip->summary(); @endphp
            <div class="card">
                <h3>الرحلة <span class="code" style="font-size:14px">{{ $trip->trip_code }}</span></h3>
                <div class="ln"><span>إيصالات في الرحلة</span><b class="num">{{ $ts['orders'] }}</b></div>
                <div class="ln"><span>الحالة</span><span class="chip n">{{ $ts['status'] ?: '—' }}</span></div>
                @can('profit-view')<div class="ln"><span>ربح الرحلة</span><b class="num plus">{{ $f($ts['profit_dinar']) }}</b></div>@endcan
                <a class="btn ghost block mt" href="{{ route('finance.trips.show', $trip->id) }}">فتح الرحلة ←</a>
            </div>
        @endif
        @php $cardUses = \Illuminate\Support\Facades\Schema::hasTable('shein_card_uses') ? \App\Models\SheinCardUse::with('card')->where('order_id', $order->id)->get() : collect(); @endphp
        @if ($cardUses->count())
            <div class="card mt">
                <h3>💳 بطاقات SHEIN على الإيصال</h3>
                @foreach ($cardUses as $u)<div class="ln"><a class="num" href="{{ route('sg.shein', ['id' => $u->card_id]) }}">{{ optional($u->card)->masked() }}</a><b class="num">{{ $f($u->amount) }} $</b></div>@endforeach
            </div>
        @endif
    </div>
</div>
@can('order-delete')
<div class="modal-bg" id="mDel"><div class="modal" style="max-width:480px">
    <h3>حذف الإيصال <span class="code">{{ $order->code }}</span> نهائي</h3>
    <form method="post" action="{{ route('sg.orders.destroy', $order) }}" id="delF">
        @csrf @method('DELETE')
        <div class="hint mb" style="background:var(--down-bg);color:var(--down)">سيُحذف الإيصال ومنتجاته ودفعاته نهائياً. يُسجَّل في سجل التعديلات من حذفه.@if ($order->delivered)<br><b>⚠ الإيصال هذا متسلّم.</b>@endif</div>
        @if ($order->paid_price > 0.009 && $st !== 'cx')
            <label class="l">العميل دافع <b class="num">{{ $f($order->paid_price) }}</b> د.ل — ماذا نفعل بالمبلغ؟</label>
            <div class="opts mb" style="flex-direction:column">
                <label><input type="radio" name="refund" value="cash" checked><span>💵 إرجاع المبلغ له (يُصرف من الخزينة)</span></label>
                <label><input type="radio" name="refund" value="credit"><span>🎟 تصبح رصيداً لديه للطلبية القادمة</span></label>
                <label><input type="radio" name="refund" value="keep"><span>🔒 تبقى في الخزينة (لا تُسترد)</span></label>
            </div>
            <div class="f" id="delPay"><label class="l">تُصرف من خزينة</label><select name="payment_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == \App\Services\AppSettings::cashPaymentId())>{{ $pm->name }}</option>@endforeach</select></div>
        @else
            <input type="hidden" name="refund" value="keep">
        @endif
        <div class="f"><label class="l">السبب (اختياري)</label><input name="reason" class="inp" placeholder="مثلاً: العميل ألغى الطلبية"></div>
        <div style="display:flex;gap:8px"><button class="btn red">حذف نهائي</button><button type="button" class="btn ghost" data-close>رجوع</button></div>
    </form>
</div></div>
<script>
(function () { const f = document.getElementById('delF'); if (!f) return; const box = document.getElementById('delPay');
  const upd = () => { const r = f.querySelector('input[name=refund]:checked'); if (box) box.hidden = !r || r.value !== 'cash'; };
  f.querySelectorAll('input[name=refund]').forEach(i => i.addEventListener('change', upd)); upd();
  f.addEventListener('submit', e => { if (!confirm('متأكد؟ الحذف نهائي')) e.preventDefault(); });
  if (location.hash === '#del') { const b = document.querySelector('[data-modal=mDel]'); if (b) setTimeout(() => b.click(), 50); }
})();
</script>
@endcan
@can('soldout-manage')
<div class="modal-bg" id="mSold"><div class="modal" style="max-width:620px">
    <h3>🚫 منتجات نفدت أثناء الشراء · <span class="code">{{ $order->code }}</span></h3>
    <div class="note mb">سجّل المنتجات التي وجدتها نفدت بسعرها، ويصل العميل رسالة بها ليرد: إلغاء (تُخصم قيمتها) أو استبدال (إيصال جديد).</div>
    <form method="post" action="{{ route('sg.soldouts.store', $order) }}" id="soldF">
        @csrf
        <div id="soldRows"></div>
        <button type="button" class="btn ghost sm" id="soldAdd">+ منتج آخر</button>
        <datalist id="soldItems">@foreach ($order->items as $it)<option value="{{ $it->name }}" data-p="{{ $it->quantity ? round($it->price / $it->quantity, 2) : $it->price }}">@endforeach</datalist>
        <div class="hint mt">المجموع: <b class="num" id="soldSum">0</b> د.ل · قيمة الإيصال {{ $f($order->grandTotal()) }} · المدفوع {{ $f($order->paid_price) }}</div>
        <label class="ck mt"><input type="checkbox" name="send_whatsapp" value="1" checked> إبلاغ العميل الآن عبر واتساب بالمنتجات وأسعارها</label>
        <div style="display:flex;gap:8px;margin-top:10px"><button class="btn">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
<div class="modal-bg" id="mDec"><div class="modal" style="max-width:520px">
    <h3>قرار العميل · <span id="decItem"></span></h3>
    <form method="post" id="decF">
        @csrf
        <div class="opts mb"><label><input type="radio" name="action" value="cancel" checked><span>✕ إلغاء وخصم القيمة</span></label><label><input type="radio" name="action" value="replace"><span>⇄ استبدال بمنتج آخر</span></label></div>
        <div id="repBox" hidden>
            <div class="f"><label class="l">المنتج البديل (أو رابطه)</label><input name="replacement" class="inp"></div>
            <div class="grid g2 f"><div><label class="l">السعر (د.ل)</label><input name="replacement_price" class="inp numr" inputmode="decimal"></div><div><label class="l">الكمية</label><input name="qty" class="inp numr" value="1"></div></div>
            <div class="note mb">يُنشأ إيصال جديد بالمنتج البديل لأنه سيُشترى وحده.</div>
        </div>
        @php $over = max(0, (float) $order->paid_price - $order->grandTotal()); @endphp
        <div id="excBox">
            <label class="l">إذا أصبح المدفوع أكثر من قيمة الإيصال، المبلغ الزائد:</label>
            <div class="opts mb"><label><input type="radio" name="excess" value="credit" checked><span>🎟 رصيد للعميل</span></label><label><input type="radio" name="excess" value="cash"><span>💵 يُرجع نقداً</span></label></div>
            <div class="f" id="decPay" hidden><label class="l">تُصرف من خزينة</label><select name="payment_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == \App\Services\AppSettings::cashPaymentId())>{{ $pm->name }}</option>@endforeach</select></div>
        </div>
        <label class="ck mb"><input type="checkbox" name="send_whatsapp" value="1" checked> إرسال تأكيد للعميل عبر واتساب</label>
        <div style="display:flex;gap:8px"><button class="btn">تأكيد</button><button type="button" class="btn ghost" data-close>رجوع</button></div>
    </form>
</div></div>
<script>
(function () {
  const box = document.getElementById('soldRows'); if (!box) return; let n = 0;
  const opts = [...document.querySelectorAll('#soldItems option')];
  function sum() { let t = 0; box.querySelectorAll('.sp').forEach(i => t += parseFloat(i.value) || 0); document.getElementById('soldSum').textContent = SG.fmt(t); }
  function add() { const i = n++; const d = document.createElement('div'); d.className = 'grid f'; d.style.cssText = 'grid-template-columns:1fr 70px 110px 28px;gap:6px';
    d.innerHTML = `<input class="inp si" name="rows[${i}][item]" list="soldItems" placeholder="المنتج (مثلاً: فستان أسود M)"><input class="inp numr sq" name="rows[${i}][qty]" value="1" inputmode="numeric"><input class="inp numr sp" name="rows[${i}][price]" placeholder="القيمة" inputmode="decimal"><button type="button" style="border:0;background:none;color:var(--down);font-size:18px;cursor:pointer">×</button>`;
    const si = d.querySelector('.si'), sq = d.querySelector('.sq'), sp = d.querySelector('.sp');
    const auto = () => { const o = opts.find(o => o.value === si.value); if (o && !sp.dataset.t) { sp.value = Math.round((+o.dataset.p) * (+sq.value || 1) * 100) / 100; sum(); } };
    si.addEventListener('change', auto); sq.addEventListener('input', auto); sp.addEventListener('input', () => { sp.dataset.t = 1; sum(); });
    d.querySelector('button').onclick = () => { d.remove(); sum(); }; box.appendChild(d); }
  document.getElementById('soldAdd').onclick = add; add();
  const f = document.getElementById('decF');
  document.querySelectorAll('[data-modal=mDec]').forEach(b => b.addEventListener('click', () => { const d = JSON.parse(b.dataset.sold); f.action = d.url;
    document.getElementById('decItem').textContent = d.item + ' (' + SG.fmt(d.price) + ' د.ل)'; f.querySelector('[name=qty]').value = d.qty; }));
  const upd = () => { const rep = f.querySelector('[name=action]:checked').value === 'replace'; document.getElementById('repBox').hidden = !rep;
    f.querySelector('[name=replacement]').required = rep; f.querySelector('[name=replacement_price]').required = rep;
    document.getElementById('decPay').hidden = f.querySelector('[name=excess]:checked').value !== 'cash'; };
  f.querySelectorAll('[name=action],[name=excess]').forEach(i => i.addEventListener('change', upd)); upd();
})();
</script>
@endcan
@can('return-create')
<div class="modal-bg" id="mRet"><div class="modal" style="max-width:560px">
    <h3>مرتجع / استبدال · <span class="code">{{ $order->code }}</span></h3>
    <form method="post" action="{{ route('sg.returns.store', $order) }}" id="retF">
        @csrf
        <div class="opts mb"><label><input type="radio" name="type" value="return" checked><span>↩ مرتجع</span></label><label><input type="radio" name="type" value="exchange"><span>⇄ استبدال</span></label></div>
        <div class="grid g2 f"><div><label class="l">القطعة المرتجعة</label><input name="items" class="inp" placeholder="مثلا: فستان أسود مقاس M" list="retItems"></div><div><label class="l">عدد القطع</label><input name="qty" class="inp numr" value="1"></div></div>
        <datalist id="retItems">@foreach ($order->items as $it)<option value="{{ $it->name }}">@endforeach</datalist>
        <div class="f"><label class="l">قيمة المرتجع (د.ل)</label><input name="amount" id="rAmt" class="inp numr" value="0"><div class="note">الإيصال: {{ $f($order->grandTotal()) }} · دفع {{ $f($order->paid_price) }} · باقي {{ $f(max(0, $order->remaining())) }}</div></div>
        <div id="exBox" hidden>
            <div class="grid g2 f"><div><label class="l">البديل الذي أخذه</label><input name="new_items" class="inp" placeholder="القطعة الجديدة"></div><div><label class="l">إذا الجديدة أغلى: الفرق (د.ل)</label><input name="extra" class="inp numr" value="0"></div></div>
        </div>
        <label class="l">المبلغ</label>
        <div class="opts mb" style="flex-wrap:wrap">
            @foreach (\App\Models\OrderReturn::METHODS as $k => $v)<label><input type="radio" name="method" value="{{ $k }}" @checked($k === ($order->remaining() > 0.009 ? 'deduct' : 'cash'))><span>{{ $v }}</span></label>@endforeach
        </div>
        <div class="f" id="payBox"><label class="l">تُصرف من خزينة</label><select name="payment_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == \App\Services\AppSettings::cashPaymentId())>{{ $pm->name }}</option>@endforeach</select><div class="note">يُصرف من الخزينة فقط الزيادة التي دفعها فوق قيمة الإيصال الجديدة</div></div>
        <div class="grid g2 f">
            <div><label class="l">مصير البضاعة</label><select name="goods" class="inp">@foreach (\App\Models\OrderReturn::GOODS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div><label class="l">السبب</label><input name="reason" class="inp" list="retWhy" placeholder="مقاس خطأ…"></div>
        </div>
        <datalist id="retWhy"><option value="مقاس خطأ"><option value="لون خطأ"><option value="تالفة / فيها عيب"><option value="مختلفة عن الصورة"><option value="العميل غيّر رأيه"></datalist>
        <label class="ck mb"><input type="checkbox" name="send_whatsapp" value="1" checked> إرسال للعميل واتساب بالمرتجع</label>
        <div style="display:flex;gap:8px"><button class="btn">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
<script>
(function () {
  const f = document.getElementById('retF'); if (!f) return;
  const upd = () => { const ex = f.querySelector('input[name=type]:checked').value === 'exchange', m = f.querySelector('input[name=method]:checked').value; document.getElementById('exBox').hidden = !ex; document.getElementById('payBox').hidden = m !== 'cash'; };
  f.querySelectorAll('input[name=type],input[name=method]').forEach(i => i.addEventListener('change', upd)); upd();
})();
</script>
@endcan
@include('sg.partials.issue-modal')
@include('sg.partials.missing-modal')
<script>if (location.hash === '#del' && document.getElementById('mDel')) document.getElementById('mDel').classList.add('open');</script>
@if (session('print_url'))
<script>window.open(@json(session('print_url')), '_blank');</script>
@endif
@include('sg.partials.undo-delivery')
@endsection
