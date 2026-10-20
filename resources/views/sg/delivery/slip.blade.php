@php use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); $c = $orders->first()->customer; $s = \App\Services\AppSettings::all(); @endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><title>إيصال استلام</title>
<link rel="stylesheet" href="{{ asset('assets/sg/sg.css') }}">
<style>
body { background: #fff; font-size: 13px; }
.slip { width: 78mm; margin: 0 auto; padding: 6mm 3mm; }
.slip img { display: block; width: 40mm; margin: 0 auto 6px; }
.slip h2 { text-align: center; font-size: 16px; margin: 4px 0 8px; }
.slip .r { display: flex; justify-content: space-between; padding: 3px 0; gap: 8px; }
.slip hr { border: 0; border-top: 1px dashed #999; margin: 8px 0; }
.slip .tot { font-size: 15px; font-weight: 800; }
.slip .c { text-align: center; color: #555; font-size: 11.5px; }
@page { size: 80mm auto; margin: 0; }
</style>
</head>
<body onload="window.print()">
<div class="slip">
    <img src="{{ asset('assets/sg/logo.png') }}" alt="">
    <h2>إيصال استلام بضاعة</h2>
    <div class="r"><span>العميل</span><b>{{ optional($c)->name }}</b></div>
    <div class="r"><span>الكود</span><b class="num">{{ optional($c)->code }}</b></div>
    <div class="r"><span>التاريخ</span><span class="num">{{ now()->format('Y-m-d g:i A') }}</span></div>
    <hr>
    @foreach ($orders as $o)
        <div class="r"><b class="num">{{ $o->code }}</b><span>{{ $o->received_qty ?? $o->qty }} قطعة · {{ $o->bags_num ?: 1 }} طرد</span></div>
        <div class="r" style="color:#555"><span>فاتورة {{ $f($o->total_price - $o->discount) }} + شحن {{ $f($o->ship_price) }}{{ \App\Services\ShippingService::isDual($o) && $o->ship_pay ? ' (' . ($o->ship_pay === 'bank' ? 'مصرف' : 'كاش') . ')' : '' }}</span><span>الباقي {{ $f(max(0, $o->remaining())) }}</span></div>
        @if ((int) $o->missing_qty > 0)<div class="r" style="color:#b42318"><span>⚠ نواقص {{ (int) $o->missing_qty }} قطعة</span><span>{{ (float) $o->missing_value > 0 ? ($o->missing_action === 'deduct' ? 'خُصم ' . $f($o->missing_value) : ($o->missing_action === 'credit' ? 'رصيد ' . $f($o->missing_value) : 'قيمتها ' . $f($o->missing_value))) : 'بدون خصم' }}</span></div>@endif
    @endforeach
    <hr>
    <div class="r tot"><span>المدفوع الآن</span><span class="num">{{ $f($paid) }} د.ل</span></div>
    <div class="r"><span>الباقي عليه</span><span class="num">{{ $f($orders->sum(fn ($o) => max(0, $o->remaining()))) }} د.ل</span></div>
    <div class="r"><span>التسليم</span><span>{{ $orders->first()->delivery_type }}{{ $orders->first()->delivery_name ? ' · ' . $orders->first()->delivery_name : '' }}</span></div>
    <div class="r"><span>سلّمها</span><span>{{ optional($orders->first()->deliveredUser)->name }}</span></div>
    <hr>
    <div class="c">استلمت البضاعة كاملة وبحالة سليمة<br><br>توقيع العميل: ....................</div>
    <hr>
    <div class="c">{{ $s['shop_name'] ?? 'Shop & Go' }} · {{ $s['shop_phone'] ?? '' }}<br>{{ $s['shop_address'] ?? '' }}</div>
</div>
</body>
</html>
