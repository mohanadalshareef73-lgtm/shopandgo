@php
    $f = fn ($n) => \App\Services\TreasuryService::fmt($n);
    $wa = fn ($p) => \App\Services\MessageService::normalize((string) $p);
    $exp = (float) $bal['expected'];
    $frac = round($exp - floor($exp), 2);
    $cr = $me->close_request ? json_decode($me->close_request, true) : null;
    $first = explode(' ', trim($me->name))[0];
    $chip = ['assigned' => ['c-wait', 'جاهزة للانطلاق'], 'out' => ['c-way', 'في الطريق'], 'problem' => ['c-bad', 'توجد مشكلة'], 'delivered' => ['c-ok', 'تم التسليم'], 'returned' => ['c-new', 'أُعيدت للمحل']];
    $evLabel = ['assigned' => ['📦', 'طلبية جديدة لك', 'c-new'], 'out' => ['🚚', 'انطلقت بالطلبية', 'c-way'], 'delivered' => ['✅', 'تم التسليم', 'c-ok'], 'problem' => ['⚠', 'مشكلة', 'c-bad'], 'returned' => ['↩', 'أُعيدت للمحل', 'c-new'], 'cancelled' => ['✕', 'أُلغيت', 'c-bad']];
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8">

<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#262c4a">
<title>تطبيق المندوب · {{ $me->name }}</title>
<link rel="stylesheet" href="{{ asset('assets/sg/app.css') }}?v=6">
</head>
<body>
@include('partials.back', ['home' => route('courier.home'), 'float' => true])
@include('app.icons')
<div class="app">
<div class="body">
@if (session('success'))<div class="flash ok">✓ {{ session('success') }}</div>@endif
@if (session('error'))<div class="flash err">{{ session('error') }}</div>@endif
@if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif

{{-- ===================== الرئيسية ===================== --}}
@if ($tab === 'home')
    <div class="hd">
        <div class="hello"><div class="av">{{ mb_substr($me->name, 0, 1) }}</div><div><div class="sub" style="margin:0">{{ now()->hour < 12 ? 'صباح الخير' : 'مساء الخير' }} 👋</div><h1 style="font-size:20px">{{ $me->name }}</h1></div></div>
        <a class="icb" href="?tab=notes"><svg width="21" height="21"><use href="#i-bell"/></svg>@if ($notes->where('created_at', '>=', now()->subDay())->count())<span class="dot"></span>@endif</a>
    </div>
    <div class="cash">
        <div class="t">المبلغ النقدي الذي معك من العملاء</div>
        <div class="v num">{{ $f($bal['cash']) }} <small>د.ل</small></div>
        <div class="row">
            <div>قبضت اليوم<b>{{ $f($todayCash) }}</b></div>
            <div>الصافي للمكتب<b>{{ $f(floor($exp)) }}</b></div>
            <div>آخر إقفال<b style="font-family:Somar;font-size:13.5px">{{ optional($settlements->first())->created_at ? $settlements->first()->created_at->diffForHumans() : '—' }}</b></div>
        </div>
        <a class="btn" href="?tab=close">إقفال الحساب</a>
    </div>
    <div style="margin:10px 0">@include('sg.partials.push', ['subUrl' => route('courier.push.subscribe')])</div>
    @if ($rate && $rate['n'])
    <div class="kp" style="margin:10px 0;display:block">
        <div style="display:flex;align-items:center;gap:10px"><span style="font-size:28px;color:#e0a800">★</span><div><b style="font-size:22px" class="num">{{ number_format($rate['avg'], 1) }}</b> <small class="note">من 5 · {{ $rate['n'] }} تقييم</small><div class="note" style="font-size:12.5px">تقييم العملاء ليك {{ $rate['avg'] >= 4.5 ? '— ممتاز، كمّل هكي 👏' : ($rate['avg'] >= 3.5 ? '— باهي، تقدر تطلع أحسن 💪' : '— يحتاج اهتمام أكثر 🙏') }}</div></div></div>
        @foreach ($rate['notes'] as $rn)
            <div style="border-top:1px solid var(--line-soft);margin-top:8px;padding-top:6px;font-size:13px"><span style="color:#e0a800">{{ str_repeat('★', (int) $rn->rating) }}</span><span style="color:#ccc">{{ str_repeat('★', 5 - (int) $rn->rating) }}</span> @if ($rn->rating_note)<span>«{{ $rn->rating_note }}»</span>@endif <small class="note">· {{ $rn->rated_at ? \Carbon\Carbon::parse($rn->rated_at)->diffForHumans() : '' }}</small></div>
        @endforeach
    </div>
    @endif
    <div class="k2">
        <a class="kp" href="?tab=orders"><div class="ki" style="background:var(--warn-bg);color:var(--warn)"><svg width="19" height="19"><use href="#i-box"/></svg></div><div class="v">{{ $now->count() }}</div><div class="t">طلبيات معلّقة لديك</div></a>
        <a class="kp" href="?tab=orders&seg=done"><div class="ki" style="background:var(--up-bg);color:var(--up)"><svg width="19" height="19"><use href="#i-check"/></svg></div><div class="v">{{ $todayDone }}</div><div class="t">سلّمتها اليوم <span class="lbl">· {{ $monthDone }} هذا الشهر</span></div></a>
    </div>
    @foreach ($now->where('status', 'problem')->take(2) as $d)
        <a class="alert" href="?d={{ $d->id }}"><svg width="22" height="22"><use href="#i-warn"/></svg><div class="tx"><b>مشكلة في طلبية {{ $d->recipient_name }} @if (optional($d->customer)->code) <span style="background:#262c4a;color:#fff;border-radius:6px;padding:1px 7px;font-family:InterNum;font-size:.85em;font-weight:800">{{ $d->customer->code }}</span>@endif</b>{{ \Illuminate\Support\Str::limit($d->problem, 50) }}</div><svg width="18" height="18"><use href="#i-chev"/></svg></a>
    @endforeach
    <div class="sec"><h3>الطلبيات القادمة</h3><a href="?tab=orders">عرض الكل</a></div>
    @forelse ($now->take(5) as $d)
        <a class="mini" href="?d={{ $d->id }}"><div class="n">{{ $loop->iteration }}</div><div class="m"><b>{{ $d->recipient_name }} @if (optional($d->customer)->code) <span style="background:#262c4a;color:#fff;border-radius:6px;padding:1px 7px;font-family:InterNum;font-size:.85em;font-weight:800">{{ $d->customer->code }}</span>@endif</b><span>{{ \Illuminate\Support\Str::limit(trim($d->city . ' ' . $d->address), 30) ?: 'لا يوجد عنوان' }} · {{ $d->parcels }} طرد</span></div><div class="a">@if ($d->toCollect() > 0.009)<b style="color:var(--down)">{{ $f($d->toCollect()) }}</b><span class="lbl">د.ل</span>@else<b style="color:var(--up);font-family:Somar;font-size:13px">مدفوعة</b>@endif</div></a>
    @empty
        <div class="empty2"><span>☕</span>لا توجد طلبيات لديك الآن</div>
    @endforelse

