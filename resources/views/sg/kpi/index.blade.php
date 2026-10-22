@extends('sg.layout')
@section('title', 'أرقام نتابعوها')
@section('bodyClass', 'soft')
@php
    $w0 = $weeks[0] ?? []; $w1 = $weeks[1] ?? [];
    $chg = function ($a, $b, $inv = false) {
        if ($a === null || $b === null || (float) $b == 0) { return ''; }
        $p = round(($a - $b) / abs($b) * 100);
        $good = $inv ? $p <= 0 : $p >= 0;
        return '<span class="chip ' . ($good ? 'u' : 'd') . '">' . ($p >= 0 ? '▲' : '▼') . ' ' . abs($p) . '%</span>';
    };
    $fmt = fn ($v, $suf = '') => $v === null ? '—' : (is_float($v) ? rtrim(rtrim(number_format($v, 1), '0'), '.') : number_format($v)) . $suf;
    $rows = [
        ['🛍 طلبيات جديدة', 'orders', '', false],
        ['💰 قيمة الطلبيات (د.ل)', 'value', '', false],
        ['✅ طلبيات تسلّمت', 'delivered', '', false],
        ['👥 زبائن طلبو', 'buyers', '', false],
        ['🔁 زبائن رجعو', 'returning', '', false],
        ['🔁 نسبة اللي رجعو', 'returning_pct', '%', false],
        ['🆕 زبائن أول مرة يطلبو', 'new_buyers', '', false],
        ['⏱ متوسط أيام التوصيل', 'avg_days', ' يوم', true],
        ['🐢 نسبة التأخير (وصلت بعد ' . $late . ' يوم)', 'late_pct', '%', true],
        ['📉 بلاغات نواقص', 'missing', '', true],
        ['📉 نسبة النواقص من المسلّم', 'missing_pct', '%', true],
        ['⚠ كل المشاكل المسجلة', 'issues', '', true],
        ['🛒 سلات جت على الواتساب', 'carts', '', false],
        ['🧾 سلات ولّت طلبية', 'carts_booked', '', false],
        ['💬 وقت الرد بالسعر (دقيقة)', 'reply_min', '', true],
        ['⭐ تقييمات جديدة', 'reviews', '', false],
        ['⭐ متوسط النجوم', 'rating', '', false],
        ['🧭 زبائن جدد تسجلو', 'new_customers', '', false],
        ['🤝 جاو بإحالة', 'src_referral', '', false],
        ['🌐 عندهم حساب في الموقع', 'src_signup', '', false],
        ['🏪 انضافو من المحل/الواتساب', 'src_staff', '', false],
    ];
@endphp
@section('content')
<div class="top">
    <div><h1>📊 أرقام نتابعوها</h1><div class="sub">كل أسبوع من السبت للجمعة · تتحدث كل 10 دقايق</div></div>
    <div class="acts">
        <div class="seg">@foreach ([4 => '4 أسابيع', 8 => '8 أسابيع', 12 => '12 أسبوع'] as $k => $v)<a href="{{ route('sg.kpi', ['w' => $k]) }}" class="{{ $n === $k ? 'on' : '' }}">{{ $v }}</a>@endforeach</div>
        <a class="btn ghost" href="{{ route('sg.kpi', ['w' => $n, 'fresh' => 1]) }}">↻ تحديث</a>
        <button class="sg-burger">☰</button>
    </div>
</div>
<div class="kpis g4">
    <div class="card kpi accent"><div class="lbl">طلبيات الأسبوع هذا</div><div class="v num">{{ $fmt($w0['orders'] ?? null) }}</div><div class="s">الأسبوع اللي فات {{ $fmt($w1['orders'] ?? null) }} {!! $chg($w0['orders'] ?? null, $w1['orders'] ?? null) !!}</div></div>
    <div class="card kpi"><div class="lbl">🔁 زبائن رجعو</div><div class="v num">{{ $fmt($w0['returning_pct'] ?? null, '%') }}</div><div class="s">{{ $fmt($w0['returning'] ?? null) }} من {{ $fmt($w0['buyers'] ?? null) }} زبون {!! $chg($w0['returning_pct'] ?? null, $w1['returning_pct'] ?? null) !!}</div></div>
    <div class="card kpi {{ ($w0['late_pct'] ?? 0) > 20 ? 'red' : '' }}"><div class="lbl">🐢 نسبة التأخير</div><div class="v num">{{ $fmt($w0['late_pct'] ?? null, '%') }}</div><div class="s">متوسط التوصيل {{ $fmt($w0['avg_days'] ?? null, ' يوم') }} {!! $chg($w0['late_pct'] ?? null, $w1['late_pct'] ?? null, true) !!}</div></div>
    <div class="card kpi"><div class="lbl">💬 وقت الرد بالسعر</div><div class="v num">{{ $fmt($w0['reply_min'] ?? null) }} <small>دقيقة</small></div><div class="s">على {{ $fmt($w0['carts'] ?? null) }} سلة {!! $chg($w0['reply_min'] ?? null, $w1['reply_min'] ?? null, true) !!}</div></div>
</div>
<div class="card tw">
    <div class="tw-h"><h3 style="margin:0">كل الأرقام أسبوع بأسبوع</h3><span class="note">الأخضر أحسن من الأسبوع اللي قبله، والأحمر أسوأ · الأسبوع الأول مازال ماشي</span></div>
    <div class="scroll-x">
        <table class="t">
            <thead><tr><th style="position:sticky;right:0;background:inherit">الرقم</th>@foreach ($weeks as $i => $w)<th class="n">{{ $w['label'] }}@if ($w['cur'])<div class="note">(ماشي)</div>@endif</th>@endforeach</tr></thead>
            <tbody>
            @foreach ($rows as [$label, $k, $suf, $inv])
                <tr>
                    <td style="position:sticky;right:0;background:#fff"><b>{{ $label }}</b></td>
                    @foreach ($weeks as $i => $w)
                        @php $v = $w[$k] ?? null; $prev = $weeks[$i + 1][$k] ?? null; $col = ($v !== null && $prev !== null && $v != $prev) ? ((($v > $prev) xor $inv) ? 'var(--up)' : 'var(--down)') : 'inherit'; @endphp
                        <td class="n num" style="color:{{ $col }};font-weight:{{ $i === 0 ? 800 : 600 }}">{{ $fmt($v, $suf) }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="hint mt">💡 «التأخير» = الإيصالات اللي وصلت بعد أكثر من {{ $late }} يوم من تسجيلها. «وقت الرد» = من وصول رابط السلة على الواتساب لين يوصل الزبون السعر. «مصدر الزبائن»: الإحالة من نظام النقاط، و«عندهم حساب» = فتحو حساب ودخلو للموقع.</div>
@endsection
