<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Shop & Go')</title>
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/sg/sg.css') }}?v=6">
    @stack('style')
    <style>
    /* الجداول العريضة ما تدفّش الصفحة تحت القائمة: عنصر الشبكة يقدر يصغر والجدول يتمرر داخله */
    .side-lay > *, .grid > *, .kpis > *, .sg-main > * { min-width: 0; }
    .sg-main { min-width: 0; }
    .scroll-x { overflow-x: auto; max-width: 100%; }
    /* الجدول ما يدخلش تحت المربعات اللي على الجنب: يتمرر داخل مكانه، وفي الشاشات الأصغر المربعات تنزل تحت */
    .side-lay > .card.tw, .side-lay > .tw { overflow-x: auto; }
    @media (max-width: 1350px) { .side-lay:not(.l) { grid-template-columns: 1fr !important; } }
    /* كتابة أوضح: أسود وعريض بدل الرمادي (خانات البحث: التلميح رمادي، واللي تكتبه أسود) */
    :root { --ink: #000; --ink-2: #111; --ink-3: #222; }
    body, .sg-main { color: #000; }
    .sg-main { font-weight: 600; }
    .sg-main h1, .sg-main h2, .sg-main h3, .sg-main b, .sg-main strong, .sg-main th { font-weight: 800; }
    .note, .muted, .sub, .hint, small, .t th, .l, label, .cap, .sg-side .it { color: #111; }
    .sg-main .t td { font-size: 15px; line-height: 1.6; }
    .sg-main .note, .sg-main small { font-size: max(13.5px, .95em); }
    .sg-main .sub { font-size: 15px; }
    .sg-side .it { font-size: 15px; font-weight: 700; }
    input, select, textarea, .inp, .pill { color: #000 !important; font-weight: 700; }
    input::placeholder, textarea::placeholder { color: #9aa0b4 !important; font-weight: 500; opacity: 1; }
    /* خط أكبر وأوضح في كل الواجهات */
    .sg-main { font-size: 16px; }
    .sg-main .t td { font-size: 16px; }
    .sg-main .t th { font-size: 14.5px; }
    .sg-main .note, .sg-main small { font-size: max(14px, .95em); }
    .sg-main .btn { font-size: max(14.5px, 1em); }
    .sg-main .inp { font-size: max(15px, 1em); }
    .sg-side .it { font-size: 16px; }
    /* (التكبير بـ zoom كان يدفع الجداول تحت القائمة — لغيناه وكبرنا الخط بداله) */
    .sg-main { font-size: 16.5px; }
    /* الجداول: الأسماء أكبر، الأرقام أصغر شوية، والأزرار أصغر */
    .sg-main .t td b, .sg-main .t .cell2 b { font-size: 17px; }
    .sg-main .t td .num, .sg-main .t td small.num, .sg-main .t td .note.num { font-size: 14.5px; }
    .sg-main .t td b.num, .sg-main .t td .num b { font-size: 16px; }
    .sg-main .t .btn { font-size: 13px !important; padding: 5px 11px !important; min-height: 0; border-radius: 10px; }
    .sg-main .t .inp { height: 34px !important; font-size: 14px !important; }
    </style>
</head>
<body class="@yield('bodyClass', 'soft'){{ request('embed') ? ' embed' : '' }}">
@if (request('embed'))<style>/* 🪟 داخل نافذة: بدون القائمة والأعلى */ .embed .sg-side, .embed .bell-wrap, .embed .sg-burger, .embed .sg-back, .embed .no-embed { display: none !important; } .embed .sg-app { display: block; } .embed .sg-main { margin: 0 !important; padding: 14px !important; max-width: none !important; }</style>@endif
@php
    $nav = $nav ?? '';
    $u = auth()->user();
    $readyCount = \Illuminate\Support\Facades\Cache::remember('sg.badge.ready', 60, fn () => \App\Models\Order::where('arrived', true)->where('delivered', false)->whereNull('delivery_id')->where('status', '!=', 'canceld')->count());
    $courierBadge = \App\Support\Fast::hasTable('deliveries') ? \Illuminate\Support\Facades\Cache::remember('sg.badge.courier', 30, fn () => \App\Models\Delivery::whereIn('status', ['requested', 'problem'])->count()) : 0;
    $signupBadge = \App\Support\Fast::hasColumn('customers', 'signup_status') ? \Illuminate\Support\Facades\Cache::remember('sg.badge.signups', 30, fn () => \App\Models\Customer::withoutGlobalScope('approved')->where('signup_status', 'pending')->count()) : 0;
    $ticketBadge = \App\Support\Fast::hasTable('support_tickets') ? \Illuminate\Support\Facades\Cache::remember('sg.badge.tickets', 30, fn () => \Illuminate\Support\Facades\DB::table('support_tickets')->where('status', 'open')->count()) : 0;
    $icons = [
        'home' => '<path d="M3 12l9-8 9 8"/><path d="M5 10v10h14V10"/>',
        'ord' => '<path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/>',
        'trip' => '<path d="M2 16l20-8-6 12-3-5-5-1z"/>',
        'del' => '<path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M8 12l3 3 5-6"/>',
        'courier' => '<path d="M1 7h13v10H1zM14 10h4l3 3v4h-7"/><circle cx="5" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
        'dispatch' => '<path d="M4 4h12v4H4zM4 10h16M4 14h16M4 18h10"/>',
        'msg' => '<path d="M4 5h16v11H8l-4 4z"/>',
        'shein' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h5"/>',
        'cust' => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6"/>',
        'cart' => '<path d="M4 6h16l-2 9H6L4 6z"/><circle cx="9" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/>',
        'pcart' => '<path d="M4 6h16l-2 9H6L4 6z"/><path d="M10 10l2 2 4-4"/>',
        'bqueue' => '<path d="M9 5h11M9 12h11M9 19h11"/><path d="M4 5l1 1 2-2M4 12l1 1 2-2M4 19l1 1 2-2"/>',
        'fin' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>',
        'exp' => '<path d="M6 3h12v18H6z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'debt' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
        'rep' => '<path d="M6 3h9l4 4v14H6z"/><path d="M9 13h7M9 17h5"/>',
        'staff' => '<circle cx="9" cy="8" r="3.5"/><circle cx="17" cy="9" r="2.5"/><path d="M2 20c0-3.5 3-5.5 7-5.5s7 2 7 5.5M16 14.5c3 0 6 1.5 6 5"/>',
        'set' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>',
        'old' => '<path d="M4 4h16v16H4z"/><path d="M4 9h16"/>',
        'ship' => '<path d="M3 7h12v9H3z"/><path d="M15 10h4l2 3v3h-6"/><circle cx="7" cy="17.5" r="1.6"/><circle cx="17" cy="17.5" r="1.6"/><path d="M6 4h6"/>',
        'signup' => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6 1.6 0 3 .3 4.2 1"/><path d="M18 14v6M15 17h6"/>',
'offers' => '<path d="M20 12v9H4v-9"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/>',
'camp' => '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1z"/><path d="M15 9a4 4 0 0 1 0 6"/><path d="M18 6a8 8 0 0 1 0 12"/>',
'visits' => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
'alerts' => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'tick' => '<path d="M4 5h16v11H8l-4 4z"/><path d="M12 8v3M12 13.5h.01"/>',
        'ret' => '<path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/>',
        'audit' => '<path d="M12 8v4l3 2"/><path d="M3.05 11a9 9 0 1 1 .5 4"/><path d="M3 4v5h5"/>',
        'backup' => '<path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/>',
        'stores' => '<path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/>',
    ];
    $items = [
        ['home', 'الرئيسية', route('admin.home'), null],
        ['ord', 'الطلبات', route('sg.orders.index'), 'order-list'],
        ['trip', 'الرحلات', route('sg.trips.index'), 'trip-purchase-list'],
        ['ship', 'الشحن', route('sg.shipping'), 'shipping-list'],
        ['shein', 'بطاقات SHEIN', route('sg.shein'), 'shein-card-list'],
        ['del', 'التسليم', route('sg.delivery'), 'delivery-list', $readyCount],
        ['courier', 'المندوبين', route('sg.couriers'), 'courier-list', $courierBadge],
        ['dispatch', 'طلبيات دليفري', \Illuminate\Support\Facades\Route::has('sg.dispatch') ? route('sg.dispatch') : '#', 'courier-list'],
        ['cust', 'العملاء', route('sg.clients'), 'customer-list'],
        ['signup', 'طلبات التسجيل', route('sg.signups'), 'customer-create', $signupBadge ?? 0],
        ['cart', 'السلات', route('sg.carts'), 'cart-list'],
        ['pcart', 'تأكيد الحجوزات', route('sg.pcarts'), 'cart-list', \App\Support\Fast::hasColumn('cart_quotes', 'portal_status') ? \Illuminate\Support\Facades\Cache::remember('sg.badge.pcart', 30, fn () => \App\Models\CartQuote::where('portal_status', 'ordered')->where(fn ($w) => $w->whereNotIn('id', \App\Models\Delivery::whereNotNull('cart_id')->whereIn('status', ['requested', 'assigned', 'out', 'problem'])->pluck('cart_id')))->count()) : 0],
        ['bqueue', 'قائمة الحجز', route('sg.bqueue'), 'cart-list', \App\Support\Fast::hasColumn('cart_quotes', 'booked_done_at') ? \Illuminate\Support\Facades\Cache::remember('sg.badge.bqueue', 30, fn () => \App\Models\CartQuote::whereNotNull('customer_id')->where('portal_status', 'confirmed')->whereNull('booked_done_at')->count()) : 0],
        ['fin', 'المالية', route('sg.finance'), 'treasury-list'],
        ['exp', 'المصروفات', route('sg.expenses'), 'purchase-list'],
        ['debt', 'الديون', route('sg.debts'), 'dept-list'],
        ['ret', 'طلبيات مسترجعة', route('sg.returns'), 'return-list'],
        ['tick', 'بلاغات العملاء', route('sg.tickets'), 'ticket-list', $ticketBadge ?? 0],
        ['offers', 'العروض والكوبونات', route('sg.offers'), 'offer-manage'],
        ['alerts', 'مركز التنبيهات', route('sg.alerts.center'), null],
        ['rep', 'التقارير', route('sg.reports'), 'reports-list'],
        ['visits', 'الزيارات', route('sg.visits'), 'reports-list'],
        ['camp', 'حملات واتساب', route('sg.campaigns'), 'system-edit'],
        ['-', 'النظام'],
        ['staff', 'الموظفين', route('sg.staff'), 'user-list'],
        ['msg', 'رسائل الواتساب', route('sg.messages'), 'whatsapp-templates'],
        ['audit', 'سجل التعديلات', route('sg.audit'), 'system-edit'],
        ['backup', 'النسخ الاحتياطي', route('sg.backups'), 'system-edit'],
        ['stores', 'إعدادات المتاجر', route('settings.edit'), 'system-list', null, 'إعدادات شي إن وآي هيرب وأمازون · سعر الصرف USD → LYD'],
        ['set', 'الإعدادات', route('sg.settings'), 'system-edit'],
    ];
@endphp
<div class="sg-app">
    <aside class="sg-side">
        <a href="{{ route('admin.home') }}"><img class="logo" src="{{ asset('assets/sg/logo.png') }}" alt="Shop and Go"></a>
        @role('Admin')
        @php
            $rCart = (float) (\App\Models\Setting::query()->value('usd_to_lyd_rate') ?: 0);
            $rBuy = (float) (\App\Models\CurrencyExchange::latestRate() ?: 0);
            $rAt = \App\Services\AppSettings::get('rate_updated_at');
            $rOld = !$rAt || \Carbon\Carbon::parse($rAt)->lt(now()->subDay());
            $rLow = $rBuy > 0 && $rCart > 0 && $rCart < $rBuy;
        @endphp
        <button type="button" data-modal="mRate" style="border:0;cursor:pointer;border-radius:12px;padding:9px 12px;width:100%;margin:6px 0 10px;text-align:center;font:inherit;font-weight:700;font-size:13.5px;box-shadow:0 2px 8px rgba(0,0,0,.08);background:{{ $rOld || $rLow ? '#fdecea' : '#fff' }};color:{{ $rOld || $rLow ? '#b3261e' : '#262c4a' }}">💲 الدولار <span class="num">{{ rtrim(rtrim(number_format($rCart, 3), '0'), '.') }}</span>{{ $rOld ? ' · حدّثه!' : ($rLow ? ' · أقل من الشراء!' : '') }}</button>
        @endrole
        @foreach ($items as $it)
            @if ($it[0] === '-')
                <div class="cap">{{ $it[1] }}</div>
            @elseif (!$it[3] || ($u && $u->can($it[3])))
                <a class="it {{ $nav === $it[0] ? 'on' : '' }}" href="{{ $it[2] }}" @if (!empty($it[5])) title="{{ $it[5] }}" @endif>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $icons[$it[0]] ?? '' !!}</svg>{{ $it[1] }}
                    @if (!empty($it[4]))<span class="bd">{{ $it[4] }}</span>@endif
                </a>
            @endif
        @endforeach

        @if ($u)
            <div class="me">
                <span class="av" style="width:32px;height:32px;font-size:13px;background:var(--brand)">{{ mb_substr($u->name, 0, 1) }}</span>
                <div style="flex:1;min-width:0"><b style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $u->name }}</b><span class="note">{{ optional($u->roles->first())->name }}</span></div>
                <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="btn ghost sm" title="خروج">⎋</button></form>
            </div>
        @endif
    </aside>
    <main class="sg-main">
        {{-- أي موظف: بلّغ الإدارة على كود --}}
        <button type="button" class="no-print" data-modal="mFlag" title="بلّغ الإدارة" style="position:fixed;bottom:18px;left:18px;z-index:40;border:0;cursor:pointer;border-radius:99px;padding:12px 16px;font:inherit;font-weight:700;font-size:13.5px;background:#262c4a;color:#fff;box-shadow:0 6px 18px rgba(0,0,0,.18)">⚑ بلّغ الإدارة</button>
        <div class="modal-bg" id="mFlag"><div class="modal" style="max-width:440px">
            <h3>⚑ بلّغ الإدارة</h3>
            <div class="note mb">زبون اشتكى من نواقص أو أي حاجة؟ اكتب الكود والإدارة يوصلها تنبيه، ويتسجل في «المشاكل».</div>
            <form method="post" action="{{ route('sg.flag') }}">@csrf
                <div class="f"><label class="l">رقم الإيصال أو كود العميل</label><input name="code" class="inp numr" required></div>
                <div class="f"><div class="opts" style="flex-wrap:wrap">@foreach (\App\Models\OrderIssue::TYPES as $k => [$t, $ic])<label><input type="radio" name="type" value="{{ $k }}" @checked($loop->first)><span>{{ $ic }} {{ $t }}</span></label>@endforeach</div></div>
                <div class="f"><label class="l">كم قطعة؟ <span class="note">(لو نواقص)</span></label><input name="qty" class="inp numr" inputmode="numeric"></div>
                <div class="f"><label class="l">شن المشكلة؟</label><textarea name="note" class="inp" rows="3" required placeholder="مثلاً: الزبون يقول ناقصته قطعتين من الفساتين"></textarea></div>
                <div style="display:flex;gap:8px"><button class="btn" style="flex:1">إرسال للإدارة</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
            </form>
        </div></div>
        @role('Admin')
        @php
            $rCart = (float) (\App\Models\Setting::query()->value('usd_to_lyd_rate') ?: 0);
            $rBuy = (float) (\App\Models\CurrencyExchange::latestRate() ?: 0);
            $rAt = \App\Services\AppSettings::get('rate_updated_at');
            $rOld = !$rAt || \Carbon\Carbon::parse($rAt)->lt(now()->subDay());
            $rLow = $rBuy > 0 && $rCart > 0 && $rCart < $rBuy;
        @endphp
        <div class="modal-bg" id="mRate"><div class="modal" style="max-width:420px">
            <h3>💲 سعر صرف السلات</h3>
            <div class="note mb">السعر اللي تنحسب بيه سلات SHEIN و iHerb و Amazon (الواتساب والموقع). السلات اللي انحسبت قبل ما تتغيرش.</div>
            @if ($rBuy)<div class="hint mb">متوسط آخر شراء دولار عندكم: <b class="num">{{ $rBuy }}</b>@if ($rLow)<br><b style="color:var(--down)">⚠ سعر السلات أقل من سعر شرائكم = تبيعوا بخسارة</b>@endif</div>@endif
            <div class="note mb">آخر تحديث: {{ $rAt ? \Carbon\Carbon::parse($rAt)->diffForHumans() : 'غير معروف' }}</div>
            <form method="post" action="{{ route('sg.rate') }}">@csrf
                <input name="rate" class="inp numr big" value="{{ $rCart }}" inputmode="decimal" required style="font-size:24px;text-align:center">
                <div style="display:flex;gap:8px;margin-top:10px"><button class="btn" style="flex:1">حفظ السعر</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
            </form>
            <form method="post" action="{{ route('sg.rate.ok') }}" style="margin-top:8px">@csrf<button class="btn ghost sm block">✓ السعر نفسه صحيح (بدون تغيير)</button></form>
        </div></div>
        @endrole
        <div class="bell-wrap no-print">
            <button class="bell" id="bell" title="التنبيهات">🔔<span class="bd" id="bellN" hidden>0</span></button>
            <div class="bell-menu" id="bellM"><div class="bm-h"><b>التنبيهات</b><a href="{{ route('sg.alerts.center') }}" class="btn ghost sm">عرض الكل</a><button id="bellRead" class="btn ghost sm">علّم الكل مقروء</button></div><div style="padding:6px 12px">@include('sg.partials.push', ['subUrl' => route('sg.push.subscribe')])</div><div id="bellL"><div class="note" style="padding:14px">لا يوجد</div></div></div>
        </div>
        @include('sg.partials.flash')
        @include('partials.back', ['home' => route('admin.home')])
        @yield('content')
    </main>
</div>
<script src="{{ asset('assets/sg/sg.js') }}?v=6"></script>
<script>
/* 🔍 بحث مباشر في كل الواجهات: كل ما تكتب في خانة البحث يفلتر وحده (من غير Enter) ويرجع المؤشر مكانه */
(function () {
  const skip = ['dq', 'pcQ', 'liveQ', 'custQ'];
  document.querySelectorAll('form input[name=q], form input[name=search], form input[name=term]').forEach(i => {
    const f = i.form; if (!f || (f.getAttribute('method') || 'get').toLowerCase() !== 'get' || skip.includes(i.id) || i.hasAttribute('oninput') || i.dataset.nolive !== undefined) return;
    let t; i.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => {
      try { sessionStorage.setItem('sg-live-focus', i.name); } catch (e) {}
      const pg = f.querySelector('input[name=page]'); if (pg) pg.remove(); f.submit(); }, 650); });
  });
  try { const n = sessionStorage.getItem('sg-live-focus'); if (n) { sessionStorage.removeItem('sg-live-focus'); const el = document.querySelector('form input[name="' + n + '"]'); if (el) { el.focus(); const l = el.value.length; try { el.setSelectionRange(l, l); } catch (e) {} } } } catch (e) {}
})();
</script>
<script>
/* التنبيهات: تسأل السيرفر كل 20 ثانية، وأي تنبيه جديد يظهر إشعار ويصوّت */
(function () {
  const url = @json(route('sg.alerts')), readUrl = @json(route('sg.alerts.read')), tok = document.querySelector('meta[name=csrf-token]').content;
  const n = document.getElementById('bellN'), m = document.getElementById('bellM'), l = document.getElementById('bellL');
  let since = +(localStorage.getItem('sgAlertSince') || 0), first = !since;
  const beep = () => { try { const c = new (window.AudioContext || window.webkitAudioContext)(), o = c.createOscillator(), g = c.createGain(); o.connect(g); g.connect(c.destination); o.frequency.value = 880; g.gain.value = .08; o.start(); setTimeout(() => { o.frequency.value = 660; }, 140); setTimeout(() => { o.stop(); c.close(); }, 320); } catch (e) {} };
  function poll() {
    fetch(url + '?since=' + since, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(d => {
      n.hidden = !d.unread; n.textContent = d.unread;
      l.innerHTML = d.items.length ? d.items.map(a => `<a class="bm-i ${a.unread ? 'u' : ''} ${a.level}" style="position:relative;padding-left:30px" data-id="${a.id}" href="${a.url || '#'}"><b>${a.title}</b><span>${a.body || ''}</span><small>${a.ago}</small><i class="bm-x" title="مسح التنبيه" style="position:absolute;top:8px;left:10px;font-style:normal;color:#999;cursor:pointer;padding:2px 6px">✕</i></a>`).join('') : '<div class="note" style="padding:14px">لا يوجد</div>';
      const fresh = d.items.filter(a => a.new);
      if (!first && fresh.length) {
        beep();
        fresh.slice(0, 3).forEach(a => { if (window.Notification && Notification.permission === 'granted') new Notification(a.title, { body: a.body || '', icon: @json(asset('assets/images/favicon.png')) }); });
        const t = document.createElement('div'); t.className = 'flash ' + (fresh[0].level === 'warn' ? 'err' : 'ok'); t.style.cssText = 'position:fixed;left:16px;bottom:16px;z-index:90;max-width:380px;box-shadow:0 10px 30px rgba(0,0,0,.15)'; t.innerHTML = '🔔 <b>' + fresh[0].title + '</b> ' + (fresh[0].body || ''); document.body.appendChild(t); setTimeout(() => t.remove(), 9000);
      }
      first = false; since = d.last; localStorage.setItem('sgAlertSince', since);
    }).catch(() => {});
  }
  document.getElementById('bell').onclick = () => { m.classList.toggle('open'); if (window.Notification && Notification.permission === 'default') Notification.requestPermission(); };
  document.getElementById('bellRead').onclick = () => fetch(readUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': tok } }).then(poll);
  document.addEventListener('click', e => { if (!e.target.closest('.bell-wrap')) m.classList.remove('open'); });
  // الضغط على تنبيه يعلّمه مقروء (يولي أبيض) وبعدين يفتح الرابط
  l.addEventListener('click', e => {
    const a = e.target.closest('.bm-i'); if (!a || !a.dataset.id) return;
    if (e.target.closest('.bm-x')) { // مسح التنبيه (ما يوصل حتى شي للعميل)
      e.preventDefault(); e.stopPropagation(); a.remove();
      const f = new FormData(); f.append('_token', tok); f.append('_method', 'DELETE');
      fetch(@json(url('app/alerts-center')) + '/' + a.dataset.id, { method: 'POST', body: f, headers: { 'X-CSRF-TOKEN': tok } }).then(poll).catch(() => {});
      return;
    }
    const go = a.getAttribute('href'); e.preventDefault(); a.style.position = 'relative';
    a.classList.remove('u');
    const fd = new FormData(); fd.append('id', a.dataset.id); fd.append('_token', tok);
    fetch(readUrl, { method: 'POST', body: fd, keepalive: true, headers: { 'X-CSRF-TOKEN': tok } }).catch(() => {}).finally(() => { if (go && go !== '#') location.href = go; else poll(); });
  });
  poll(); setInterval(poll, 20000);
})();
</script>
@stack('script')
</body>
</html>