{{-- ===================== الطلبات ===================== --}}
@elseif ($tab === 'orders')
    @php $todayCollected = $done->where('status', 'delivered')->filter(fn ($d) => $d->delivered_at && $d->delivered_at->isToday())->sum(fn ($d) => $d->collected_cash + $d->collected_bank); @endphp
    <div class="hd"><div><h1>الطلبات</h1><div class="sub">@if ($seg === 'done')قبضت اليوم <b class="num" style="color:var(--up)">{{ $f($todayCollected) }}</b> د.ل@else المطلوب قبضه <b class="num" style="color:var(--down)">{{ $f($now->sum(fn ($d) => $d->toCollect())) }}</b> د.ل من {{ $now->count() }} طلبيات @endif</div></div></div>
    <div class="seg"><a href="?tab=orders" class="{{ $seg !== 'done' ? 'on' : '' }}"><b>{{ $now->count() }}</b>قيد التوصيل</a><a href="?tab=orders&seg=done" class="{{ $seg === 'done' ? 'on' : '' }}"><b>{{ $done->count() }}</b>تم التوصيل</a></div>
    @if ($seg !== 'done')
        @forelse ($now as $d)
            @php [$cc, $cl] = $chip[$d->status] ?? ['c-new', $d->statusLabel()]; @endphp
            <div class="oc {{ $d->status === 'problem' ? 'prob' : '' }}">
                <a class="top" href="?d={{ $d->id }}"><div><div class="nm">{{ $d->recipient_name }} @if (optional($d->customer)->code) <span style="background:#262c4a;color:#fff;border-radius:6px;padding:1px 7px;font-family:InterNum;font-size:.85em;font-weight:800">{{ $d->customer->code }}</span>@endif</div><div class="cd">{{ $d->code }} · {{ $d->recipient_phone }}</div></div><span class="chip {{ $cc }}"><i></i>{{ $cl }}</span></a>
                <div class="info"><div><b>{{ $d->parcels }}</b><span>طرود</span></div><div>@if ($d->toCollect() > 0.009)<b class="due">{{ $f($d->toCollect()) }}</b><span>المطلوب (د.ل)</span>@else<b class="paid">مدفوعة ✓</b><span>لا تقبض</span>@endif</div><div><b>{{ $d->orders->sum(fn ($o) => $o->received_qty ?? $o->qty) }}</b><span>قطع</span></div></div>
                @if ($d->problem)<div class="pb"><svg width="18" height="18"><use href="#i-warn"/></svg><div><b>{{ $d->problem }}</b>تم إبلاغ المكتب</div></div>@endif
                <div class="adr"><svg width="17" height="17" style="color:var(--ink-3);margin-top:2px"><use href="#i-pin"/></svg><span class="x">{{ trim($d->city . ' ' . $d->address) ?: 'لا يوجد عنوان — اتصل بالمستلم' }}</span>@if ($d->mapUrl())<a class="maplink" href="{{ $d->mapUrl() }}" target="_blank"><svg width="14" height="14"><use href="#i-map"/></svg>الموقع</a>@else<span class="maplink no">لا يوجد موقع</span>@endif</div>
                <div class="acts">
                    <a class="btn ghost sm sq" style="color:var(--wa)" href="https://wa.me/{{ $wa($d->recipient_phone) }}" target="_blank"><svg width="19" height="19"><use href="#i-wa"/></svg></a>
                    <a class="btn ghost sm sq" href="tel:{{ $d->recipient_phone }}"><svg width="17" height="17"><use href="#i-phone"/></svg></a>
                    <a class="btn red sm" href="?d={{ $d->id }}&s=problem">مشكلة</a>
                    <a class="btn green sm" href="?d={{ $d->id }}&s=done"><svg width="16" height="16"><use href="#i-check"/></svg>تم التسليم</a>
                </div>
            </div>
        @empty
            <div class="empty2"><span>☕</span>لا توجد طلبيات لديك الآن<br>عندما يسند المكتب طلبية إليك ستظهر هنا</div>
        @endforelse
    @else
        @foreach ($done->groupBy(fn ($d) => optional($d->delivered_at ?? $d->updated_at)->toDateString()) as $day => $g)
            <div class="day">{{ \Carbon\Carbon::parse($day)->isToday() ? 'اليوم' : (\Carbon\Carbon::parse($day)->isYesterday() ? 'أمس' : $day) }}</div>
            @foreach ($g as $d)
                @php [$cc, $cl] = $chip[$d->status] ?? ['c-new', $d->statusLabel()]; @endphp
                <div class="oc">
                    <div class="top"><div><div class="nm">{{ $d->recipient_name }} @if (optional($d->customer)->code) <span style="background:#262c4a;color:#fff;border-radius:6px;padding:1px 7px;font-family:InterNum;font-size:.85em;font-weight:800">{{ $d->customer->code }}</span>@endif</div><div class="cd">{{ $d->code }} · {{ $d->recipient_phone }}</div></div><span class="chip {{ $cc }}"><i></i>{{ $cl }}</span></div>
                    <div class="info"><div><b>{{ $d->parcels }}</b><span>طرود</span></div><div><b style="color:var(--up)">{{ $f($d->collected_cash + $d->collected_bank) }}</b><span>قبضت (د.ل)</span></div><div><b style="font-family:Somar;font-size:14px">{{ $d->collected_bank > 0 ? ($d->collected_cash > 0 ? 'نقدي ومصرف' : 'مصرف') : 'نقدي' }}</b><span>الدفع</span></div></div>
                    <div class="done-row"><span>{{ optional($d->delivered_at)->format('g:i a') }}{{ $d->settlement_id ? ' · تم إقفالها' : '' }}</span><span>عمولتك <b class="num" style="color:var(--ink)">{{ $f($d->courierFee()) }}</b> د.ل</span></div>
                </div>
            @endforeach
        @endforeach
        @if (!$done->count())<div class="empty2"><span>📭</span>لا توجد طلبيات مسلّمة بعد</div>@endif
    @endif

