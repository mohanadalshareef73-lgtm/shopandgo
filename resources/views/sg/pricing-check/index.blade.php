@extends('sg.layout')
@section('title', 'فحص التسعير والسرعة')
@section('bodyClass', 'soft')
@php
    $age = $hb ? now()->timestamp - (int) $hb : null;
    $cronOk = $age !== null && $age < 180;
    $qAge = $hbq ? now()->timestamp - (int) $hbq : null;
    $stuck = $cronOk && ($qAge === null || $qAge > 180);
    $avail = $wallet['available']['amount'] ?? null;
    $ok = fn ($b) => $b ? '<span class="chip u">✓ تمام</span>' : '<span class="chip d">✗ مشكلة</span>';
@endphp
@section('content')
<div class="top">
    <div><h1>🩺 فحص التسعير والسرعة</h1><div class="sub">ليش السلات ما تنحسبش، وشن الصفحات اللي ثقيلة</div></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <form method="post" action="{{ route('sg.pricing.unlock') }}">@csrf<button class="btn ghost">🔓 فك القفل العالق</button></form>
        <form method="post" action="{{ route('sg.pricing.run') }}">@csrf<button class="btn">▶ شغّل التسعير توا</button></form>
        <form method="post" action="{{ route('sg.pricing.optimize') }}">@csrf<button class="btn primary">⚡ سرّع المنظومة</button></form>
        <a href="{{ route('sg.pricing.check') }}" class="btn ghost">↻ تحديث</a>
    </div>
</div>

<div class="card mb">
    <h3>الفحوصات</h3>
    <table class="t">
        <tr><td>⏰ الجدولة (cron) — المحرك اللي يحسب السلات كل دقيقة</td><td>{!! $ok($cronOk) !!}</td><td class="note">
            @if ($age === null) ما اشتغلتش ولا مرة من بعد الرفع. لو مرت دقيقتين وهذا مازال، الـ cron واقف في cPanel
            @elseif ($cronOk) آخر مرة قبل {{ $age }} ثانية
            @else آخر مرة قبل {{ round($age / 60) }} دقيقة — الـ cron واقف @endif</td></tr>
        <tr><td>🔒 قفل التسعير</td><td>{!! $ok(!$stuck) !!}</td><td class="note">@if ($stuck) أمر التسعير ما بداش {{ $qAge === null ? 'من بعد الرفع' : 'من ' . round($qAge / 60) . ' دقيقة' }} والـ cron شغال — غالباً قفل عالق: اضغط «فك القفل العالق» @elseif ($qAge !== null) آخر تشغيل قبل {{ $qAge }} ثانية @else نستنو أول تشغيل @endif</td></tr>
        <tr><td>📥 رسائل الواتساب توصل للمنظومة؟</td><td>{!! $ok($lastWaIn && $lastWaIn->gt(now()->subHours(2))) !!}</td><td class="note">آخر رسالة جت {{ $lastWaIn ? $lastWaIn->diffForHumans() : 'ولا وحدة' }}{{ $lastWaIn && $lastWaIn->lt(now()->subHours(2)) ? ' — الواتساب مش موصّل رسائله (الويب هوك أو جلسة الواتساب)' : '' }}</td></tr>
        <tr><td>🧪 وضع التجربة</td><td>{!! $ok(!$testMode) !!}</td><td class="note">{{ $testMode ? 'شغال! المنظومة ترد على رقم التجربة بس — امسح CARTQUOTES_WHATSAPP_TEST_NUMBER من .env' : 'مقفول (ترد على الكل)' }}</td></tr>
        <tr><td>🔑 مفتاح خدمة التسعير</td><td>{!! $ok($token) !!}</td><td class="note">{{ $token ? 'موجود' : 'ناقص في ملف .env (CART_API_KEY)' }}</td></tr>
        <tr><td>💳 محفظة التسعير</td><td>{!! $ok($wallet && (float) $avail > 0) !!}</td><td class="note">
            @if ($walletErr) ما قدرناش نوصلو للخدمة: {{ $walletErr }}
            @else المتاح <b class="num">{{ $avail }} {{ $wallet['available']['currency'] ?? '' }}</b> @endif</td></tr>
        <tr><td>📵 الواتساب (آخر 3 ساعات)</td><td>{!! $ok($waFail < 3) !!}</td><td class="note">{{ $waFail }} رسالة فشلت{{ $waFail >= 3 ? ' — تأكد إن جلسة الواتساب مربوطة' : '' }}</td></tr>
        <tr><td>🛒 سلات تستنى الرد (آخر يومين)</td><td>{!! $ok($waiting < 5) !!}</td><td class="note">{{ $waiting }} سلة · فشلت اليوم {{ $failedToday }} · آخر سلة جت {{ $lastIn ? $lastIn->diffForHumans() : '—' }}</td></tr>
        <tr><td>🐞 وضع الديبق APP_DEBUG</td><td>{!! $ok(!$debug) !!}</td><td class="note">{{ $debug ? 'شغال — خليه false في .env (يثقل المنظومة)' : 'مقفول' }}</td></tr>
    </table>
