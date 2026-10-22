@php
    use App\Services\ShippingService as SS;
    $f = fn ($n) => \App\Services\TreasuryService::fmt($n);
    $credit = (float) $c->credit;
    $pts = (int) $c->points;
    $first = explode(' ', trim($c->name))[0];
    $stageOf = function ($o) {
        if ($o->kind === 'shipping') { $k = SS::stageOf($o); return [SS::STAGES[$k][0], ['expected' => 'c-new', 'warehouse' => 'c-new', 'transit' => 'c-way', 'arrived' => 'c-ok', 'courier' => 'c-wait', 'delivered' => 'c-ok'][$k] ?? 'c-new', ['expected' => 1, 'warehouse' => 1, 'transit' => 2, 'arrived' => 4, 'courier' => 4, 'delivered' => 5][$k] ?? 1]; }
        if ($o->delivered) return ['تم التسليم', 'c-ok', 5];
        if ($o->delivery_id) return ['مع المندوب', 'c-wait', 4];
        if ($o->arrived) return ['وصلت · جاهزة للاستلام', 'c-ok', 4];
        if ($o->no_trip ?? false) return ['سيصل قريباً', 'c-way', 2]; // بدون رحلة: ما نعرضوش حالة الرحلة
        if ($o->trip_details) return ['في الطريق', 'c-way', 2];
        return ['قيد التجهيز', 'c-new', 1];
    };
    $wantOrder = request('order');
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8">

