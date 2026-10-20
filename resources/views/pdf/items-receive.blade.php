<html dir="rtl"><head><meta charset="utf-8">
<style>
body { font-family: somar; font-size: 12pt; color: #1f2433; }
.hd { border-bottom: 0.6mm solid #1c45a3; padding-bottom: 3mm; margin-bottom: 4mm; }
.hd b { font-size: 17pt; color: #1c45a3; }
.box { background: #f2f4fa; border-radius: 3mm; padding: 3mm 4mm; margin-bottom: 4mm; }
table.t { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
table.t th { background: #1c45a3; color: #fff; padding: 2.2mm; font-size: 11pt; }
table.t td { border-bottom: 0.3mm solid #e2e5ee; padding: 2.2mm; text-align: center; }
table.t td.nm { text-align: right; }
.now { color: #0f7a4a; font-weight: bold; }
.left { color: #c62828; font-weight: bold; }
table.m { width: 100%; border-collapse: collapse; }
table.m td { padding: 2mm 3mm; border-bottom: 0.3mm dashed #d5d9e4; }
table.m td.v { text-align: left; font-weight: bold; }
.due { background: #fff4e5; font-size: 13.5pt; }
.ok { background: #e9f7ef; color: #0f7a4a; text-align: center; font-weight: bold; padding: 3mm; border-radius: 3mm; margin-top: 4mm; }
</style></head><body>
<div class="hd"><b>Shop & Go</b> &nbsp;·&nbsp; إيصال استلام منتجات</div>
<div class="box">
    👤 <b>{{ $c->name }}</b> · كود <b>{{ $c->code }}</b> · {{ $c->phone_1 }}<br>
    🔢 الإيصال: <b>{{ $order->code }}</b>@if ($order->trip_details) · ✈️ الرحلة: <b>{{ $order->trip_details }}</b>@endif<br>
    📅 {{ now()->format('Y-m-d g:i a') }} · الموظف: {{ optional(auth()->user())->name }}
</div>
<table class="t">
    <tr><th>الصنف</th><th>طلب</th><th>استلم هذي المرة</th><th>استلم الكلي</th><th>باقي</th></tr>
    @foreach ($items as $it)
        @php $now = (int) ($nowMap[$it->id] ?? 0); $left = max(0, (int) $it->quantity - (int) $it->received_qty); @endphp
        <tr><td class="nm">{{ $it->name }}</td><td>{{ $it->quantity }}</td><td class="{{ $now ? 'now' : '' }}">{{ $now ?: '—' }}</td><td>{{ (int) $it->received_qty }}</td><td class="{{ $left ? 'left' : '' }}">{{ $left ?: '✓' }}</td></tr>
    @endforeach
    <tr><td class="nm"><b>المجموع</b></td><td><b>{{ $all }}</b></td><td class="now">{{ $nowTotal ?: '—' }}</td><td><b>{{ $got }}</b></td><td class="{{ $all - $got ? 'left' : '' }}">{{ $all - $got ?: '✓' }}</td></tr>
</table>
<table class="m">
    <tr><td>🛍 قيمة الطلب (شامل الشحن)</td><td class="v">{{ $fmt($order->grandTotal()) }} د.ل</td></tr>
    @if ($paid > 0)<tr><td>💵 دفع هذي المرة</td><td class="v">{{ $fmt($paid) }} د.ل</td></tr>@endif
    <tr><td>✅ المدفوع الكلي</td><td class="v">{{ $fmt($order->paid_price) }} د.ل</td></tr>
    <tr class="due"><td><b>⏳ الباقي عليه</b></td><td class="v">{{ $fmt(max(0, $order->remaining())) }} د.ل</td></tr>
</table>
@if ($done)<div class="ok">🎁 تم استلام الطلب بالكامل — شكراً لثقتك 💙</div>@endif
@if ($note)<div class="box" style="margin-top:4mm">📝 {{ $note }}</div>@endif
</body></html>