</div>

<div class="card mb">
    <h3>⚡ السرعة</h3>
    <table class="t">
        <tr><td>الإعدادات محفوظة جاهزة (config cache)</td><td>{!! $ok($speed['config']) !!}</td><td class="note">{{ $speed['config'] ? 'تمام' : 'اضغط «سرّع المنظومة»' }}</td></tr>
        <tr><td>الروابط محفوظة جاهزة (route cache)</td><td>{!! $ok($speed['routes']) !!}</td><td class="note">{{ $speed['routes'] ? 'تمام' : 'اضغط «سرّع المنظومة»' }}</td></tr>
        <tr><td>OPcache (ذاكرة PHP)</td><td>{!! $ok($speed['opcache']) !!}</td><td class="note">{{ $speed['opcache'] ? 'شغال' : 'مقفول — فعّله من cPanel ← Select PHP Version ← Extensions ← opcache' }}</td></tr>
        <tr><td>PHP</td><td></td><td class="note num">{{ $speed['php'] }} · cache: {{ $speed['cache'] }} · session: {{ $speed['session'] }}</td></tr>
    </table>
    <div class="hint mt">بعد أي رفع ملفات افتح «مسح الكاش» (clearCacheAndCookies) — يمسح ويرجع يسرّع وحده.</div>
</div>

<div class="card tw mb">
    <div class="tw-h" style="display:flex;justify-content:space-between;align-items:center"><b>🐢 الصفحات اللي أخذت أكثر من ثانية (آخر 3 أيام)</b>
        @if ($slow->count())<form method="post" action="{{ route('sg.pricing.clearslow') }}">@csrf<button class="btn ghost sm">مسح السجل</button></form>@endif</div>
    @if ($slowBy->isEmpty())
        <div class="note" style="padding:14px 18px">ما فيش صفحات بطيئة مسجلة لين توا (السجل يبدا من بعد الرفع). خلي الموظفين يشتغلو ساعة وارجع شوف.</div>
    @else
    <table class="t">
        <thead><tr><th>الصفحة</th><th>كم مرة</th><th>المتوسط</th><th>الأبطأ</th><th>الداتابيز</th><th>استعلامات</th><th>ذاكرة</th></tr></thead>
        @foreach ($slowBy->take(25) as $r)
        <tr><td style="direction:ltr;text-align:right">{{ $r['route'] }}</td><td class="num">{{ $r['n'] }}</td><td class="num">{{ number_format($r['avg'] / 1000, 1) }} ث</td><td class="num">{{ number_format($r['max'] / 1000, 1) }} ث</td><td class="num">{{ number_format($r['db'] / 1000, 1) }} ث</td><td class="num">{{ $r['q'] }}</td><td class="num">{{ $r['mem'] }}MB</td></tr>
        @endforeach
    </table>
    @endif
</div>

<div class="card tw mb">
    <div class="tw-h"><b>آخر 20 سلة</b></div>
    <table class="t">
        <thead><tr><th>#</th><th>المتجر</th><th>الحالة</th><th>الخطأ</th><th>محاولات</th><th>جت</th><th>المحاولة الجاية</th><th>انبعت للزبون</th></tr></thead>
        @foreach ($quotes as $q)
        <tr>
            <td class="num">{{ $q->id }}</td>
            <td>{{ $q->retailer_name }}</td>
            <td><span class="chip {{ $q->status === 'failed' ? 'd' : ($q->notified_at ? 'u' : '') }}">{{ $q->status }}</span></td>
            <td class="note" style="max-width:260px;direction:ltr;text-align:right">{{ \Illuminate\Support\Str::limit((string) $q->last_error, 80) ?: '—' }}</td>
            <td class="num">{{ $q->attempts }}</td>
            <td class="note">{{ optional($q->created_at)->diffForHumans() }}</td>
            <td class="note">{{ $q->next_attempt_at ? \Carbon\Carbon::parse($q->next_attempt_at)->diffForHumans() : '—' }}</td>
            <td>{!! $q->notified_at ? '✓ ' . \Carbon\Carbon::parse($q->notified_at)->diffForHumans() : '<span style="color:var(--down)">لا</span>' !!}</td>
        </tr>
        @endforeach
    </table>
</div>

<div class="card tw">
    <div class="tw-h"><b>آخر رسائل الواتساب</b></div>
    <table class="t">
        <thead><tr><th>النوع</th><th>الرقم</th><th>الحالة</th><th>الخطأ</th><th>متى</th></tr></thead>
        @foreach ($wa as $m)
        <tr><td>{{ $m->type }}</td><td class="num">{{ $m->phone }}</td><td><span class="chip {{ $m->status === 'failed' ? 'd' : 'u' }}">{{ $m->status }}</span></td><td class="note" style="direction:ltr;text-align:right">{{ \Illuminate\Support\Str::limit((string) $m->error, 90) ?: '—' }}</td><td class="note">{{ \Carbon\Carbon::parse($m->created_at)->diffForHumans() }}</td></tr>
        @endforeach
    </table>
</div>
@endsection
