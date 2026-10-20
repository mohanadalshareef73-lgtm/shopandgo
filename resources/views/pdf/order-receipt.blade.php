<html dir="rtl"><head><meta charset="utf-8">
<style>
body { font-family: somar; font-size: 12pt; color: #1f2433; }
.hd { border-bottom: 0.6mm solid #1c45a3; padding-bottom: 3mm; margin-bottom: 4mm; }
.hd b { font-size: 17pt; color: #1c45a3; }
.box { background: #f2f4fa; border-radius: 3mm; padding: 3mm 4mm; margin-bottom: 4mm; line-height: 1.8; }
table.t { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
table.t th { background: #1c45a3; color: #fff; padding: 2.2mm; font-size: 11pt; }
table.t td { border-bottom: 0.3mm solid #e2e5ee; padding: 2.2mm; text-align: center; }
table.t td.nm { text-align: right; }
table.m { width: 100%; border-collapse: collapse; }
table.m td { padding: 2mm 3mm; border-bottom: 0.3mm dashed #d5d9e4; }
table.m td.v { text-align: left; font-weight: bold; }
.due { background: #fff4e5; font-size: 13.5pt; }
</style></head><body>
<div class="hd"><b>Shop & Go</b> &nbsp;·&nbsp; إيصال طلب</div>
<div class="box">
    🔢 رقم الطلب: <b>{{ $order->code }}</b> · 📅 {{ optional($order->created_at)->format('Y-m-d') }}<br>
    👤 <b>{{ optional($c)->name }}</b> · كود <b>{{ optional($c)->code }}</b> · {{ optional($c)->phone_1 }}
    @if ($order->trip_details)<br>✈️ الرحلة: <b>{{ $order->trip_details }}</b>@endif
</div>
@if ($order->kind === 'shipping')
@php $og = \App\Services\AppSettings::origins()[$order->ship_origin] ?? []; @endphp
<table class="m" style="margin-bottom:4mm">
    <tr><td>🌍 الشحن من</td><td class="v">{{ $og['name'] ?? '—' }}</td></tr>
    @if ($order->ship_platform)<tr><td>🛍 المنصة</td><td class="v">{{ $order->ship_platform }}</td></tr>@endif
    <tr><td>✈️ نوع الشحن</td><td class="v">{{ $order->package_type ?: 'جوي' }}</td></tr>
    @if ($order->tracking_no)<tr><td>🔎 رقم التتبع</td><td class="v">{{ $order->tracking_no }}</td></tr>@endif
    <tr><td>📦 عدد الطرود</td><td class="v">{{ $order->bags_num ?: 1 }}</td></tr>
    @if ($order->kilo)<tr><td>⚖️ الوزن</td><td class="v">{{ $fmt($order->kilo) }} كغ</td></tr>@endif
    @if ($order->storage_num)<tr><td>🗄 الرف</td><td class="v">{{ $order->storage_num }}</td></tr>@endif
    @if (\App\Services\ShippingService::isDual($order))
        @php $dp = \App\Services\ShippingService::dualPrices($order); @endphp
        <tr><td>🚚 سعر الشحن</td><td class="v">{{ $fmt($order->usd_amount) . ' $' }}</td></tr>
        <tr><td>💵 إذا الدفع كاش</td><td class="v">{{ $fmt($dp['cash']) }} د.ل@if ($order->ship_pay === 'cash') ✓@endif</td></tr>
        <tr><td>🏦 إذا الدفع مصرف</td><td class="v">{{ $fmt($dp['bank']) }} د.ل@if ($order->ship_pay === 'bank') ✓@endif</td></tr>
    @else
    <tr><td>🚚 سعر الشحن</td><td class="v">@if (($order->ship_cur ?? null) !== 'lyd' && (float) $order->usd_amount > 0){{ $fmt($order->usd_amount) . ' $' }}@else{{ $fmt($order->ship_price) }} د.ل@endif</td></tr>
    @endif
</table>
@elseif ($order->items->count())
<table class="t">
    <tr><th>الصنف</th><th>العدد</th><th>المجموع</th></tr>
    @foreach ($order->items as $it)
        <tr><td class="nm">{{ $it->name }}</td><td>{{ $it->quantity }}</td><td>{{ $fmt($it->price) }}</td></tr>
    @endforeach
</table>
@else
<div class="box">📦 عدد القطع: <b>{{ $order->qty }}</b></div>
@endif
<table class="m">
    @if ($order->kind !== 'shipping')<tr><td>🛍 قيمة الطلب</td><td class="v">{{ $fmt($order->total_price) }} د.ل</td></tr>@endif
    @if ((float) $order->ship_price > 0)<tr><td>🚚 الشحن@if (\App\Services\ShippingService::isDual($order) && $order->ship_pay) ({{ $order->ship_pay === 'bank' ? 'مصرف' : 'كاش' }})@endif</td><td class="v">{{ $fmt($order->ship_price) }} د.ل</td></tr>@endif
    @if ((float) $order->discount > 0)<tr><td>🎁 تخفيض</td><td class="v">− {{ $fmt($order->discount) }} د.ل</td></tr>@endif
    <tr><td>✅ المدفوع</td><td class="v">{{ $fmt($order->paid_price) }} د.ل</td></tr>
    <tr class="due"><td><b>⏳ الباقي</b></td><td class="v">{{ $fmt(max(0, $order->remaining())) }} د.ل</td></tr>
</table>
</body></html>