{{-- ===================== تفاصيل الطلبية ===================== --}}
@elseif ($tab === 'detail')
    @php $d = $sel; [$cc, $cl] = $chip[$d->status] ?? ['c-new', $d->statusLabel()]; $open = $d->isOpen(); $s = request('s'); @endphp
    <div class="back"><a class="icb" href="?tab=orders"><svg width="20" height="20"><use href="#i-back"/></svg></a><h1>طلبية <span class="num">{{ $d->code }}</span></h1><span class="chip {{ $cc }}"><i></i>{{ $cl }}</span></div>
    <div class="card mb">
        <div class="cust"><div class="av" style="background:var(--s7-bg);color:var(--s7)">{{ mb_substr($d->recipient_name, 0, 1) }}</div><div class="x"><b>{{ $d->recipient_name }} @if (optional($d->customer)->code) <span style="background:#262c4a;color:#fff;border-radius:6px;padding:1px 7px;font-family:InterNum;font-size:.85em;font-weight:800">{{ $d->customer->code }}</span>@endif</b><span>{{ $d->recipient_phone }}{{ $d->recipient_phone2 ? ' · ' . $d->recipient_phone2 : '' }}</span></div>
            <a class="call" style="background:var(--wa-bg);color:var(--wa)" href="https://wa.me/{{ $wa($d->recipient_phone) }}" target="_blank"><svg width="21" height="21"><use href="#i-wa"/></svg></a>
            <a class="call" style="background:var(--s1-bg);color:var(--s1)" href="tel:{{ $d->recipient_phone }}"><svg width="19" height="19"><use href="#i-phone"/></svg></a></div>
        @if ($d->mapUrl())
            <a class="mapbox" href="{{ $d->mapUrl() }}" target="_blank" style="display:block">
                <svg width="100%" height="118" style="position:absolute;inset:0"><path d="M-10 80 Q 90 60 180 75 T 600 50" stroke="#fff" stroke-width="10" fill="none"/><path d="M120 -10 L 150 130" stroke="#fff" stroke-width="8"/><path d="M250 -10 L 230 130" stroke="#fff" stroke-width="6"/><rect x="20" y="10" width="80" height="45" rx="6" fill="#dbe3d6"/></svg>
                <svg class="pin" width="34" height="34" style="color:var(--down)"><use href="#i-pin"/></svg>
                <span class="open"><svg width="14" height="14"><use href="#i-map"/></svg>فتح في خرائط Google</span>
            </a>
        @endif
        <div style="font-size:14px;color:var(--ink-2);margin-top:8px">📍 {{ trim($d->city . ' ' . $d->address) ?: 'لا يوجد عنوان — اتصل بالمستلم' }}</div>
    </div>
    @if ($d->note)<div class="note-c">📝 ملاحظة: {{ $d->note }}</div>@endif
    @if ($d->problem)<div class="note-c" style="background:var(--down-bg);color:var(--down)">⚠ {{ $d->problem }}</div>@endif
    <div class="card mb">
        <div class="kv" style="border:0"><span>الإيصالات</span><b class="n">{{ $d->orders->pluck('code')->implode(' · ') }}</b></div>
        <div class="kv"><span>عدد الطرود</span><b class="n">{{ $d->parcels }} {{ $d->package_type }}</b></div>
        <div class="kv"><span>عدد القطع</span><b class="n">{{ $d->orders->sum(fn ($o) => $o->received_qty ?? $o->qty) }}</b></div>
        <div class="kv"><span>طريقة الدفع</span><b>{{ \App\Models\Delivery::PAY_TYPES[$d->pay_type] ?? '' }}</b></div>
        @if ($me->courierCan('see_amount'))
            <div class="kv"><span>قيمة الطلبيات</span><b class="n">{{ $f($d->amount) }} د.ل</b></div>
            <div class="kv"><span>أجرة التوصيل (عمولتك){{ $d->companyPays() ? ' · على الشركة، لا تطلبها من العميل' : '' }}</span><b class="n">{{ $f($d->fee) }} د.ل</b></div>
            <div class="collect"><span>المطلوب قبضه من العميل</span><b>{{ $f($d->toCollect()) }} <small style="font-size:14px;font-family:Somar">د.ل</small></b></div>
        @endif
    </div>
    @if ($open)
        @if ($d->status === 'assigned')
            <form method="post" action="{{ route('courier.out', $d->id) }}" class="mb">@csrf<button class="btn" style="width:100%;height:50px">📦 استلمت الطلبية — قيد التوصيل 🚚</button></form>
        @endif
        <form class="sheet {{ $s === 'done' ? 'on' : '' }}" id="shDone" method="post" action="{{ route('courier.done', $d->id) }}" enctype="multipart/form-data">
            @csrf
            <h3 style="margin:0 0 10px;font-size:16px">تأكيد التسليم</h3>
            <div class="fl"><label>المبلغ الذي قبضته نقداً</label><input name="cash" id="cashIn" class="inp" style="height:52px;font-family:InterNum;font-size:20px;font-weight:800" inputmode="decimal" value="{{ in_array($d->pay_type, ['bank', 'paid']) ? round($d->customerFee(), 2) : round($d->toCollect(), 2) }}" data-due="{{ round($d->toCollect() - ($d->pay_type === 'bank' ? $d->amount : 0), 2) }}"></div>
            @if ($me->courierCan('collect_bank'))<div class="fl"><label>تحويل مصرفي</label><input name="bank" id="bankIn" class="inp" inputmode="decimal" value="{{ $d->pay_type === 'bank' ? round($d->amount, 2) : 0 }}"></div>@endif
            <div id="shortBox" class="note-c" style="display:none;background:var(--s1-bg);color:#1d4f8f">
                قبضت أقل من المطلوب بفرق <b class="num" id="shortV">0</b> د.ل.
                <label class="ck2" style="color:#1d4f8f"><input type="checkbox" name="writeoff" value="1" id="woChk"> العميل قرّب المبلغ — يُشطب الفرق على الشركة (حتى {{ $f($writeoffMax) }} د.ل) أو يُحسب عليك</label>
            </div>
            <input name="note" class="inp" placeholder="ملاحظة (مثال: استلمها شقيقه)">
            <label style="display:flex;align-items:center;gap:10px;margin-top:10px;padding:12px;border:1.5px dashed var(--line);border-radius:12px;cursor:pointer;font-size:14px">
                <span style="font-size:24px">📸</span><span style="flex:1"><b>صوّر الطرد</b> وقت التسليم<br><small class="note" id="pfN">اختياري — يحميك لو صار خلاف</small></span>
                <input type="file" name="proof" accept="image/*" capture="environment" hidden onchange="document.getElementById('pfN').textContent=this.files.length?'✓ الصورة جاهزة':'اختياري — يحميك لو صار خلاف'">
            </label>
            <button class="btn green" style="width:100%;height:50px;margin-top:10px"><svg width="18" height="18"><use href="#i-check"/></svg>تأكيد التسليم</button>
        </form>
        <form class="sheet {{ $s === 'problem' ? 'on' : '' }}" id="shProb" method="post" action="{{ route('courier.problem', $d->id) }}">
            @csrf
            <h3 style="margin:0 0 10px;font-size:16px">الإبلاغ عن مشكلة</h3>
            <div class="radio2">@foreach (['العميل لا يرد على الهاتف', 'العنوان غير صحيح', 'العميل رفض الاستلام', 'العميل طلب التأجيل', 'المبلغ غير مكتمل لدى العميل'] as $p)<label><input type="radio" name="q" onclick="this.form.note.value='{{ $p }}'"><span>{{ $p }}</span></label>@endforeach</div>
            <textarea name="note" class="inp" rows="2" placeholder="اكتب ما حدث…" required></textarea>
            <button class="btn" style="width:100%;height:48px;margin-top:10px;background:var(--down)">إرسال للمكتب</button>
        </form>
        <details class="card mb" style="margin-top:10px"><summary class="lbl" style="color:var(--down)">↩ إلغاء التوصيل (ترجع للشركة)</summary>
            <form method="post" action="{{ route('courier.cancel', $d->id) }}" style="margin-top:10px" onsubmit="return confirm('متأكد؟ الطلبية ترجع للشركة وتقعد في الرف متاعها')">@csrf
                <input name="note" class="inp" style="height:46px" placeholder="السبب (مثلاً: العميل ما ردش، أجّل…)" required>
                <button class="btn" style="width:100%;height:46px;margin-top:8px;background:var(--down)">↩ ألغِ ورجّعها للشركة</button>
            </form>
        </details>
        @if ($me->courierCan('edit_recipient'))
            <details class="card mb" style="margin-top:10px"><summary class="lbl">✏ تعديل رقم أو عنوان المستلم</summary>
                <form method="post" action="{{ route('courier.recipient', $d->id) }}" style="margin-top:10px">@csrf
                    <input name="recipient_phone" class="inp mb" style="height:46px;margin-bottom:8px" value="{{ $d->recipient_phone }}"><input name="recipient_phone2" class="inp" style="height:46px;margin-bottom:8px" value="{{ $d->recipient_phone2 }}" placeholder="رقم ثانٍ">
                    <input name="address" class="inp" style="height:46px;margin-bottom:8px" value="{{ $d->address }}" placeholder="العنوان"><input name="location_url" class="inp" style="height:46px;direction:ltr" value="{{ $d->location_url }}" placeholder="رابط الموقع">
                    <button class="btn ghost" style="width:100%;margin-top:8px">حفظ</button>
                </form>
            </details>
        @endif
        <div style="height:80px"></div>
    @endif