<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#262c4a">
<link rel="manifest" href="data:application/manifest+json,{{ rawurlencode(json_encode(['name' => $settings['shop_name'], 'short_name' => $settings['shop_name'], 'start_url' => route('customer.dashboard'), 'display' => 'standalone', 'background_color' => '#f4f5f8', 'theme_color' => '#262c4a', 'lang' => 'ar', 'dir' => 'rtl'])) }}">
<link rel="apple-touch-icon" href="{{ asset('assets/sg/logo.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>حسابي · {{ $settings['shop_name'] }}</title>
<link rel="stylesheet" href="{{ asset('assets/sg/app.css') }}?v=6">
<style>
.abox { background: var(--line-soft); border: 1.5px dashed #c9cdd8; border-radius: 14px; padding: 12px 14px; font-family: InterNum, Somar; font-size: 14px; line-height: 1.75; direction: ltr; text-align: left; white-space: pre-line; color: var(--ink); }
.abox b { color: var(--down); background: #fff; padding: 0 6px; border-radius: 6px; }
.addr .h > span:first-child { font-size: 24px; }
</style>
</head>
<body>
@include('partials.back', ['home' => route('customer.dashboard'), 'float' => true])
@include('app.icons')
<div class="app">
<div class="body">
@if (session('success'))<div class="flash ok">✓ {{ session('success') }}</div>@endif
@if (session('error'))<div class="flash err">{{ session('error') }}</div>@endif
@if (session('warning'))<div class="flash warn">{{ session('warning') }}</div>@endif
@if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif

{{-- ========================= الرئيسية ========================= --}}
@if ($tab === 'home')
    <div class="hd"><div class="hello"><div class="av">{{ mb_substr($c->name, 0, 1) }}</div><div><div class="sub" style="margin:0">مرحباً 👋 · كودك <b class="num" style="color:var(--ink)">{{ $c->code }}</b></div><h1 style="font-size:20px">{{ $c->name }}</h1></div></div>
        <a class="icb" href="?tab=account"><svg width="21" height="21"><use href="#i-bell"/></svg>@if ($unread)<span class="dot"></span>@endif</a></div>
    <div class="money3">
        <a href="?tab=account"><small>المستحق عليك</small><b style="color:var(--down)">{{ $f($due) }}</b><i>د.ل · {{ $debts->count() }} طلبية</i></a>
        <a href="?tab=account"><small>رصيدك لدينا</small><b style="color:var(--s1)">{{ $f($credit) }}</b><i>د.ل</i></a>
        <a href="?tab=account"><small>نقاطك</small><b style="color:var(--s7)">{{ $pts }}</b><i>{{ $pointsOn ? '= ' . $f($pts * $pointValue) . ' د.ل' : '' }}</i></a>
    </div>
    <div class="qa">
        <a href="?tab=delivery"><span style="background:var(--up-bg);color:var(--up)">🚚</span>طلب توصيل</a>
        <a href="?tab=problem"><span style="background:var(--down-bg);color:var(--down)">⚠</span>لدي مشكلة</a>
        <a href="?tab=addresses"><span style="background:var(--s1-bg)">📍</span>عناويننا</a>
        <a href="?tab=prices"><span style="background:var(--warn-bg)">💲</span>الأسعار</a>
    </div>
    @foreach ($offers as $of)
        <div class="note-c" style="background:linear-gradient(135deg,#fff4e0,#ffe9f0);color:#6b3a00;border:1px solid #f5d9b0">
            <b>{{ \App\Models\Offer::KINDS[$of->kind][1] ?? '🎁' }} {{ $of->title }}</b>@if ($of->discountLabel()) · {{ $of->discountLabel() }}@endif
            @if ($of->body)<div style="margin-top:4px;font-size:13px">{{ $of->body }}</div>@endif
            @if ($of->coupon_code)<div style="margin-top:6px">الكود: <b class="num" style="letter-spacing:1px">{{ $of->coupon_code }}</b> <button type="button" class="cpy" onclick="navigator.clipboard && navigator.clipboard.writeText('{{ $of->coupon_code }}');this.textContent='✓'">نسخ</button>@if ($of->ends_at) <span style="font-size:12px">· حتى {{ $of->ends_at->format('Y-m-d') }}</span>@endif</div>@endif
        </div>
    @endforeach
    @if ($ready->count())
        <a class="alert" href="?tab=delivery" style="background:var(--up-bg);color:var(--up)"><svg width="22" height="22"><use href="#i-check"/></svg><div class="tx"><b>وصلت بضاعتك 🎉</b>{{ $ready->pluck('code')->implode('، ') }} في الرف · يمكنك طلب التوصيل</div><svg width="18" height="18"><use href="#i-chev"/></svg></a>
    @endif
    @foreach ($feed->take(2) as $m)
        <div class="note-c" style="background:var(--s1-bg);color:#1d4f8f">💬 {{ $m->body }} @if ($m->code)<b class="num">· {{ $m->code }}</b>@endif<div style="font-size:11.5px;opacity:.8;margin-top:2px">{{ $m->t->diffForHumans() }}</div></div>
    @endforeach
    <a href="{{ route('portal.carts') }}" style="display:flex;align-items:center;gap:12px;background:#262c4a;color:#fff;border-radius:18px;padding:16px;margin:12px 0;text-decoration:none"><span style="font-size:28px">🛒</span><span style="flex:1"><b style="font-size:16px">احسب سلتك واطلب</b><br><small style="color:#c9cde0">الصق رابط سلة SHEIN أو iHerb أو Amazon، وشوف السعر طول</small></span><span>←</span></a>
    @foreach ($offers as $of)
        <div style="background:linear-gradient(135deg,#eb6834,#f59e0b);color:#fff;border-radius:18px;padding:14px 16px;margin-bottom:10px">
            <b style="font-size:15px">🎁 {{ $of->title }}</b>@if ($of->body)<div style="font-size:13px;opacity:.95;margin-top:4px;white-space:pre-line">{{ $of->body }}</div>@endif
            <div style="font-size:12px;margin-top:6px;opacity:.9">@if ($of->coupon_code)كود: <b class="num" style="background:#fff;color:#b5480f;padding:1px 8px;border-radius:6px">{{ $of->coupon_code }}</b> @endif @if ($of->ends_at)· لين {{ $of->ends_at->format('Y-m-d') }}@endif</div>
        </div>
    @endforeach
    @if (\Illuminate\Support\Facades\Schema::hasTable('push_subscriptions'))
    <div id="pushBox" style="display:none;background:#fff;border:1.5px dashed #c9cdd8;border-radius:16px;padding:12px 14px;margin-bottom:10px;gap:10px;align-items:center">
        <span style="font-size:24px">🔔</span><span style="flex:1;font-size:13.5px" id="pushTxt">فعّل الإشعارات على جوالك باش يوصلك خبر وصول طلبيتك وتأكيد حجزك طول</span>
        <button type="button" class="btn" id="pushBtn" style="height:40px;padding:0 14px">تفعيل</button>
    </div>
    <script>
    (function () {
      const box = document.getElementById('pushBox'), btn = document.getElementById('pushBtn'), txt = document.getElementById('pushTxt');
      const ios = /iphone|ipad|ipod/i.test(navigator.userAgent), standalone = window.navigator.standalone || matchMedia('(display-mode: standalone)').matches;
      if (!('serviceWorker' in navigator)) return;
      if (ios && !standalone) { box.style.display = 'flex'; txt.innerHTML = 'باش توصلك الإشعارات على الآيفون: اضغط زر المشاركة <b>⬆</b> ← <b>إضافة إلى الشاشة الرئيسية</b>، وافتح الحساب من الأيقونة.'; btn.style.display = 'none'; return; }
      if (!('PushManager' in window) || !('Notification' in window) || Notification.permission === 'denied') return;
      const key = @json(\App\Services\PushService::publicKey());
      const u8 = b => { const p = '='.repeat((4 - b.length % 4) % 4), s = atob((b + p).replace(/-/g, '+').replace(/_/g, '/')); return Uint8Array.from([...s].map(c => c.charCodeAt(0))); };
      async function sub() {
        const reg = await navigator.serviceWorker.register(@json(route('portal.sw', [], false)), { scope: '/' });
        await navigator.serviceWorker.ready;
        let s = await reg.pushManager.getSubscription();
        if (!s) s = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: u8(key) });
        await fetch(@json(route('portal.push.subscribe')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }, body: JSON.stringify(s) });
      }
      if (Notification.permission === 'granted') { sub().catch(() => {}); return; }
      box.style.display = 'flex';
      btn.onclick = async () => { btn.disabled = true; try { if (await Notification.requestPermission() === 'granted') { await sub(); txt.textContent = '✓ تفعلت الإشعارات'; btn.style.display = 'none'; setTimeout(() => box.style.display = 'none', 2500); } else { txt.textContent = 'ما سمحتش بالإشعارات — تقدر تفعلها بعدين من إعدادات المتصفح'; } } catch (e) { txt.textContent = 'ما قدرناش نفعلوها على الجهاز هذا'; } btn.disabled = false; };
    })();
    </script>
    @endif
    @php $lv = \App\Services\LoyaltyService::info($c); @endphp
    @if ($lv)
        <div style="background:#fff;border-radius:18px;padding:14px 16px;margin-bottom:10px;border:1.5px solid {{ $lv['tier'][5] ?? '#e3e6ee' }}">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <b style="font-size:15px">{{ $lv['tier'] ? $lv['tier'][2] . ' عميل ' . $lv['tier'][1] : '🏅 مستويات العملاء' }}</b>
                @if ($lv['discount'] > 0)<span style="font-size:12px;background:#e6f6ee;color:#0f6b4c;padding:3px 10px;border-radius:99px">خصم الشحن <b class="num">{{ rtrim(rtrim(number_format($lv['discount'], 2), '0'), '.') }}$</b> للكيلو</span>@endif
            </div>
            @if ($lv['next'])
                <div style="height:8px;background:#eef0f4;border-radius:8px;overflow:hidden;margin:10px 0 6px"><div style="height:100%;width:{{ $lv['progress'] }}%;background:{{ $lv['next'][5] }}"></div></div>
                <div class="lbl">باقيلك <b class="num">{{ $f($lv['left']) }}</b> د.ل وتولّي {{ $lv['next'][2] }} <b>{{ $lv['next'][1] }}</b> — خصم <b class="num">{{ rtrim(rtrim(number_format($lv['next'][4], 2), '0'), '.') }}$</b> على كل كيلو شحن</div>
            @else
                <div class="lbl" style="margin-top:6px">وصلت لأعلى مستوى 🎉 شكراً على ثقتك</div>
            @endif
        </div>
    @endif
    @if ($credit > 0.009)<div class="note-c" style="background:var(--s1-bg);color:#1d4f8f">💳 رصيدك عندنا <b class="num">{{ $f($credit) }}</b> د.ل — ينخصم من طلبيتك الجاية</div>@endif
    @foreach ($toRateDl as $rd)
        <form method="post" action="{{ route('portal.rate.delivery', $rd->id) }}" class="card" style="padding:12px 14px;margin-bottom:10px">@csrf
            <b style="font-size:14px">🛵 كيف كان المندوب {{ optional($rd->courier)->name }}؟ <span class="lbl num">{{ $rd->code }}</span></b>
            <div class="stars" style="display:flex;gap:6px;flex-direction:row-reverse;justify-content:flex-end;margin:8px 0">@for ($k = 5; $k >= 1; $k--)<label style="font-size:28px;cursor:pointer"><input type="radio" name="rating" value="{{ $k }}" required style="display:none" onchange="[...this.form.querySelectorAll('.stars label')].forEach(l=>l.style.color=+l.querySelector('input').value<={{ $k }}?'#f5a524':'#cfd3dc')"><span>★</span></label>@endfor</div>
            <div style="display:flex;gap:6px"><input name="note" class="inp" style="height:40px;flex:1" placeholder="ملاحظات على التوصيل (اختياري)"><button class="btn" style="height:40px">إرسال</button></div>
        </form>
    @endforeach
    @foreach ($toRate as $ro)
        <form method="post" action="{{ route('portal.rate', $ro->id) }}" class="card" style="padding:12px 14px;margin-bottom:10px">@csrf
            <b style="font-size:14px">كيف كانت طلبيتك <span class="num">{{ $ro->code }}</span>؟</b>
            <div class="stars" style="display:flex;gap:6px;flex-direction:row-reverse;justify-content:flex-end;margin:8px 0">@for ($k = 5; $k >= 1; $k--)<label style="font-size:28px;cursor:pointer"><input type="radio" name="rating" value="{{ $k }}" required style="display:none" onchange="[...this.form.querySelectorAll('.stars label')].forEach(l=>l.style.color=+l.querySelector('input').value<={{ $k }}?'#f5a524':'#cfd3dc')"><span>★</span></label>@endfor</div>
            <div style="display:flex;gap:6px"><input name="note" class="inp" style="height:40px;flex:1" placeholder="ملاحظة (اختياري)"><button class="btn" style="height:40px">إرسال</button></div>
        </form>
    @endforeach
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:12px">
        @foreach ([['calc', '🧮', 'حاسبة الشحن'], ['returns', '↩', 'مرتجعاتي'], ['invite', '🎁', 'ادعُ صديق'], ['faq', '❓', 'الأسئلة']] as [$k, $ic, $t])
            <a href="?tab={{ $k }}" style="background:#fff;border-radius:14px;padding:10px 4px;text-align:center;text-decoration:none;color:inherit;font-size:12px;border:1px solid var(--line)"><div style="font-size:22px">{{ $ic }}</div>{{ $t }}</a>
        @endforeach
    </div>
    <div class="sec"><h3>آخر طلباتك</h3><a href="?tab=orders">الكل</a></div>
    @forelse ($open->take(4) as $o)
        @php [$sl, $sc, $step] = $stageOf($o); $og = $o->kind === 'shipping' ? ($origins[$o->ship_origin] ?? []) : null; @endphp
        <div class="o2"><div class="t"><span class="cd">{{ $o->code }}</span><span class="chip {{ $sc }}"><i></i>{{ $sl }}</span></div>
            <div class="m">@if ($og){{ $og['flag'] ?? '' }} شحن من {{ $og['name'] ?? '' }} · {{ $o->bags_num }} طرد{{ $o->kilo ? ' · ' . $f($o->kilo) . ' كغ' : '' }}@else{{ optional($o->platform)->name }} · {{ $o->qty }} قطعة{{ $o->received_qty !== null ? ' · وصل ' . $o->received_qty : '' }}@endif</div>
            <div class="prog2">@for ($i = 1; $i <= 5; $i++)<i class="{{ $i <= $step ? 'on' : '' }}"></i>@endfor</div>
            <div class="b"><span>{{ $og ? 'الشحن' : 'المتبقي عليك' }}</span>@if ($og)<b>{{ $f($o->usd_amount) }}$</b>@elseif ($o->remaining() > 0.009)<b style="color:var(--down)">{{ $f($o->remaining()) }} د.ل</b>@else<b style="color:var(--up)">مدفوعة ✓</b>@endif</div></div>
    @empty
        <div class="empty2"><span>🛍</span>لا توجد طلبات حالية</div>
    @endforelse

