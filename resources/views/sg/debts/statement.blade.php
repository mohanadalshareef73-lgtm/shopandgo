@php use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); @endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><title>كشف الديون {{ now()->format('Y-m-d') }}</title>
<link rel="stylesheet" href="{{ asset('assets/sg/sg.css') }}">
<style>body { background: #fff; padding: 24px; } h1 { margin: 0; } .hd { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; } .hd img { width: 120px; } @page { size: A4; margin: 12mm; }</style>
</head>
<body onload="window.print()">
<div class="hd"><div><h1>كشف الديون عند العملاء</h1><div class="note">بتاريخ {{ now()->format('Y-m-d g:i A') }}</div></div><img src="{{ asset('assets/sg/logo.png') }}" alt=""></div>
<table class="t">
    <thead><tr><th>#</th><th>العميل</th><th>الكود</th><th>الهاتف</th><th>الإيصالات</th><th class="n">الفاتورة + الشحن</th><th class="n">دفع</th><th class="n">الباقي</th><th class="n">عمر الدين</th></tr></thead>
    <tbody>
    @foreach ($rows as $r)
        <tr><td class="num">{{ $loop->iteration }}</td><td>{{ $r->customer->name }}</td><td class="num">{{ $r->customer->code }}</td><td class="num">{{ $r->customer->phone_1 }}</td><td class="wrap num" style="font-size:12px">{{ $r->orders->pluck('code')->implode(' · ') }}</td><td class="n num">{{ $f($r->total) }}</td><td class="n num">{{ $f($r->paid) }}</td><td class="n num"><b>{{ $f($r->due) }}</b></td><td class="n num">{{ $r->age }} يوم</td></tr>
    @endforeach
    </tbody>
</table>
<div class="ln tot" style="margin-top:16px"><span>المجموع ({{ $rows->count() }} عميل)</span><span class="num">{{ $f($rows->sum('due')) }} د.ل</span></div>
</body>
</html>