{{-- ===================== المالية ===================== --}}
@elseif ($tab === 'money')
    <div class="hd"><div><h1>المالية</h1><div class="sub">{{ $settlements->first() ? 'منذ آخر إقفال · ' . $settlements->first()->created_at->format('Y-m-d') : 'منذ بداية العمل' }}</div></div></div>
    @if ($cr)<div class="note-c" style="background:var(--s1-bg);color:#1d4f8f">📨 أرسلت طلب إقفال بمبلغ <b class="num">{{ $f($cr['amount']) }}</b> د.ل — بانتظار تأكيد المكتب</div>@endif
    <div class="big">
        <div class="t">المطلوب تسليمه للمكتب</div>
        <div class="v">{{ $f(floor($exp)) }} <small>د.ل</small></div>
        <div class="ln"><span class="l"><span class="d" style="background:var(--up)"></span>قبضت نقداً من العملاء</span><b>{{ $f($bal['cash']) }}</b></div>
        <div class="ln"><span class="l"><span class="d" style="background:var(--s7)"></span>عمولتك ({{ $bal['count'] }} طلبية)</span><b style="color:var(--s7)">−{{ $f($bal['fees']) }}</b></div>
        @if (abs($bal['carry']) >= 0.01)<div class="ln"><span class="l"><span class="d" style="background:var(--warn)"></span>من الإقفال السابق</span><b>{{ $f($bal['carry']) }}</b></div>@endif
        @if ($frac > 0.009)<div class="ln"><span class="l"><span class="d" style="background:var(--warn)"></span>كسور تُشطب (أقل من دينار)</span><b style="color:var(--warn)">−{{ $f($frac) }}</b></div>@endif
        <div class="ln tot"><span>الصافي</span><b>{{ $f(floor($exp)) }} د.ل</b></div>
        <a class="btn" style="margin-top:8px;width:100%" href="?tab=close">إقفال الحساب</a>
    </div>
    <div class="k2">
        <div class="kp"><div class="t" style="margin:0 0 6px">قبضت عبر المصرف</div><div class="v" style="font-size:21px">{{ $f($bal['bank']) }} <small style="font-size:12px;font-family:Somar;font-weight:400;color:var(--ink-3)">د.ل</small></div><div class="lbl" style="margin-top:4px">ليست معك · ذهبت للمكتب</div></div>
        <div class="kp"><div class="t" style="margin:0 0 6px">عمولتك هذا الشهر</div><div class="v" style="font-size:21px;color:var(--s7)">{{ $f($monthFees) }} <small style="font-size:12px;font-family:Somar;font-weight:400;color:var(--ink-3)">د.ل</small></div><div class="lbl" style="margin-top:4px">{{ $monthDone }} طلبية</div></div>
    </div>
    <div class="card hist" style="padding:12px 14px">
        <div class="sec" style="margin:0 0 6px"><h3>آخر الحركات</h3></div>
        @foreach ($done->where('status', 'delivered')->take(5) as $d)
            <div class="row"><span class="ic" style="background:var(--up-bg);color:var(--up)"><svg width="18" height="18"><use href="#i-cash"/></svg></span><div class="m"><b>قبض · {{ $d->recipient_name }} @if (optional($d->customer)->code) <span style="background:#262c4a;color:#fff;border-radius:6px;padding:1px 7px;font-family:InterNum;font-size:.85em;font-weight:800">{{ $d->customer->code }}</span>@endif</b><span>{{ optional($d->delivered_at)->format('Y-m-d g:i a') }} · {{ $d->collected_bank > 0 ? 'مصرف' : 'نقدي' }}</span></div><span class="a" style="color:var(--up)">+{{ $f($d->collected_cash + $d->collected_bank) }}</span></div>
        @endforeach
        @foreach ($settlements->take(3) as $st)
            <div class="row"><span class="ic" style="background:var(--s1-bg);color:var(--s1)"><svg width="18" height="18"><use href="#i-wallet"/></svg></span><div class="m"><b>إقفال حساب #{{ $st->id }}</b><span>{{ $st->created_at->format('Y-m-d') }} · {{ $st->deliveries_count }} طلبية{{ $st->rounding > 0 ? ' · كسور ' . $f($st->rounding) : '' }}</span></div><span class="a">−{{ $f($st->received) }}</span></div>
        @endforeach
    </div>