{{-- ========================= طلباتي ========================= --}}
@elseif ($tab === 'orders')
    @php $seg = request('seg', 'open'); $kind = request('kind'); $list = ($seg === 'done' ? $orders->where('delivered', true) : $open); if ($kind === 'shipping') $list = $list->where('kind', 'shipping'); elseif ($kind === 'buy') $list = $list->where('kind', '!=', 'shipping'); elseif ($kind === 'shelf') $list = $list->where('arrived', true)->where('delivered', false); @endphp
    <div class="hd"><div><h1>طلباتي</h1><div class="sub">المشتريات والشحنات معاً</div></div></div>
    <div class="seg"><a href="?tab=orders" class="{{ $seg !== 'done' ? 'on' : '' }}"><b>{{ $open->count() }}</b>الحالية</a><a href="?tab=orders&seg=done" class="{{ $seg === 'done' ? 'on' : '' }}"><b>{{ $orders->where('delivered', true)->count() }}</b>المستلمة</a></div>
    <div class="opt2">
        @foreach (['' => 'الكل', 'buy' => '🛍 شراء', 'shipping' => '📦 شحن', 'shelf' => '✅ جاهز للاستلام' . (($rdyN = $open->where('arrived', true)->where('delivered', false)->count()) ? ' (' . $rdyN . ')' : '')] as $k => $v)<a href="?tab=orders&seg={{ $seg }}&kind={{ $k }}"><span class="{{ (string) $kind === (string) $k ? 'on' : '' }}">{{ $v }}</span></a>@endforeach
    </div>
    @forelse ($list as $o)
        @php [$sl, $sc, $step] = $stageOf($o); $og = $o->kind === 'shipping' ? ($origins[$o->ship_origin] ?? \App\Services\AppSettings::origins()[$o->ship_origin] ?? []) : null; $rem = $o->remaining(); @endphp
        <div class="o2"><div class="t"><span class="cd">{{ $o->code }}</span><span class="chip {{ $sc }}"><i></i>{{ $sl }}</span></div>
            <div class="m">@if ($og){{ $og['flag'] ?? '' }} {{ $og['name'] ?? '' }} · <span class="num">{{ $o->tracking_no }}</span> · {{ $o->bags_num }} طرد{{ $o->kilo ? ' · ' . $f($o->kilo) . ' كغ' : '' }}@else{{ optional($o->platform)->name }}{{ $o->trip_details ? ' · رحلة ' . $o->trip_details : '' }} · {{ $o->qty }} قطعة{{ $o->received_qty !== null ? ' · وصل ' . $o->received_qty : '' }}@endif</div>
            @if ($o->missing_action === 'deduct' && $o->missing_value > 0)<div class="pb" style="display:flex;gap:6px;background:var(--warn-bg);color:var(--warn);border-radius:10px;padding:7px 10px;font-size:12.5px;margin-top:8px">⚠ {{ $o->missing_qty }} قطعة ناقصة · خُصمت قيمتها {{ $f($o->missing_value) }} د.ل</div>@endif
            @if (!$o->delivered)<div class="prog2">@for ($i = 1; $i <= 5; $i++)<i class="{{ $i <= $step ? 'on' : '' }}"></i>@endfor</div>@endif
            <div class="b">@if ($og)<span>سعر الكيلو {{ $f($o->usd_per_kg - $o->usd_discount) }}$ · {{ $f($o->usd_amount) }}$</span>@else<span>الإجمالي {{ $f($o->grandTotal()) }} · دفعت {{ $f($o->paid_price) }}</span>@endif @if ($rem > 0.009)<b style="color:var(--down)">المتبقي {{ $f($rem) }}</b>@else<b style="color:var(--up)">مدفوعة ✓</b>@endif</div>
            <div style="display:flex;gap:6px;margin-top:10px">
                <a class="btn ghost sm" style="flex:1" href="{{ route('order.invoice', $o->code) }}" target="_blank">🧾 الفاتورة</a>
                @if ($o->arrived && !$o->delivered && !$o->delivery_id)<a class="btn sm" style="flex:1;background:var(--up)" href="?tab=delivery&order={{ $o->id }}">🚚 أوصلوها لي</a>@endif
                <a class="btn ghost sm" href="?tab=problem&order={{ $o->id }}">⚠</a>
            </div>
        </div>
    @empty
        <div class="empty2"><span>📭</span>لا توجد طلبات هنا</div>
    @endforelse

