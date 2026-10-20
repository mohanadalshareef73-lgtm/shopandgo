{{-- 🧾 نفس فاتورتنا الأصلية (order-invoice) بس بصيغة PDF — جداول بدل flex باش تطلع صح في الملف --}}
<html dir="rtl"><head><meta charset="utf-8">
<style>
body { font-family: somar; font-size: 11.5pt; color: #1f2433; }
.hd { width: 100%; border-bottom: 0.4mm solid #545353; margin-bottom: 2mm; }
.hd td { vertical-align: middle; }
.ttl { font-size: 15pt; font-weight: bold; text-align: left; }
.big { font-size: 15pt; } .navy { color: #282E4D; }
table.d { width: 100%; border-collapse: collapse; margin: 3mm 0 5mm; }
table.d th, table.d td { border: 0.3mm solid #000; text-align: center; padding: 1.6mm; }
table.d th { background: #f2f2f2; }
.it td { background: #c6c4c4; }
.nb { border: 0 !important; }
.paid { color: #fff; background: #282E4D; }
.due { background: #d3d3d3; }
.warn { color: #bb7a00; font-size: 10.5pt; }
ul { margin: 1mm 0 2mm; }
.ft { border-top: 0.6mm solid #000; text-align: center; margin-top: 8mm; padding-top: 2mm; font-size: 10.5pt; }
</style></head><body>
@php $sign = $order->currency_sign ?: 'د.ل'; $logo = public_path('assets/images/loggo.png'); @endphp
<table class="hd"><tr>
    <td style="width:50%">@if (is_file($logo))<img src="{{ $logo }}" style="height:16mm">@endif</td>
    <td class="ttl">SHOP AND GO</td>
</tr></table>
<div style="font-size:13pt;font-weight:bold"># {{ $order->code }}</div>
<div style="margin:2mm 0 3mm"><b class="big">اسم العميل :</b> <b class="big navy">{{ optional($c)->name }} - {{ optional($c)->code }}</b></div>
@if (!$order->ship_price)<div class="warn">ملاحظة :: اجمالي القيمة بدون سعر الشحن</div>@endif
<table style="width:100%;font-size:10.5pt;margin-top:2mm"><tr>
    <td>تاريخ الفاتورة: <span class="navy">{{ now()->format('Y-m-d') }}</span></td>
    <td style="text-align:left">تاريخ الطلب: <span class="navy">{{ optional($order->created_at)->format('Y-m-d') }}</span></td>
</tr></table>
<table class="d">
    <tr><th>المنصة</th><th>الكمية</th><th>سعر الشحن</th><th>اجمالي القيمة</th></tr>
    <tr class="navy">
        <td>{{ optional($order->platform)->name }}</td>
        <td>{{ $order->qty }}</td>
        <td>{{ $order->ship_price ? $fmt($order->ship_price) . ' ' . $sign : '-' }}@if (\App\Services\ShippingService::isDual($order))@php $dp = \App\Services\ShippingService::dualPrices($order); @endphp<br><span style="font-size:9pt">كاش: {{ $fmt($dp['cash']) }} · مصرف: {{ $fmt($dp['bank']) }}</span>@endif</td>
        <td>{{ $fmt($order->total_price + (float) $order->ship_price) }} {{ $sign }}@if ($order->discount) <span style="color:red">(تخفيض: {{ $fmt($order->discount) }} {{ $sign }})</span>@endif</td>
    </tr>
    @foreach ($order->items as $item)
        <tr class="it"><td>{{ $item->name }}</td><td>{{ $item->quantity }}</td><td colspan="2">{{ $fmt($item->price) }} {{ $sign }}</td></tr>
    @endforeach
    <tr><td colspan="2" style="border:0.3mm solid #fff;border-bottom:0.3mm solid #fff;border-top:0.3mm solid #fff"></td><td class="paid">القيمة المدفوعة</td><td class="paid">{{ $fmt($order->paid_price) }} {{ $sign }}</td></tr>
    @if ($last = $order->paids->last())
        <tr><td colspan="2" style="border:0.3mm solid #fff;border-bottom:0.3mm solid #fff;border-top:0.3mm solid #fff"></td><td>تم الدفع في {{ optional($last->created_at)->format('Y-m-d') }}</td><td>{{ $fmt($last->paid_price) }} {{ $sign }}</td></tr>
    @endif
    <tr class="navy"><td colspan="2" style="border:0.3mm solid #fff;border-bottom:0.3mm solid #fff;border-top:0.3mm solid #fff"></td><td class="due">القيمة المستحقة</td><td class="due">{{ $fmt(($order->total_price + (float) $order->ship_price - (float) $order->discount) - (float) $order->paid_price) }} {{ $sign }}</td></tr>
</table>
@if (optional($invoice)->welcome)<div style="margin-bottom:3mm"><b>{{ $invoice->welcome }}</b></div>@endif
<div class="navy">الشروط:</div>
<ul><li>دفع قيمة الشراء فوراً</li><li>دفع تكلفة الشحن عند وصول الاصناف اعلاه</li></ul>
@if (optional($invoice)->note)<div class="navy"><b>{{ $invoice->note }}</b></div>@endif
@if (optional($invoice)->note2)<div style="padding-top:2mm">{{ $invoice->note2 }}</div>@endif
<div class="navy" style="margin-top:3mm">نقدم ضمانات الاتية:</div>
<ul><li>مطابقة للمواصفات و الكمية المتفق عليها مسبقا</li><li>عدم مطالبة بتكلفة اضافية من الزبون بعد الاتفاق و دفع الفاتورة</li></ul>
<table style="width:100%"><tr><td><b>نحن سعداء لخدمتكم.</b></td><td style="text-align:left"><b>الـتـوقـيـع :</b><br>............................</td></tr></table>
@if (optional($invoice)->address)<div class="ft">{{ $invoice->address }}</div>@endif
</body></html>