{{-- ===================== إقفال الحساب ===================== --}}
@elseif ($tab === 'close')
    <div class="back"><a class="icb" href="?tab=money"><svg width="20" height="20"><use href="#i-back"/></svg></a><h1>إقفال الحساب</h1></div>
    <div class="steps"><i class="on"></i><i class="on"></i><i></i></div>
    <form method="post" action="{{ route('courier.close') }}" id="closeF">
        @csrf
        <div class="card mb">
            <div class="lbl">{{ $settlements->first() ? 'من ' . $settlements->first()->created_at->format('Y-m-d') : 'منذ البداية' }} حتى اليوم · {{ $bal['count'] }} طلبية</div>
            <div class="ln" style="border:0"><span class="l">قبضت نقداً</span><b>{{ number_format($bal['cash'], 2) }}</b></div>
            <div class="ln"><span class="l">عمولتك</span><b style="color:var(--s7)">−{{ number_format($bal['fees'], 2) }}</b></div>
            @if (abs($bal['carry']) >= 0.01)<div class="ln"><span class="l">من الإقفال السابق</span><b>{{ number_format($bal['carry'], 2) }}</b></div>@endif
            <div class="ln"><span class="l">المطلوب بالضبط</span><b>{{ number_format($exp, 2) }}</b></div>
            @if ($frac > 0.009)<div class="ln"><span class="l">كسور تُشطب (الدراهم تحت الدينار)</span><b style="color:var(--warn)">−{{ number_format($frac, 2) }}</b></div>@endif
            <div class="ln tot"><span>تسلّم للمكتب</span><b>{{ $f(floor($exp)) }} د.ل</b></div>
            <div class="cnt">
                <div style="font-size:13.5px;font-weight:700;margin-bottom:8px">كم عددت من المال الذي معك؟</div>
                <div class="inp focus" style="justify-content:space-between;padding:0"><input name="amount" id="cntIn" class="num" value="{{ floor($exp) }}" inputmode="decimal" style="border:0;outline:0;background:none;font-family:InterNum;font-size:22px;font-weight:800;width:100%;padding:0 14px;height:52px"><span style="font-family:Somar;font-size:14px;font-weight:400;color:var(--ink-3);padding:0 14px">د.ل</span></div>
                <div class="diff" id="cntDiff" data-exp="{{ floor($exp) }}"><span>✓ مطابق</span><span>الفرق 0</span></div>
            </div>
            <div class="fl" style="margin:0"><label>سلّمتها إلى</label><select name="to" class="inp" style="height:50px">@foreach ($staff as $u)<option>{{ $u->name }}</option>@endforeach</select></div>
            <input name="note" class="inp" style="height:46px;margin-top:10px" placeholder="ملاحظة (اختياري)">
        </div>
        <div class="note-c" style="background:var(--s1-bg);color:var(--s1)">ℹ بعد التأكيد يصل إشعار للمكتب، ويُقفل الحساب عند تأكيدهم الاستلام</div>
        <div style="height:80px"></div>
        <div class="bar"><button class="btn" style="background:var(--brand);flex:1;height:52px">تأكيد وتسليم <span id="cntBtn" class="num">{{ $f(floor($exp)) }}</span> د.ل</button></div>
    </form>