{{-- ========================= طلب توصيل ========================= --}}
@elseif ($tab === 'delivery')
    <div class="back"><a class="icb" href="?tab=home"><svg width="20" height="20"><use href="#i-back"/></svg></a><h1>طلب توصيل</h1></div>
    @foreach ($deliveries->whereIn('status', ['requested', 'assigned', 'out', 'problem']) as $d)
        <div class="o2"><div class="t"><span class="cd">{{ $d->code }}</span><span class="chip {{ $d->status === 'out' ? 'c-way' : 'c-wait' }}"><i></i>{{ $d->statusLabel() }}</span></div><div class="m">{{ $d->orders->pluck('code')->implode('، ') }} · {{ $d->address }}</div>
            <div class="b"><span>{{ $d->courier ? 'المندوب: ' . $d->courier->name : 'بانتظار إسناد مندوب' }}</span><b>{{ $f($d->amount) }} د.ل</b></div>
            @if ($d->status === 'requested')<form method="post" action="{{ route('portal.delivery.cancel', $d->id) }}" onsubmit="return confirm('إلغاء طلب التوصيل؟')" style="margin-top:8px">@csrf<button class="btn ghost sm" style="width:100%">إلغاء الطلب</button></form>@endif
        </div>
    @endforeach
    @if (!($settings['portal_delivery_enabled'] ?? true))
        <div class="empty2"><span>🚫</span>طلب التوصيل من الحساب غير متاح حالياً، تواصل معنا</div>
    @elseif (!$ready->count())
        <div class="empty2"><span>📦</span>لا توجد طلبيات جاهزة في الرف حالياً<br>عند وصول بضاعتك يمكنك طلب توصيلها من هنا</div>
    @else
    <form method="post" action="{{ route('portal.delivery') }}" enctype="multipart/form-data" id="dlF">
        @csrf
        <div class="lbl" style="margin:0 2px 8px">الطلبيات الموجودة في الرف</div>
        @foreach ($ready as $o)
            <label class="pick2"><input type="checkbox" name="order_ids[]" value="{{ $o->id }}" data-rem="{{ max(0, $o->remaining()) }}" style="width:20px;height:20px;accent-color:var(--brand)" @checked(!$wantOrder || $wantOrder == $o->id)><div style="flex:1"><b>{{ $o->code }}</b> · {{ $o->received_qty ?? $o->qty ?: $o->bags_num }} {{ $o->kind === 'shipping' ? 'طرد' : 'قطعة' }}{{ $o->storage_num ? '' : '' }}</div>@if ($o->remaining() > 0.009)<b style="color:var(--down)">{{ $f($o->remaining()) }}</b>@else<b style="color:var(--up)">مدفوعة</b>@endif</label>
        @endforeach
        <div class="fl" style="margin-top:12px"><label>اسم المستلم</label><input name="recipient_name" class="inp" style="height:50px" value="{{ old('recipient_name', $c->name) }}" required></div>
        <div class="fl"><label>هاتف المستلم</label><input name="recipient_phone" class="inp num" style="height:50px;text-align:right" value="{{ old('recipient_phone', $c->phone_1) }}" required inputmode="tel"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px"><div class="fl"><label>المدينة</label><input name="city" class="inp" style="height:50px" list="lyCities" value="{{ old('city', $c->city ?? null ?: 'بنغازي') }}" required></div><div class="fl"><label>المنطقة</label><input name="area" class="inp" style="height:50px" value="{{ old('area', $c->area ?? null) }}" required placeholder="الكيش، الهواري…"></div></div>@include('sg.partials.cities')
        <div class="fl"><label>العنوان بالتفصيل</label><input name="address" class="inp" style="height:50px" value="{{ old('address', $c->address) }}" required placeholder="الشارع، أقرب معلم"></div>
        <div class="fl"><label>الموقع على الخريطة (اختياري)</label><div class="inp" style="justify-content:space-between;padding:0 0 0 8px;height:50px"><input name="location_url" id="loc" value="{{ old('location_url', $c->location_url) }}" placeholder="رابط Google Maps" style="border:0;outline:0;background:none;flex:1;height:100%;padding:0 14px;direction:ltr;font-size:13px"><button type="button" class="cp" id="gps" style="border:0;cursor:pointer">📍 موقعي الآن</button></div><div class="lbl" id="gpsN" style="margin-top:4px"></div></div>
        @if ($pointsOn && $pts >= $pointsMin && $pts > 0)<label class="ck2"><input type="checkbox" name="use_points" value="1" id="useP" data-v="{{ $pts * $pointValue }}"> استخدام نقاطي ({{ $pts }} نقطة = {{ $f($pts * $pointValue) }} د.ل)</label>@endif
        <div class="fl"><label>كوبون خصم (اختياري)</label><input name="coupon_code" class="inp" style="height:50px;direction:ltr;text-transform:uppercase" value="{{ old('coupon_code') }}" placeholder="مثلاً PTX7K2Q9"></div>
        <label style="display:block;font-size:13.5px;font-weight:700;margin:4px 0 7px">كيف ستدفع <span id="dueT" class="num">0</span> د.ل؟</label>
        <div class="pay2"><div><span>💵 نقداً للمندوب</span><input name="plan_cash" id="pc" class="inp num" style="height:44px;margin-top:6px;font-weight:800" value="{{ old('plan_cash') }}" inputmode="decimal"></div><div><span>🏦 تحويل مصرفي</span><input name="plan_bank" id="pb" class="inp num" style="height:44px;margin-top:6px;font-weight:800" value="{{ old('plan_bank', 0) }}" inputmode="decimal"></div></div>
        <div id="bankBox" style="display:none">
            @if (!empty($settings['bank_accounts_text']))<div class="note-c" style="background:var(--s1-bg);color:#1d4f8f;white-space:pre-line">{{ $settings['bank_accounts_text'] }}</div>@endif
            @if ($banks->count() > 1)<select name="bank_payment_id" class="inp" style="height:46px;margin-bottom:10px">@foreach ($banks as $bk)<option value="{{ $bk->id }}">{{ $bk->name }}</option>@endforeach</select>@elseif ($banks->count())<input type="hidden" name="bank_payment_id" value="{{ $banks->first()->id }}">@endif
            <label class="up" style="display:block">📎 صورة إيصال التحويل<input type="file" name="receipt" accept="image/*" style="display:block;margin:8px auto 0;font-size:12px"></label>
        </div>
        <input name="note" class="inp" style="height:46px" placeholder="ملاحظة للمندوب (اختياري)" value="{{ old('note') }}">
        <div style="height:80px"></div>
        <div class="bar"><button class="btn" style="background:var(--up);flex:1;height:52px">إرسال طلب التوصيل</button></div>
    </form>
    @endif

