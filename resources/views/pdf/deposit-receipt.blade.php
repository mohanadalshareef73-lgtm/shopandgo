@php
    $ph = fn ($p) => (str_contains((string) $p, '@') || strlen(preg_replace('/\D/', '', (string) $p)) > 13) ? '' : $p;
    $c = $d->customer; $fee = $d->fee_payer === 'company' ? 0 : (float) $d->fee; $who = $d->courier_name ?: optional($d->courier)->name;
@endphp
<html dir="rtl"><head><meta charset="utf-8">
<style>
body { font-family: somar; font-size: 13pt; color: #1f2433; }
.hd { border-bottom: 1mm solid #1c45a3; padding-bottom: 4mm; margin-bottom: 6mm; }
.hd b { font-size: 22pt; color: #1c45a3; }
.tt { font-size: 18pt; font-weight: bold; color: #b5480f; margin-bottom: 5mm; }
table.m { width: 100%; border-collapse: collapse; }
table.m td { padding: 4mm 3mm; border-bottom: 0.4mm dashed #c5cad8; font-size: 14pt; }
table.m td.v { text-align: left; font-weight: bold; }
.tot td { background: #fff4e5; font-size: 17pt !important; }
.sig { margin-top: 18mm; width: 100%; }
.sig td { width: 50%; padding-top: 14mm; border-top: 0.4mm solid #1f2433; text-align: center; font-size: 12pt; }
</style></head><body>
<div class="hd"><b>Shop & Go</b> &nbsp;·&nbsp; {{ now()->format('Y-m-d') }}</div>
<div class="tt">إيصال عربون</div>
<table class="m">
    <tr><td>الزبون</td><td class="v">{{ optional($c)->name ?: $d->recipient_name }} @if (optional($c)->code)· كود {{ $c->code }}@endif</td></tr>
    <tr><td>الهاتف</td><td class="v">{{ $ph($d->recipient_phone) }}</td></tr>
    <tr><td>العنوان</td><td class="v">{{ $d->address }}</td></tr>
    @if ($cart)<tr><td>الحجز</td><td class="v">سلة {{ $cart->retailer_label }} #{{ $cart->id }}</td></tr>@endif
    @if ($order)<tr><td>رقم الإيصال</td><td class="v">{{ $order->code }}</td></tr>@endif
    <tr><td>رقم التوصيل</td><td class="v">{{ $d->code }}</td></tr>
    @if ($who)<tr><td>المندوب</td><td class="v">{{ $who }}</td></tr>@endif
    <tr><td>العربون</td><td class="v">{{ $f($d->amount) }} د.ل</td></tr>
    <tr><td>التوصيل</td><td class="v">{{ $f($fee) }} د.ل</td></tr>
    <tr class="tot"><td>المطلوب من الزبون</td><td class="v">{{ $f((float) $d->amount + $fee) }} د.ل</td></tr>
</table>
</body></html>