{{-- ===================== الإشعارات ===================== --}}
@elseif ($tab === 'notes')
    <div class="hd"><div><h1>الإشعارات</h1><div class="sub">ما يصلك من المكتب على طلبياتك</div></div></div>
    @foreach ($notes->groupBy(fn ($n) => $n->created_at->toDateString()) as $day => $g)
        <div class="day">{{ \Carbon\Carbon::parse($day)->isToday() ? 'اليوم' : (\Carbon\Carbon::parse($day)->isYesterday() ? 'أمس' : $day) }}</div>
        @foreach ($g as $n)
            @php [$ic, $tt, $cc] = $evLabel[$n->status] ?? ['🔔', 'تحديث', 'c-new']; @endphp
            <a class="nt {{ $n->created_at->gt(now()->subHours(6)) ? 'unread' : '' }}" href="?d={{ $n->delivery_id }}"><span class="ic {{ $cc }}" style="font-size:18px">{{ $ic }}</span><div class="m"><b>{{ $tt }} · <span class="num">{{ $codes[$n->delivery_id] ?? '' }}</span></b><p>{{ $n->note ?: '—' }}</p><span>{{ $n->created_at->diffForHumans() }}{{ $n->user ? ' · ' . $n->user->name : '' }}</span></div></a>
        @endforeach
    @endforeach
    @if (!$notes->count())<div class="empty2"><span>🔕</span>لا توجد إشعارات</div>@endif

