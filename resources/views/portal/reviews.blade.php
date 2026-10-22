@php $s = \App\Services\AppSettings::all(); @endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#262c4a">
<title>تقييمات الزبائن · {{ $s['shop_name'] ?? 'Shop & Go' }}</title>
<link rel="icon" href="{{ asset('assets/sg/logo.png') }}">
<link rel="stylesheet" href="{{ asset('assets/sg/app.css') }}?v=6">
</head>
<body>
<div class="app" style="padding-bottom:24px">
    <div style="background:#262c4a;color:#fff;border-radius:0 0 30px 30px;padding:34px 22px 26px;text-align:center">
        <img src="{{ asset('assets/sg/logo.png') }}" alt="Shop & Go" style="width:120px;background:#fff;border-radius:12px;padding:6px 10px">
        <h1 style="font-size:22px;margin:16px 0 4px">تقييمات زبائن Shop & Go</h1>
        <div style="font-family:InterNum,Somar;font-weight:800;font-size:28px">+{{ number_format($stats['delivered']) }}</div>
        <div style="color:#c9cde0;font-size:13.5px">طلبية سلّمناها @if ($stats['reviews']) · ⭐ {{ $stats['rating'] }} من {{ $stats['reviews'] }} تقييم @endif</div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;padding:18px">
        @forelse ($rows as $r)
            <div style="background:#fff;border-radius:16px;padding:12px 14px;font-size:14px;line-height:1.75">
                @if ($r->photo)<img src="{{ asset('storage/' . $r->photo) }}" alt="" loading="lazy" style="width:100%;height:170px;object-fit:cover;border-radius:10px;margin-bottom:8px">@endif
                <div style="color:#f5a623;font-size:16px">{{ str_repeat('★', $r->rating) }}<span style="color:#d9dce4">{{ str_repeat('★', 5 - $r->rating) }}</span></div>
                @if ($r->body)<div>{{ $r->body }}</div>@endif
                <div style="color:var(--ink-3);font-size:12.5px;margin-top:4px">— {{ $r->publicName() }} · {{ optional($r->decided_at)->format('Y-m-d') }}</div>
            </div>
        @empty
            <div style="text-align:center;color:var(--ink-3);padding:30px">قريباً ✨</div>
        @endforelse
    </div>
    <div style="text-align:center">{{ $rows->links() }}</div>
    <div style="text-align:center;margin-top:10px"><a href="{{ route('customer.login') }}" style="color:var(--brand);font-weight:700">ادخل لحسابك ←</a></div>
</div>
</body>
</html>