{{-- ========================= لدي مشكلة ========================= --}}
@elseif ($tab === 'problem')
    <div class="back"><a class="icb" href="?tab=home"><svg width="20" height="20"><use href="#i-back"/></svg></a><h1>لدي مشكلة</h1></div>
    <form method="post" action="{{ route('portal.ticket') }}" enctype="multipart/form-data">
        @csrf
        <div class="lbl" style="margin:0 2px 8px">على أي طلبية؟</div>
        <div class="radio2">
            @foreach ($orders->take(8) as $o)<label><input type="radio" name="order_id" value="{{ $o->id }}" @checked($wantOrder == $o->id || (!$wantOrder && $loop->first))><span class="num">{{ $o->code }}</span></label>@endforeach
            <label><input type="radio" name="order_id" value=""><span>ليست على طلبية</span></label>
        </div>
        <div class="lbl" style="margin:0 2px 8px">نوع المشكلة</div>
        <div class="radio2">@foreach (\App\Models\SupportTicket::TYPES as $k => $v)<label><input type="radio" name="type" value="{{ $k }}" @checked($loop->first) required><span>{{ $v }}</span></label>@endforeach</div>
        <textarea name="message" class="inp" rows="4" placeholder="اشرح المشكلة…" required style="margin-bottom:12px">{{ old('message') }}</textarea>
        <label class="up" style="display:block">📷 إرفاق صورة (اختياري)<input type="file" name="image" accept="image/*" style="display:block;margin:8px auto 0;font-size:12px"></label>
        <button class="btn" style="width:100%;height:50px;margin-bottom:16px">إرسال للمكتب</button>
    </form>
    <div class="sec"><h3>بلاغاتي السابقة</h3></div>
    @forelse ($tickets as $t)
        <div class="tk"><div class="t"><b>{{ optional($t->order)->code ? $t->order->code . ' · ' : '' }}{{ \App\Models\SupportTicket::TYPES[$t->type] ?? '' }}</b><span class="chip {{ ['open' => 'c-wait', 'answered' => 'c-way', 'closed' => 'c-ok'][$t->status] ?? 'c-new' }}"><i></i>{{ \App\Models\SupportTicket::STATUSES[$t->status][0] ?? '' }}</span></div><p>{{ $t->message }}</p>@if ($t->reply)<div class="rep">💬 المكتب: {{ $t->reply }}</div>@endif<div class="lbl" style="margin-top:6px">{{ $t->created_at->diffForHumans() }}</div></div>
    @empty
        <div class="empty2" style="padding:20px"><span>✓</span>لا توجد بلاغات سابقة</div>
    @endforelse

{{-- ========================= حسابي ========================= --}}
@elseif ($tab === 'account')
    <div class="hd"><div><h1>حسابي</h1><div class="sub">المستحقات والرصيد والنقاط</div></div></div>
    <div class="cash" style="margin-bottom:10px"><div class="t">المستحق عليك لـ {{ $settings['shop_name'] }}</div><div class="v num">{{ $f($due) }} <small>د.ل</small></div>
        @if ($debts->count())<div class="row">@foreach ($debts->take(3) as $o)<div>{{ $o->code }}<b>{{ $f($o->remaining()) }}</b></div>@endforeach</div>@endif
        <a class="btn" href="{{ route('portal.statement') }}" target="_blank">كشف حساب</a></div>
    @if ($credit > 0.009)<div class="note-c" style="background:var(--s1-bg);color:#1d4f8f">💳 لديك رصيد <b class="num">{{ $f($credit) }}</b> د.ل يُخصم من طلبيتك القادمة</div>@endif
    @if ($couponOn ?? false)
        <div class="pts" style="background:linear-gradient(135deg,#6250d6,#8a6cf0)"><small>حوّل نقاطك إلى كوبون</small><div class="v">{{ $pts }} <small style="font-size:13px">نقطة</small></div>
            <small>كل {{ $couponCost }} نقطة = كوبون خصم {{ rtrim(rtrim(number_format($couponPct, 2), '0'), '.') }}%</small>
            @if ($pts >= $couponCost)<form method="post" action="{{ route('portal.points.coupon') }}" style="margin-top:8px" onsubmit="return confirm('تحويل {{ $couponCost }} نقطة إلى كوبون؟')">@csrf<button class="btn" style="background:#fff;color:#6250d6;height:40px">🎟 احصل على كوبون</button></form>@endif
        </div>
    @endif
    @if ($myCoupons->count())
        <div class="card hist" style="padding:12px 14px;margin-bottom:10px"><div class="sec" style="margin:0 0 6px"><h3>كوبوناتي</h3></div>
            @foreach ($myCoupons as $cp)<div class="row"><span class="ic" style="background:var(--s7-bg);color:var(--s7)">🎟</span><div style="flex:1"><b class="num">{{ $cp->code }}</b><div class="lbl">خصم {{ $cp->label() }}{{ $cp->expires_at ? ' · حتى ' . $cp->expires_at->format('Y-m-d') : '' }}</div></div><b>{{ $cp->isUsable() ? 'صالح' : 'مستخدم' }}</b></div>@endforeach
        </div>
    @endif
    @if ($pointsOn)<div class="pts"><small>نقاطك</small><div class="v">{{ $pts }}</div><small>تساوي {{ $f($pts * $pointValue) }} د.ل خصم · يمكنك استخدامها من {{ $pointsMin }} نقاط فأكثر</small></div>@endif
    <div class="card hist" style="padding:12px 14px">
        <div class="sec" style="margin:0 0 6px"><h3>آخر الحركات</h3><a href="{{ route('portal.statement') }}" target="_blank">كشف حساب PDF</a></div>
        @forelse ($moves as $m)
            <div class="row"><span class="ic" style="background:{{ $m->kind === 'pay' ? 'var(--up-bg)' : 'var(--s7-bg)' }};color:{{ $m->kind === 'pay' ? 'var(--up)' : 'var(--s7)' }}">{!! $m->kind === 'pay' ? '<svg width="18" height="18"><use href="#i-cash"/></svg>' : '★' !!}</span><div class="m"><b>{{ $m->title }}</b><span>{{ $m->t->format('Y-m-d g:i a') }}{{ $m->sub ? ' · ' . $m->sub : '' }}</span></div><span class="a" style="color:{{ $m->kind === 'pay' ? 'var(--up)' : 'var(--s7)' }}">{{ $m->amount >= 0 ? '+' : '' }}{{ $m->kind === 'pay' ? $f($m->amount) : (int) $m->amount }}</span></div>
        @empty
            <div class="lbl" style="padding:10px 0">لا توجد حركات بعد</div>
        @endforelse
    </div>
    @if ($feed->count())
        <div class="sec" style="margin-top:12px"><h3>🔔 الإشعارات والرسائل</h3></div>
        @foreach ($feed->take(15) as $m)<div class="tk"><p style="margin:0;white-space:pre-line">{{ $m->body }}</p><div class="lbl" style="margin-top:4px">{{ $m->code ? $m->code . ' · ' : '' }}{{ $m->t->diffForHumans() }}</div></div>@endforeach
    @endif