{{-- ===================== الإعدادات ===================== --}}
@else
    <div class="hd"><div><h1>الإعدادات</h1></div></div>
    <div class="prof"><div class="av">{{ mb_substr($me->name, 0, 1) }}</div><div style="flex:1"><b>{{ $me->name }}</b><span class="num" style="display:block;text-align:right">{{ $me->email }}</span><span>المنطقة: {{ $me->courier_zone ?: '—' }}</span></div></div>
    <div class="gl">الحساب</div>
    <div class="grp">
        <div class="it"><span class="ic"><svg width="18" height="18"><use href="#i-user"/></svg></span><span class="x">البيانات الشخصية<span>{{ $me->phone ?? '' }} · تُعدَّل من المكتب</span></span></div>
        <details class="it" style="display:block"><summary style="display:flex;align-items:center;gap:12px;list-style:none"><span class="ic"><svg width="18" height="18"><use href="#i-lock"/></svg></span><span class="x">تغيير كلمة المرور</span><svg width="16" height="16" style="color:var(--ink-3)"><use href="#i-chev"/></svg></summary>
            <form method="post" action="{{ route('courier.password') }}" style="margin-top:10px">@csrf
                <input type="password" name="current" class="inp" style="height:46px;margin-bottom:8px" placeholder="كلمة المرور الحالية" required>
                <input type="password" name="new" class="inp" style="height:46px;margin-bottom:8px" placeholder="كلمة المرور الجديدة" required>
                <input type="password" name="new_confirmation" class="inp" style="height:46px;margin-bottom:8px" placeholder="تأكيد كلمة المرور" required>
                <button class="btn" style="width:100%">حفظ</button>
            </form>
        </details>
        <form method="post" action="{{ route('courier.available') }}" class="it">@csrf<span class="ic"><svg width="18" height="18"><use href="#i-truck"/></svg></span><span class="x">متاح للتوصيل اليوم<span>يستطيع المكتب إسناد طلبيات إليك</span></span><button class="tg {{ $me->courier_available ?? true ? 'on' : '' }}" style="border:0;cursor:pointer"></button></form>
    </div>
    <div class="gl">التطبيق</div>
    <div class="grp">
        <div class="it"><span class="ic"><svg width="18" height="18"><use href="#i-map"/></svg></span><span class="x">فتح المواقع في</span><span class="r">خرائط Google</span></div>
        <form method="post" action="{{ route('admin.logout') }}" class="it" style="color:var(--down)">@csrf<span class="ic" style="background:var(--down-bg);color:var(--down)"><svg width="18" height="18"><use href="#i-out"/></svg></span><button class="x" style="border:0;background:none;color:var(--down);text-align:right;font-size:14.5px;padding:0;cursor:pointer">تسجيل الخروج</button></form>
    </div>
