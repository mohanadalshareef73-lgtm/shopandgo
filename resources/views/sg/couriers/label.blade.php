@php $f = fn ($n) => \App\Services\TreasuryService::fmt($n); $s = \App\Services\AppSettings::all(); @endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><title>{{ $d->code }}</title>
<link rel="stylesheet" href="{{ asset('assets/sg/sg.css') }}">
<style>
body { background: #fff; font-size: 13.5px; }
.lb { width: 100mm; margin: 0 auto; padding: 5mm; border: 2px dashed #999; }
.lb .h { display: flex; justify-content: space-between; align-items: center; }
.lb .h img { width: 34mm; }
.lb .big { font-size: 22px; font-weight: 800; }
.lb .r { display: flex; justify-content: space-between; gap: 8px; padding: 4px 0; border-bottom: 1px dotted #ccc; }
.lb .amt { background: #111; color: #fff; text-align: center; border-radius: 8px; padding: 8px; margin: 8px 0; }
.lb .amt b { font-size: 26px; display: block; }
@page { size: 105mm auto; margin: 0; }
</style>
</head>
<body onload="window.print()">
<div class="lb">
    <div class="h"><img src="{{ asset('assets/sg/logo.png') }}" alt=""><div style="text-align:left"><div class="big num">{{ $d->code }}</div><div class="note">{{ now()->format('Y-m-d') }}</div></div></div>
    <div class="r"><span>المستلم</span><b>{{ $d->recipient_name }}</b></div>
    <div class="r"><span>الهاتف</span><b class="num">{{ $d->recipient_phone }}{{ $d->recipient_phone2 ? ' / ' . $d->recipient_phone2 : '' }}</b></div>
    <div class="r"><span>العنوان</span><b>{{ $d->city }} {{ $d->address }}</b></div>
    <div class="r"><span>الإيصالات</span><b class="num">{{ $d->orders->pluck('code')->implode(' · ') }}</b></div>
    <div class="r"><span>الطرود</span><b>{{ $d->parcels }} · {{ $d->package_type }}</b></div>
    <div class="r"><span>المندوب</span><b>{{ optional($d->courier)->name }} <span class="num">{{ optional($d->courier)->phone }}</span></b></div>
    <div class="amt">المطلوب من العميل ({{ \App\Models\Delivery::PAY_TYPES[$d->pay_type] ?? '' }})<b class="num">{{ $f($d->toCollect()) }} د.ل</b><span style="font-size:12px">{{ $d->companyPays() ? 'أجرة التوصيل على الشركة — لا تُطلب من العميل' : 'منها أجرة توصيل ' . $f($d->fee) }}</span></div>
    @if ($d->note)<div><b>ملاحظات:</b> {{ $d->note }}</div>@endif
    <div class="note" style="text-align:center;margin-top:8px">{{ $s['shop_name'] ?? 'Shop & Go' }} · {{ $s['shop_phone'] ?? '' }}</div>
</div>
</body>
</html>