{{-- ========================= الأسعار ========================= --}}
@elseif ($tab === 'prices')
    <div class="back"><a class="icb" href="?tab=home"><svg width="20" height="20"><use href="#i-back"/></svg></a><h1>الأسعار</h1></div>
    <div class="sec"><h3>💲 سعر الشحن للكيلو</h3></div>
    <div class="pr">
        @foreach ($origins as $k => $og)
            <div class="r"><span class="f">{{ $og['flag'] }}</span><div class="x"><b>{{ $og['name'] }}</b><small>{{ $og['days'] }}{{ ($og['min'] ?? 0) > 0 ? ' · أقل شحنة ' . $f($og['min']) . '$' : '' }}</small></div>@php $myKg = SS::perKg($c, $k); @endphp<div class="p">@if ($myKg < $og['per_kg'] - 0.0001)<s style="font-size:12px;color:var(--ink-3);font-weight:400">{{ $f($og['per_kg']) }}$</s> @endif{{ $f($myKg) }}$<small>للكيلو</small></div></div>
        @endforeach
    </div>
    @php $mine = collect($special)->filter(fn ($v, $k) => isset($origins[$k]) && is_numeric($v)); @endphp
    @if ($mine->count())<div class="note-c" style="background:var(--s7-bg);color:var(--s7)">⭐ لديك سعر خاص: @foreach ($mine as $k => $v){{ $origins[$k]['name'] }} {{ $f($v) }}$ للكيلو{{ $loop->last ? '' : '، ' }}@endforeach</div>@endif
    <div class="sec"><h3>🛍 المواقع التي نشتري منها</h3></div>
    <div class="pr">
        @foreach ($platforms as $p)
            <div class="st2"><span class="lg" style="background:#161a2e">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($p->name, 5, '')) }}</span><div style="flex:1"><b>{{ $p->name }}</b><div class="lbl">{{ $pfNotes[$p->id] ?? '' }}</div></div><div style="font-family:InterNum;font-weight:800;text-align:left">{{ $p->rate ? number_format($p->rate, 2) : number_format($rate, 2) }}<small style="display:block;font-family:Somar;font-weight:400;font-size:11px;color:var(--ink-3)">د.ل للدولار</small></div></div>
        @endforeach
    </div>
    @if ($settings['shop_phone'])<a class="btn" style="width:100%;height:50px" href="https://wa.me/{{ \App\Services\MessageService::normalize((string) $settings['shop_phone']) }}" target="_blank">🛒 أرسل لنا رابط السلة عبر واتساب</a>@endif

{{-- ========================= عناويننا ========================= --}}
@elseif ($tab === 'addresses')
    <div class="back"><a class="icb" href="?tab=home"><svg width="20" height="20"><use href="#i-back"/></svg></a><h1>عناويننا للشحن</h1></div>
    <div class="warnbox"><b>⚠ تنبيه مهم قبل الشراء</b>انسخ العنوان كما هو دون أي تعديل، فهو يحتوي على كودك <b style="display:inline;color:var(--down)">SG{{ $c->code }}</b>. في حال كتابة العنوان أو الكود بشكل خاطئ، <b style="display:inline">فإن الشركة غير مسؤولة</b> عن ضياع الشحنة أو تأخرها.</div>
    @foreach ($origins as $k => $og)
        @foreach (SS::addrs($og) as $ai => $ad)
        @php $txt = SS::addressFor($og, $c, $ad); @endphp
        <div class="addr"><div class="h"><span>{{ $og['flag'] }}</span><div><b>مخزن {{ $og['name'] }} · {{ SS::MODES[$ad['mode'] ?? 'جوي'] ?? '' }} {{ $ad['mode'] ?? 'جوي' }}</b><small>{{ $og['city'] }} · {{ $f(SS::perKg($c, $k, $ad['mode'] ?? null)) }}$ للكيلو</small></div></div>
            <div class="abox" id="ad{{ $k }}{{ $ai }}">{!! str_replace(e('SG' . $c->code), '<b>' . e('SG' . $c->code) . '</b>', e($txt)) !!}</div>
            <button class="btn" type="button" style="width:100%;margin-top:10px;height:42px;font-size:14px" data-copy="{{ $txt }}">📋 نسخ العنوان كاملاً</button></div>
        @endforeach
    @endforeach
    <form method="post" action="{{ route('portal.tracking') }}" class="card" style="padding:14px;margin-top:6px">
        @csrf
        <div class="fl"><label>تسجيل رقم التتبع قبل وصول الشحنة (اختياري)</label>
            <select name="origin" class="inp" style="height:46px;margin-bottom:8px">@foreach ($origins as $k => $og)<option value="{{ $k }}">{{ $og['flag'] }} {{ $og['name'] }}</option>@endforeach</select>
            <input name="tracking_no" class="inp num" style="height:46px;margin-bottom:8px;text-align:right" placeholder="مثال: YT2210984421CN" required>
            <input name="note" class="inp" style="height:46px" placeholder="محتوى الشحنة (اختياري)"></div>
        <button class="btn ghost" style="width:100%">+ إضافة</button>
    </form>

{{-- ========================= حاسبة الشحن ========================= --}}
@elseif ($tab === 'calc')
    <div class="back"><a class="icb" href="?tab=home"><svg width="20" height="20"><use href="#i-back"/></svg></a><h1>حاسبة الشحن</h1></div>
    @php $calcData = collect($origins)->map(fn ($og, $k) => ['name' => $og['flag'] . ' ' . $og['name'], 'kg' => SS::perKg($c, $k), 'min' => (float) ($og['min'] ?? 0), 'tiers' => SS::tiers($og), 'days' => $og['days'] ?? '']); @endphp
    <div class="card" style="padding:14px">
        <div class="fl"><label>من وين؟</label><select id="cOg" class="inp" style="height:46px">@foreach ($calcData as $k => $cd)<option value="{{ $k }}">{{ $cd['name'] }}</option>@endforeach</select></div>
        <div class="fl" style="margin-top:10px"><label>الوزن (كيلو)</label><input id="cKg" class="inp num" style="height:46px;text-align:right" inputmode="decimal" placeholder="مثلاً 3.5"></div>
        <div style="background:var(--brand,#262c4a);color:#fff;border-radius:16px;padding:16px;margin-top:12px">
            <div style="font-size:13px;opacity:.8" id="cLine">اكتب الوزن</div>
            <div style="font-size:30px;font-weight:800" class="num"><span id="cUsd">0</span> $</div>
            <div style="font-size:14px;opacity:.9" class="num">💵 كاش ≈ <span id="cLyd">0</span> د.ل</div>
            <div style="font-size:14px;opacity:.9" class="num">🏦 مصرف ≈ <span id="cLydB">0</span> د.ل</div>
            <div style="font-size:12px;opacity:.75;margin-top:6px" id="cDays"></div>
        </div>
        <div class="lbl" style="margin-top:8px">السعر تقريبي، والنهائي حسب الوزن الفعلي في المخزن.</div>
    </div>
    <script>
    (function () {
      const D = @json($calcData), R = {{ SS::rates()['cash'] }}, RB = {{ SS::rates()['bank'] }}, og = document.getElementById('cOg'), kg = document.getElementById('cKg');
      const f = n => (Math.round(n * 100) / 100).toLocaleString('en-US');
      function go() { const o = D[og.value], k = parseFloat(kg.value) || 0; let p = o.kg, tag = '';
        Object.keys(o.tiers).forEach(t => { if (k >= parseFloat(t) && o.tiers[t] < p) { p = o.tiers[t]; tag = ' (سعر الوزن الكبير)'; } });
        const usd = k > 0 ? Math.max(o.min, k * p) : 0;
        document.getElementById('cLine').textContent = k ? f(k) + ' كغ × ' + f(p) + '$' + tag : 'اكتب الوزن';
        document.getElementById('cUsd').textContent = f(usd); document.getElementById('cLyd').textContent = f(usd * R); document.getElementById('cLydB').textContent = f(usd * RB);
        document.getElementById('cDays').textContent = o.days ? '⏱ المدة: ' + o.days : ''; }
      og.onchange = go; kg.oninput = go; go();
    })();
    </script>