@endif
</div>

@if (!in_array($tab, ['detail', 'close']))
<nav class="tabs">
    <a href="?tab=home" class="{{ $tab === 'home' ? 'on' : '' }}"><svg width="23" height="23"><use href="#i-home"/></svg>الرئيسية</a>
    <a href="?tab=orders" class="{{ $tab === 'orders' ? 'on' : '' }}"><svg width="23" height="23"><use href="#i-box"/></svg>الطلبات@if ($now->count())<span class="bd">{{ $now->count() }}</span>@endif</a>
    <a href="?tab=money" class="{{ $tab === 'money' ? 'on' : '' }}"><svg width="23" height="23"><use href="#i-wallet"/></svg>المالية</a>
    <a href="?tab=notes" class="{{ $tab === 'notes' ? 'on' : '' }}"><svg width="23" height="23"><use href="#i-bell"/></svg>الإشعارات</a>
    <a href="?tab=settings" class="{{ $tab === 'settings' ? 'on' : '' }}"><svg width="23" height="23"><use href="#i-gear"/></svg>الإعدادات</a>
</nav>
@elseif ($tab === 'detail' && $sel->isOpen())
<div class="bar"><button class="btn red" onclick="sh('shProb')">⚠ توجد مشكلة</button><button class="btn green" onclick="sh('shDone')"><svg width="18" height="18"><use href="#i-check"/></svg>تم التسليم</button></div>
@endif
</div>
<script>
function sh(id) { document.querySelectorAll('.sheet').forEach(s => { if (s.id !== id) s.classList.remove('on'); }); const e = document.getElementById(id); e.classList.add('on'); e.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
(function () {
  const c = document.getElementById('cashIn');
  if (c) { const b = document.getElementById('bankIn'), box = document.getElementById('shortBox');
    const upd = () => { const due = parseFloat(c.dataset.due) || 0, got = (parseFloat(c.value) || 0) + (b ? 0 : 0); const s = Math.round((due - got) * 100) / 100;
      box.style.display = s > 0.009 ? 'block' : 'none'; document.getElementById('shortV').textContent = s.toFixed(2); if (s <= 0.009) document.getElementById('woChk').checked = false; };
    c.addEventListener('input', upd); upd(); }
  const n = document.getElementById('cntIn');
  if (n) { const d = document.getElementById('cntDiff'), exp = parseFloat(d.dataset.exp);
    const upd = () => { const v = parseFloat(n.value) || 0, x = Math.round((v - exp) * 100) / 100; d.style.color = Math.abs(x) < 0.01 ? 'var(--up)' : 'var(--down)';
      d.innerHTML = '<span>' + (Math.abs(x) < 0.01 ? '✓ مطابق' : (x < 0 ? '⚠ ناقص' : '⚠ زائد')) + '</span><span>الفرق ' + Math.abs(x).toFixed(2) + '</span>'; document.getElementById('cntBtn').textContent = v.toLocaleString('en-US'); };
    n.addEventListener('input', upd); upd(); }
  // تحديث تلقائي كل دقيقة في الرئيسية والطلبات
  if (['home', 'orders'].includes(@json($tab)) ) setInterval(() => { if (document.visibilityState === 'visible') location.reload(); }, 60000);
  // تنبيه فوري لما تجي توصيلة جديدة: صوت + اهتزاز + رسالة
  let last = {{ (int) $lastAssigned }};
  const ding = () => { try { const c = new (window.AudioContext || window.webkitAudioContext)(); [880, 660, 990].forEach((f, i) => { const o = c.createOscillator(), g = c.createGain(); o.connect(g); g.connect(c.destination); o.frequency.value = f; g.gain.value = .15; o.start(c.currentTime + i * .18); o.stop(c.currentTime + i * .18 + .16); }); } catch (e) {} };
  setInterval(() => fetch(@json(route('courier.ping')), { headers: { Accept: 'application/json' } }).then(r => r.json()).then(j => {
    if (j.last > last) { last = j.last; ding(); if (navigator.vibrate) navigator.vibrate([300, 120, 300]);
      const t = document.createElement('a'); t.href = '?tab=orders'; t.style.cssText = 'position:fixed;top:14px;left:14px;right:14px;z-index:99;background:#1f7a4d;color:#fff;padding:14px 16px;border-radius:14px;font-weight:700;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.25);text-decoration:none';
      t.textContent = '🛵 عندك توصيلة جديدة! اضغط هنا'; document.body.appendChild(t); setTimeout(() => location.reload(), 8000); }
  }).catch(() => {}), 20000);
})();
</script>
</body>
</html>
