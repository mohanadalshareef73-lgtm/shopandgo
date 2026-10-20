<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><style>
body { font-family: somar; color: #232f3e; font-size: 12pt; }
table { width: 100%; border-collapse: collapse; }
td { vertical-align: top; }
.header td { padding: 0 0 5mm; border-bottom: 1mm solid #ff9900; }
.title { font-size: 25pt; font-weight: bold; }
.muted { color: #596573; font-size: 10pt; line-height: 1.5; }
.details { margin-top: 5mm; }
.details td { padding: 3mm; background: #f4f5f6; }
.label { font-size: 10pt; color: #596573; margin-bottom: 1mm; }
.value { font-size: 12pt; font-weight: bold; }
.product { margin-top: 8mm; }
.product th { padding: 3mm; background: #232f3e; color: #fff; font-size: 11pt; }
.product td { padding: 5mm 3mm; border-bottom: 0.3mm solid #ddd; }
.name { font-size: 17pt; font-weight: bold; line-height: 1.4; }
.variant { margin-top: 2mm; color: #596573; }
.offer-notice { margin: 2mm 0; }
.offer-notice td { padding: 1.5mm; background: #fff4e0; border: 0.3mm solid #ff9900; font-size: 10pt; line-height: 1.2; }
.offer-details { margin-top: 2mm; font-size: 10pt; line-height: 1.5; }
.image { text-align: center; }
.price { white-space: nowrap; font-weight: bold; text-align: center; }
.total { margin-top: 7mm; page-break-inside: avoid; }
.total td { background: #fff4e0; border-top: 0.7mm solid #ff9900; padding: 5mm; }
.amount { font-size: 28pt; font-weight: bold; text-align: left; white-space: nowrap; }
.notes { margin-top: 6mm; padding: 4mm; background: #f4f5f6; line-height: 1.6; }
.actions { margin-top: 7mm; page-break-inside: avoid; }
.actions td { width: 33.3%; text-align: center; padding: 3mm; border: 0.3mm solid #ddd; }
.discount { color: #007600; margin-top: 3mm; font-size: 11pt; }
</style></head>
<body>
@php
    $item = data_get($cart->payload, 'result.items.0', []);
    $offer = \App\Support\AmazonOfferDetails::fromItem($item);
    $quantity = $quotation['quantity'];
    $unitLyd = (string) \Brick\Math\BigDecimal::of($quotation['customer_unit_usd'])
        ->multipliedBy($quotation['exchange_rate'])->toScale(2, \Brick\Math\RoundingMode::HALF_UP);
    $sale = data_get($cart->payload, 'result.native.sale');
    $original = data_get($cart->payload, 'result.native.original');
    $discounted = is_string($sale['amount'] ?? null) && is_string($original['amount'] ?? null)
        && preg_match('/^\d+(?:\.\d+)?$/D', $sale['amount']) && preg_match('/^\d+(?:\.\d+)?$/D', $original['amount'])
        && ($sale['currency'] ?? null) === ($original['currency'] ?? null)
        && \Brick\Math\BigDecimal::of($original['amount'])->isGreaterThan($sale['amount']);
    $phone = $cart->contact_phone;
    $phone = is_string($phone) && preg_match('/^\+?\d{8,15}$/D', $phone) ? $phone : null;
@endphp
<table class="header" dir="ltr"><tr>
    <td style="width:43%"><img src="{{ public_path('assets/images/shop-and-go-logo-transparent.png') }}" style="width:70mm"><div class="muted">{{ $quotation['company_name'] }}</div></td>
    <td style="width:57%;text-align:right" dir="rtl"><div class="title">عرض سعر منتجات أمازون</div><div class="muted" dir="ltr">AMAZON PRODUCT QUOTATION</div><div style="margin-top:2mm" dir="ltr">SG-AMAZON-{{ str_pad($cart->id, 5, '0', STR_PAD_LEFT) }}</div></td>
</tr></table>
<table class="details"><tr>
    <td style="width:40%"><div class="label">العميل</div><div class="value">{{ $cart->incomingMessage?->name ?: 'عميلنا الكريم' }}</div>@if($phone)<div class="muted" dir="ltr">+{{ ltrim($phone, '+') }}</div>@endif</td>
    <td style="width:32%"><div class="label">تاريخ عرض السعر / المتجر</div><div class="value" dir="ltr">{{ \Carbon\Carbon::parse($quotation['prepared_at'])->format('Y/m/d') }}</div><div class="muted" dir="ltr">{{ \App\Models\CartQuote::amazonSite(data_get($cart->payload, 'storefront')) }}</div></td>
    <td style="width:28%"><div class="label">حالة الطلب</div><div class="value">{{ $cart->pdf_stamp }}</div><div class="muted" dir="ltr">{{ $cart->whatsappCartReference() }}</div></td>
</tr></table>
<table class="product"><thead><tr><th style="width:10%">القطعة</th><th style="width:60%">تفاصيل المنتج</th><th style="width:10%">الكمية</th><th style="width:20%">سعر القطعة</th></tr></thead><tbody><tr>
    <td style="text-align:center">1</td><td><div class="name">{{ $item['name'] ?? '' }}</div>@if(!empty($item['variant']))<div class="variant">{{ $item['variant'] }}</div>@endif
        @if($offer['notice'])<table class="offer-notice"><tr><td><strong>تنبيه بشأن عرض البائع</strong><br><span dir="ltr">{{ $offer['notice'] }}</span></td></tr></table>@endif
        @if($offer['seller'])<div class="offer-details" dir="ltr">{{ $offer['seller'] }}</div>@endif
        @if($offer['delivery'])<div class="offer-details"><div dir="ltr">{{ $offer['delivery'] }}</div><strong>{{ \App\Support\AmazonOfferDetails::DELIVERY_EXCLUSION }}</strong></div>@endif
        @if($images[0] ?? null)<div class="image" style="margin-top:{{ $offer['notice'] ? '3mm' : '5mm' }}"><img src="{{ $images[0] }}" style="max-width:{{ $offer['notice'] ? '35mm' : '45mm' }};max-height:{{ $offer['notice'] ? '35mm' : '45mm' }}"></div>@endif
        @foreach($offer['other_notes'] as $note)<div class="muted">{{ $note }}</div>@endforeach
        @if($discounted)<div class="discount">عليه خصم في أمازون حالياً.</div>@endif
    </td><td style="text-align:center">{{ $quantity }}</td><td class="price" dir="ltr">{{ $unitLyd }} LYD</td>
</tr></tbody></table>
<table class="total"><tr><td style="width:55%"><div style="font-size:17pt;font-weight:bold">الإجمالي بالدينار الليبي</div><div class="muted">{{ $quantity }} {{ $quantity === 1 ? 'قطعة' : 'قطع' }} · {{ $offer['delivery'] ? 'إجمالي المنتجات، دون رسوم توصيل البائع' : 'السعر النهائي لطلبك' }}</div></td><td class="amount" dir="ltr">{{ $quotation['total']['amount'] }} LYD</td></tr></table>
<div class="notes"><strong>تفاصيل الطلب</strong><br>رابط أمازون يمثل منتجاً واحداً. @if($quantity > 1)تم ضرب سعر القطعة في الكمية المطلوبة ({{ $quantity }}).@else الكمية المفترضة قطعة واحدة.@endif
    <br>يقرب الإجمالي النهائي إلى أقرب دينار. الأسعار والتوفر قابلان للتغيير قبل الشراء.
    <br>عرض السعر صادر من {{ $quotation['company_name'] }} لشراء المنتج من أمازون.</div>
<div style="margin-top:6mm;font-weight:bold">للرد على العرض، استخدم أزرار رسالة واتساب:</div>
<table class="actions"><tr><td>تأكيد الطلب</td><td>تعديل الكميات</td><td>إلغاء الطلب</td></tr></table>
</body></html>