{{-- ========================= مرتجعاتي ========================= --}}
@elseif ($tab === 'returns')
    <div class="back"><a class="icb" href="?tab=home"><svg width="20" height="20"><use href="#i-back"/></svg></a><h1>مرتجعاتي</h1></div>
    @forelse ($myReturns as $r)
        <div class="card" style="padding:12px 14px;margin-bottom:8px">
            <div style="display:flex;justify-content:space-between"><b>{{ $r->type === 'exchange' ? '⇄ استبدال' : '↩ مرتجع' }} · <span class="num">{{ optional($r->order)->code }}</span></b><span class="lbl num">{{ $r->created_at->format('Y-m-d') }}</span></div>
            <div class="lbl" style="margin-top:4px">{{ $r->items ?: '—' }}@if ($r->qty) ({{ $r->qty }} قطعة)@endif @if ($r->new_items) · بدالها: {{ $r->new_items }}@endif</div>
            <div style="margin-top:6px;font-size:13.5px">القيمة <b class="num">{{ $f($r->amount) }}</b> د.ل · {{ ['cash' => 'رجعنالك فلوس', 'credit' => 'صارت رصيد عندنا', 'deduct' => 'انخصمت من الإيصال', 'none' => 'بدون إرجاع مبلغ'][$r->method] ?? $r->method }}</div>
        </div>
    @empty
        <div class="card" style="padding:24px;text-align:center"><div style="font-size:30px">↩</div><div class="lbl">ما عندكش مرتجعات</div></div>
    @endforelse

{{-- ========================= ادعُ صديق ========================= --}}
@elseif ($tab === 'invite')
    @php $refLink = route('customer.register', ['ref' => $c->code]); $refPts = (int) ($settings['referral_points'] ?? 50); @endphp
    <div class="back"><a class="icb" href="?tab=home"><svg width="20" height="20"><use href="#i-back"/></svg></a><h1>ادعُ صديق</h1></div>
    <div class="card" style="padding:16px;text-align:center">
        <div style="font-size:40px">🎁</div>
        <b style="font-size:16px">ابعت الرابط لصحابك</b>
        <div class="lbl" style="margin:6px 0 12px">لما صاحبك يسجل ويستلم أول طلبية، تاخد <b class="num">{{ $refPts }}</b> نقطة</div>
        <div class="abox" style="direction:ltr;word-break:break-all">{{ $refLink }}</div>
        <div style="display:flex;gap:8px;margin-top:10px">
            <button class="btn" type="button" style="flex:1" data-copy="{{ $refLink }}">📋 نسخ الرابط</button>
            <a class="btn" style="flex:1;background:var(--wa,#25d366)" target="_blank" href="https://wa.me/?text={{ rawurlencode('سجل معاي في ' . $settings['shop_name'] . ' واطلب من SHEIN وغيره بسهولة 👇 ' . $refLink) }}">💬 واتساب</a>
        </div>
    </div>
    @if ($invited->count())
        <div class="sec"><h3>اللي دعيتهم ({{ $invited->count() }})</h3></div>
        @foreach ($invited as $iv)<div class="card" style="padding:10px 14px;margin-bottom:6px;display:flex;justify-content:space-between"><span>{{ $iv->name }}</span><span class="lbl">{{ $iv->referral_rewarded ? '✓ خذيت النقاط' : 'يستنى أول طلبية' }}</span></div>@endforeach
    @endif

{{-- ========================= الأسئلة والسياسات ========================= --}}
@elseif ($tab === 'faq')
    <div class="back"><a class="icb" href="?tab=home"><svg width="20" height="20"><use href="#i-back"/></svg></a><h1>الأسئلة والسياسات</h1></div>
    @php
        $faq = []; $cur = null;
        foreach (preg_split('/\R/u', (string) ($settings['portal_faq'] ?? \App\Services\AppSettings::defaultFaq())) as $ln) {
            $ln = trim($ln); if ($ln === '') continue;
            if (preg_match('/^س\s*[:：]\s*(.+)$/u', $ln, $m)) { $faq[] = ['q' => $m[1], 'a' => '']; }
            elseif (preg_match('/^ج\s*[:：]\s*(.+)$/u', $ln, $m) && $faq) { $faq[count($faq) - 1]['a'] .= $m[1]; }
            elseif ($faq) { $faq[count($faq) - 1]['a'] .= "\n" . $ln; }
        }
    @endphp
    @foreach ($faq as $qa)
        <details class="card" style="padding:12px 14px;margin-bottom:8px"><summary style="font-weight:700;cursor:pointer">{{ $qa['q'] }}</summary><div style="margin-top:8px;font-size:14px;line-height:1.8;white-space:pre-line">{{ $qa['a'] }}</div></details>
    @endforeach

{{-- ========================= الإعدادات ========================= --}}
@else
    <div class="hd"><div><h1>الإعدادات</h1></div></div>
    <div class="prof"><div class="av">{{ mb_substr($c->name, 0, 1) }}</div><div style="flex:1"><b>{{ $c->name }}</b><span class="num" style="display:block;text-align:right">{{ $c->phone_1 }}</span><span>كودك {{ $c->code }} · عميل منذ {{ optional($c->created_at)->format('Y') }}</span></div></div>
    <div class="gl">حسابي</div>
    <div class="grp">
        <details class="it" style="display:block"><summary style="display:flex;align-items:center;gap:12px;list-style:none"><span class="ic"><svg width="18" height="18"><use href="#i-pin"/></svg></span><span class="x">عنوان التوصيل والهاتف<span>{{ trim(implode(' · ', array_filter([$c->city ?? null, $c->area ?? null]))) ?: ($c->address ?: 'لم يُحدد') }}{{ $c->location_url ? ' · يوجد موقع على الخريطة' : '' }}</span></span><svg width="16" height="16" style="color:var(--ink-3)"><use href="#i-chev"/></svg></summary>
            <form method="post" action="{{ route('portal.profile') }}" style="margin-top:10px">@csrf
                <input name="city" class="inp" style="height:46px;margin-bottom:8px" list="lyCities" value="{{ $c->city ?? null ?: 'بنغازي' }}" placeholder="المدينة" required>
                <input name="area" class="inp" style="height:46px;margin-bottom:8px" value="{{ $c->area ?? null }}" placeholder="المنطقة" required>
                <input name="address" class="inp" style="height:46px;margin-bottom:8px" value="{{ $c->address }}" placeholder="العنوان بالتفصيل">
                <input name="location_url" class="inp" style="height:46px;margin-bottom:8px;direction:ltr" value="{{ $c->location_url }}" placeholder="رابط الموقع">
                <input name="phone_2" class="inp num" style="height:46px;margin-bottom:8px;text-align:right" value="{{ $c->phone_2 }}" placeholder="هاتف ثانٍ">
                <button class="btn" style="width:100%">حفظ</button>
            </form></details>
        <details class="it" style="display:block"><summary style="display:flex;align-items:center;gap:12px;list-style:none"><span class="ic"><svg width="18" height="18"><use href="#i-lock"/></svg></span><span class="x">تغيير كلمة المرور</span><svg width="16" height="16" style="color:var(--ink-3)"><use href="#i-chev"/></svg></summary>
            <form method="post" action="{{ route('portal.password') }}" style="margin-top:10px">@csrf
                <input type="password" name="current" class="inp" style="height:46px;margin-bottom:8px" placeholder="كلمة المرور الحالية" required>
                <input type="password" name="new" class="inp" style="height:46px;margin-bottom:8px" placeholder="كلمة المرور الجديدة (8 خانات على الأقل)" required>
                <input type="password" name="new_confirmation" class="inp" style="height:46px;margin-bottom:8px" placeholder="تأكيد كلمة المرور" required>
                <button class="btn" style="width:100%">حفظ</button>
            </form></details>
    </div>
    <div class="gl">مساعدة</div>
    <div class="grp">
        <a class="it" href="?tab=faq"><span class="ic">❓</span><span class="x">الأسئلة والسياسات</span><svg width="16" height="16" style="color:var(--ink-3)"><use href="#i-chev"/></svg></a>
        <a class="it" href="?tab=invite"><span class="ic">🎁</span><span class="x">ادعُ صديق واكسب نقاط</span><svg width="16" height="16" style="color:var(--ink-3)"><use href="#i-chev"/></svg></a>
        <a class="it" href="?tab=returns"><span class="ic">↩</span><span class="x">مرتجعاتي</span><svg width="16" height="16" style="color:var(--ink-3)"><use href="#i-chev"/></svg></a>
        <a class="it" href="?tab=problem"><span class="ic">⚠</span><span class="x">لدي مشكلة</span><svg width="16" height="16" style="color:var(--ink-3)"><use href="#i-chev"/></svg></a>
        <div class="it"><span class="ic">📍</span><span class="x">عنوان المحل وأوقات الدوام<span>{{ $settings['shop_address'] ?: '—' }} · {{ $settings['shop_hours'] }}</span></span></div>
        @if ($settings['shop_phone'])<a class="it" href="https://wa.me/{{ \App\Services\MessageService::normalize((string) $settings['shop_phone']) }}" target="_blank"><span class="ic" style="color:var(--wa)"><svg width="18" height="18"><use href="#i-wa"/></svg></span><span class="x">تواصل معنا عبر واتساب<span class="num" style="text-align:right">{{ $settings['shop_phone'] }}</span></span></a>@endif
        <div class="it"><span class="ic">📲</span><span class="x">أضف الحساب للشاشة الرئيسية<span>من قائمة المتصفح اختر "إضافة إلى الشاشة الرئيسية" ليفتح كأنه تطبيق</span></span></div>
        <form method="post" action="{{ route('customer.logout') }}" class="it" style="color:var(--down)">@csrf<span class="ic" style="background:var(--down-bg);color:var(--down)"><svg width="18" height="18"><use href="#i-out"/></svg></span><button class="x" style="border:0;background:none;color:var(--down);text-align:right;font-size:14.5px;padding:0;cursor:pointer;font-family:inherit">تسجيل الخروج</button></form>
    </div>
