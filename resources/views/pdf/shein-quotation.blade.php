<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><style>
body { font-family: somar; color: #182847; background: #fff; font-size: 12pt; }
table { border-collapse: collapse; width: 100%; }
td { vertical-align: middle; }
.accent { color: #1c45a3; }
.muted { color: #51617b; font-size: 9.5pt; line-height: 1.2; }
.header td { padding: 0 0 4mm; border-bottom: 0.6mm solid #1c45a3; }
.header-title { font-size: 24pt; font-weight: bold; color: #182847; margin-bottom: 2mm; }
.reference { font-size: 11pt; color: #51617b; }
.intro { margin: 3mm 0 5mm; }
.intro td { padding: 2mm 3mm; vertical-align: top; border-bottom: 0.2mm solid #e1e7f1; }
.info-label { font-size: 10.5pt; color: #51617b; }
.info-value { font-size: 12pt; margin: 1mm 0 2mm; }
.status-badge { width: 34mm; }
.status-badge td { border: 0; padding: 3mm 2mm; text-align: center; vertical-align: middle; font-size: 14pt; font-weight: bold; }
.products { table-layout: fixed; }
.products thead { display: table-header-group; }
.products th { background: #e9effb; color: #1c45a3; padding: 2mm 1mm; font-size: 9.5pt; font-weight: bold; }
.products tr { page-break-inside: avoid; }
.products td { padding: 2mm 1mm; border-bottom: 0.3mm solid #f2f2f2; background: #fff; }
.products-unavailable { margin-top: 5mm; }
.products .unavailable-title { background: #fff; color: #222; text-align: right; font-size: 17pt; padding: 3mm 1.5mm; }
.products-unavailable th { background: #f5f5f5; color: #999; }
.products tr.item-unavailable td { background: #fff; color: #b4b4b4; }
.products tr.item-unavailable .muted, .products tr.item-unavailable .index { color: #b4b4b4; }
.products tr.item-unavailable .line-total { color: #b4b4b4; }
.products .product { font-size: 11pt; line-height: 1.2; }
.products .number { text-align: center; font-size: 11.5pt; white-space: nowrap; }
.products .original { color: #999; text-decoration: line-through; }
.products .sale-price { color: #fa4b18; font-weight: bold; font-size: 12.5pt; }
.discount-badge { color: #8b4b24; background-color: #ffe6d5; font-size: 11pt; padding: 0.6mm 1.5mm; }
.unavailable-discount { color: #fff; background-color: #b4b4b4; }
.unavailable-note { color: #fff; background-color: #999; font-size: 9pt; padding: 1mm; }
.discount-note { color: #fa4b18; font-size: 9.5pt; font-weight: bold; margin-top: 1mm; }
.demand-note { color: #b7802d; font-size: 9.5pt; font-weight: normal; margin-top: 1mm; }
.unavailable-text { color: #b4b4b4; font-size: 9.5pt; line-height: 1.2; }
.note-icon { width: 3.2mm; height: 3.2mm; vertical-align: middle; }
.products .line-total { font-weight: bold; font-size: 12pt; color: #1c45a3; }
.thumbnail { max-width: 20mm; max-height: 25mm; }
.picture { text-align: center; }
.index { font-size: 10pt; color: #1c45a3; }
.quotation-summary { margin-top: 5mm; page-break-inside: avoid; }
.summary-content { padding: 0; }
.totals { page-break-inside: avoid; }
.totals .summary-title { font-size: 17pt; font-weight: bold; color: #1c45a3; padding: 0 0 3mm; border: 0; }
.totals td { padding: 2mm 4mm; font-size: 12pt; border-bottom: 0.2mm solid #e1e7f1; }
.totals .value { text-align: left; white-space: nowrap; font-size: 13pt; }
.totals .original-total td { color: #999; }
.totals .original-total .value { text-decoration: line-through; }
.totals .sale-total td { color: #fa4b18; font-weight: bold; }
.totals .grand td { background: #1c45a3; color: #fff; padding: 4mm; font-size: 14pt; border: 0; }
.totals .grand .value { font-size: 22pt; font-weight: bold; }
.totals .grand-secondary td { background: #153578; color: #fff; padding: 2.5mm 3mm; }
.totals .grand-secondary .value { font-size: 18pt; font-weight: bold; }
.notes { margin-top: 4mm; }
.notes td { background: #f7f9fd; padding: 4mm; font-size: 11.5pt; line-height: 1.5; }
.closing { margin-top: 4mm; font-size: 11.5pt; }
a { color: #1c45a3; text-decoration: none; }
</style></head>
<body>
@php
    [$stampColor, $stampBackground] = match ($cart->operator_status) {
        'purchased', 'confirmed' => ['#18734b', '#e6f5ec'],
        'dismissed', 'cancelled' => ['#9c4545', '#fcebed'],
        'buying' => ['#9e6409', '#fff2d8'],
        'following_up', 'editing' => ['#6556ae', '#efecfc'],
        default => ['#1c45a3', '#e9effb'],
    };
    $itemGroups = [
        'available' => array_filter($quotation['items'], fn ($item) => $item['availability'] === 'available'),
        'unavailable' => array_filter($quotation['items'], fn ($item) => $item['availability'] === 'unavailable'),
    ];
    $priceNoteIcon = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path d="M2 2v12h12M4 4l4 4 3-3 3 6M10 11h4V7" fill="none" stroke="#fa4b18" stroke-width="1.7"/></svg>');
    $demandNoteIcon = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path d="M9 1c1 5-2 5-2 8-2-1-2-3-2-3-3 4-3 9 3 9s7-7 1-14z" fill="#b7802d"/></svg>');
@endphp
<table class="header" dir="ltr"><tr>
    <td style="width:48%">
        <img src="{{ public_path('assets/images/shop-and-go-logo-transparent.png') }}" style="width:78mm">
    </td>
    <td style="width:52%;text-align:right" dir="rtl">
        <div class="header-title">تفاصيل سلة {{ $cart->retailer_label }}</div>
        <div class="reference">رقم المرجع <span dir="ltr">SG-{{ strtoupper($cart->retailer ?? 'shein') }}-{{ str_pad($cart->id, 5, '0', STR_PAD_LEFT) }}</span></div>
    </td>
</tr></table>

<table class="intro" dir="rtl"><tr>
    <td style="width:44%;padding-right:0">
        <div class="info-label">العميل</div>
        <div class="info-value"><strong>{{ $quotation['customer_name'] ?: 'عميلنا الكريم' }}</strong></div>
        <div class="info-label">رقم واتساب</div>
        <div class="info-value"><span dir="ltr">+{{ ltrim($quotation['customer_phone'], '+') }}</span></div>
    </td>
    <td style="width:30%">
        <div class="info-label">تاريخ العرض</div>
        <div class="info-value" dir="ltr">{{ \Carbon\Carbon::parse($quotation['prepared_at'])->format('Y/m/d') }}</div>
        <div class="info-label">المتجر / عدد القطع</div>
        <div class="info-value"><span dir="ltr">{{ data_get($cart->payload, 'storefront', '-') }} / {{ $quotation['item_count'] }}</span></div>
    </td>
    <td style="width:26%;padding-left:0">
        <div class="info-label" style="text-align:left;margin-bottom:2mm">حالة السلة</div>
        <table class="status-badge" align="left"><tr><td style="color:{{ $stampColor }};background:{{ $stampBackground }}">
            {{ $cart->pdf_stamp }}
        </td></tr></table>
    </td>
</tr></table>

@foreach ($itemGroups as $availability => $items)
@if (count($items))
<table class="products {{ $availability === 'unavailable' ? 'products-unavailable' : '' }}" dir="rtl">
<thead>
@if ($availability === 'unavailable')
<tr><th colspan="6" class="unavailable-title">غير متوفر في هذا الموقع</th></tr>
@endif
<tr>
    <th style="width:12%">الصورة</th>
    <th style="width:46%">تفاصيل المنتج</th>
    <th style="width:6%">الكمية</th>
    <th style="width:11%">السعر الأصلي<br><span dir="ltr">USD / قطعة</span></th>
    <th style="width:11%">سعر التخفيض<br><span dir="ltr">USD / قطعة</span></th>
    <th style="width:14%">إجمالي المنتج<br><span dir="ltr">LYD</span></th>
</tr></thead>
<tbody>
@foreach ($items as $index => $item)
<tr @class(['item-unavailable' => $item['availability'] === 'unavailable'])>
    <td class="picture">
        @if ($display['items'][$index]['discount_percent'] !== null)
            <div><span dir="ltr" class="discount-badge {{ $availability === 'unavailable' ? 'unavailable-discount' : '' }}">-{{ $display['items'][$index]['discount_percent'] }}%</span></div>
        @endif
        @if ($images[$index] ?? null)<img class="thumbnail" src="{{ $images[$index] }}">@else<span class="muted">لا توجد صورة</span>@endif
        @if ($availability === 'unavailable')
            @foreach (($item['provider']['notes'] ?? []) as $note)
                @if (is_string($note) && preg_match('/sold out|\bleft\b/i', $note))
                    <div class="unavailable-note" dir="ltr">{{ $note }}</div>
                @endif
            @endforeach
        @endif
        <div class="{{ $availability === 'unavailable' ? 'unavailable-text' : 'index' }}">{{ $item['line_no'] }}</div>
    </td>
    <td class="product">
        <div dir="{{ preg_match('/[\x{0600}-\x{06FF}]/u', $item['name']) ? 'rtl' : 'ltr' }}"><strong>{{ $item['name'] }}</strong></div>
        @if ($item['variant'])<div class="{{ $availability === 'unavailable' ? 'unavailable-text' : 'muted' }}" dir="ltr">{{ $item['variant'] }}</div>@endif
        @if ($availability === 'available')
            @foreach (($item['provider']['notes'] ?? []) as $note)
                @if (is_string($note) && trim($note) !== '')
                    @php $isDemandNote = preg_match('/sold|\bleft\b|buying/i', $note) === 1; @endphp
                    <div class="{{ $isDemandNote ? 'demand-note' : 'discount-note' }}" dir="{{ preg_match('/[\x{0600}-\x{06FF}]/u', $note) ? 'rtl' : 'ltr' }}"><img class="note-icon" src="{{ $isDemandNote ? $demandNoteIcon : $priceNoteIcon }}"> {{ $note }}</div>
                @endif
            @endforeach
        @endif
        <div class="{{ $availability === 'unavailable' ? 'unavailable-text' : 'muted' }}">{{ $item['availability'] === 'available' ? 'متوفر' : 'غير متوفر - غير مشمول بالإجمالي' }}</div>
        @if ($item['qty_source'] === 'assumed_one')<div class="{{ $availability === 'unavailable' ? 'unavailable-text' : 'muted' }}">الكمية تقديرية - تحتاج إلى تأكيد</div>@endif
        @if ($display['items'][$index]['unit_lyd'] !== null)<div class="muted">سعر العميل للقطعة: <span dir="ltr">{{ $display['items'][$index]['unit_lyd'] }} LYD</span></div>@endif
    </td>
    @if ((int) $item['qty'] > 1 && $availability === 'available')
    <td class="number" style="background:#e8590c;color:#ffffff;font-size:16pt;font-weight:bold">×{{ $item['qty'] }}<div style="font-size:7pt;font-weight:bold">مكرر</div></td>
    @else
    <td class="number">{{ $item['qty'] }}</td>
    @endif
    <td class="number original" dir="ltr">{{ $display['items'][$index]['original_usd'] ?? '-' }}</td>
    <td class="number {{ $availability === 'available' ? 'sale-price' : '' }}" dir="ltr">{{ $display['items'][$index]['sale_usd'] ?? '-' }}</td>
    <td class="number line-total" dir="ltr">{{ $display['items'][$index]['line_lyd'] ?? '-' }}</td>
</tr>
@endforeach
</tbody></table>
@endif
@endforeach

<table class="quotation-summary"><tr><td class="summary-content">
        <table class="totals" dir="rtl">
            <tr><td colspan="2" class="summary-title">ملخص السلة</td></tr>
            <tr><td>عدد القطع / الكمية المتوفرة</td><td class="value" dir="ltr">{{ $quotation['item_count'] }} / {{ $quotation['available_quantity'] }}</td></tr>
            @if ($display['original_usd_total'] !== null)<tr class="original-total"><td>قيمة المنتجات قبل التخفيض</td><td class="value" dir="ltr">{{ $display['original_usd_total'] }} USD</td></tr>@endif
            <tr class="sale-total"><td>قيمة المنتجات بعد التخفيض</td><td class="value" dir="ltr">{{ $display['sale_usd_total'] }} USD</td></tr>
            <tr><td>سعر الصرف لكل دولار</td><td class="value" dir="ltr">{{ $display['exchange_rate'] }} LYD</td></tr>
            <tr class="grand"><td>الإجمالي النهائي بالدينار</td><td class="value" dir="ltr">{{ $display['total_lyd'] }} LYD</td></tr>
            <tr class="grand-secondary"><td>الإجمالي النهائي بالدولار</td><td class="value" dir="ltr">{{ $display['total_usd'] }} USD</td></tr>
        </table>

<table class="notes"><tr><td>
    <strong>ملاحظات العرض</strong><br>
    {!! nl2br(e($display['customer_note'])) !!}
</td></tr></table>
<table class="closing"><tr>
    <td><a href="{{ $cart->source_url }}">فتح رابط {{ $cart->retailer_label }} الأصلي</a></td>
    <td style="text-align:left" dir="ltr"><img src="{{ public_path('assets/images/shop-and-go-logo-transparent.png') }}" style="width:43mm"></td>
</tr></table>
</td></tr></table>
</body></html>