@endif
</div>

@if (!in_array($tab, ['delivery']) || !$ready->count())
<nav class="tabs">
    <a href="?tab=home" class="{{ $tab === 'home' ? 'on' : '' }}"><svg width="23" height="23"><use href="#i-home"/></svg>الرئيسية</a>
    <a href="?tab=orders" class="{{ $tab === 'orders' ? 'on' : '' }}"><svg width="23" height="23"><use href="#i-box"/></svg>طلباتي@if ($ready->count())<span class="bd">{{ $ready->count() }}</span>@endif</a>
    <a href="?tab=delivery" class="{{ $tab === 'delivery' ? 'on' : '' }}"><svg width="23" height="23"><use href="#i-truck"/></svg>توصيل</a>
    <a href="?tab=account" class="{{ $tab === 'account' ? 'on' : '' }}"><svg width="23" height="23"><use href="#i-wallet"/></svg>حسابي</a>
    <a href="?tab=settings" class="{{ in_array($tab, ['settings', 'prices', 'addresses', 'problem']) ? 'on' : '' }}"><svg width="23" height="23"><use href="#i-gear"/></svg>المزيد</a>
</nav>
@endif
</div>
<script>
document.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', () => {
  const t = b.dataset.copy, done = () => { const o = b.textContent; b.textContent = '✓ تم النسخ'; setTimeout(() => b.textContent = o, 1800); };
  if (navigator.clipboard) navigator.clipboard.writeText(t).then(done, () => { prompt('انسخ العنوان:', t); });
  else { prompt('انسخ العنوان:', t); }
}));
(function () {
  const f = document.getElementById('dlF'); if (!f) return;
  const pc = document.getElementById('pc'), pb = document.getElementById('pb'), FEE = 0;
  const due = () => { let d = 0; f.querySelectorAll('input[name="order_ids[]"]:checked').forEach(i => d += parseFloat(i.dataset.rem) || 0); const up = document.getElementById('useP'); if (up && up.checked) d = Math.max(0, d - parseFloat(up.dataset.v)); return Math.round(d * 100) / 100; };
  const upd = (auto) => { const d = due(); document.getElementById('dueT').textContent = d.toLocaleString('en-US'); if (auto) pc.value = Math.max(0, Math.round((d + FEE - (parseFloat(pb.value) || 0)) * 100) / 100); document.getElementById('bankBox').style.display = (parseFloat(pb.value) || 0) > 0 ? 'block' : 'none'; };
  f.querySelectorAll('input[name="order_ids[]"], #useP').forEach(i => i && i.addEventListener('change', () => upd(true)));
  pb.addEventListener('input', () => upd(true)); upd(!pc.value);
  const g = document.getElementById('gps');
  g && g.addEventListener('click', () => { const n = document.getElementById('gpsN');
    if (!navigator.geolocation) { n.textContent = 'المتصفح لا يدعم تحديد الموقع'; return; }
    n.textContent = 'جارٍ تحديد موقعك…';
    navigator.geolocation.getCurrentPosition(p => { document.getElementById('loc').value = 'https://maps.google.com/?q=' + p.coords.latitude.toFixed(6) + ',' + p.coords.longitude.toFixed(6); n.textContent = '✓ تم تحديد موقعك'; }, () => { n.textContent = 'تعذر تحديد الموقع، الصق الرابط من Google Maps'; }, { enableHighAccuracy: true, timeout: 10000 });
  });
})();
</script>
@if (\App\Http\Controllers\Portal\PortalController::badName($c->name))
<div style="position:fixed;inset:0;z-index:999;background:rgba(15,20,40,.6);display:flex;align-items:center;justify-content:center;padding:16px">
  <form method="post" action="{{ route('portal.name') }}" style="background:#fff;border-radius:18px;padding:22px;max-width:380px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3);text-align:center">@csrf
    <div style="font-size:40px">👋</div>
    <h3 style="margin:6px 0">أهلاً بيك! شن اسمك؟</h3>
    <p style="color:#666;font-size:14px;margin:0 0 12px">باش نعرفوك ونجهزولك طلبياتك صح، اكتب اسمك الحقيقي</p>
    @if (session('error'))<div style="color:#c0392b;font-size:13px;margin-bottom:8px">{{ session('error') }}</div>@endif
    <input name="name" required minlength="3" maxlength="60" placeholder="مثلاً: محمد علي" autofocus style="width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid #ddd;border-radius:12px;font-size:16px;font-family:inherit;text-align:center">
    <button style="margin-top:12px;width:100%;padding:12px;border:0;border-radius:12px;background:#f47b20;color:#fff;font-size:16px;font-weight:700;font-family:inherit;cursor:pointer">حفظ ومتابعة</button>
  </form>
</div>
@endif
</body>
</html>
