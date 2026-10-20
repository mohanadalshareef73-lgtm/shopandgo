@php
    $amazonSettings = $settings ?? \App\Models\Setting::find(1);
    $amazonRules = isset($cart) && $cart->amazon_pricing
        ? data_get($cart->amazon_pricing, 'rules', ['fixed_fee_usd' => '5', 'flat_surcharge_usd' => '10', 'threshold_usd' => '100', 'percentage' => '10'])
        : ['fixed_fee_usd' => $amazonSettings?->amazon_fixed_fee_usd ?? '5', 'flat_surcharge_usd' => $amazonSettings?->amazon_flat_surcharge_usd ?? '10',
            'threshold_usd' => $amazonSettings?->amazon_threshold_usd ?? '100', 'percentage' => $amazonSettings?->amazon_percentage ?? '10'];
    $amazonRules = array_map(fn ($value) => (string) \Brick\Math\BigDecimal::of($value)->stripTrailingZeros(), $amazonRules);
@endphp
<div class="alert alert-light border" role="note">
    <strong>تسعير منتجات أمازون — <bdi>{{ \App\Models\CartQuote::amazonSite(isset($cart) ? data_get($cart->payload, 'storefront') : null) }}</bdi></strong>
    <p class="mt-2 mb-1">سعر المنتج بالدولار + {{ $amazonRules['fixed_fee_usd'] }} دولارات + هامش {{ $amazonRules['flat_surcharge_usd'] }} دولارات إذا كان سعر المنتج أقل من {{ $amazonRules['threshold_usd'] }} دولار، أو {{ $amazonRules['percentage'] }}% من سعر المنتج إذا كان {{ $amazonRules['threshold_usd'] }} دولار أو أكثر.</p>
    <small>يحسب السعر لكل قطعة، ثم يضرب في الكمية المطلوبة ويحول إلى الدينار بسعر الصرف المحفوظ للعرض. يقرب الإجمالي النهائي إلى أقرب دينار.</small>
    @if (isset($cart) && $cart->amazon_pricing)
        <dl class="row mt-3 mb-0">
            <dt class="col-sm-6">سعر المنتج الأساسي</dt><dd class="col-sm-6"><bdi>{{ $cart->amazon_pricing['base_unit_usd'] }} USD</bdi></dd>
            <dt class="col-sm-6">الإضافة الثابتة لكل قطعة</dt><dd class="col-sm-6"><bdi>{{ $cart->amazon_pricing['fixed_unit_fee_usd'] }} USD</bdi></dd>
            <dt class="col-sm-6">هامش كل قطعة</dt><dd class="col-sm-6"><bdi>{{ $cart->amazon_pricing['unit_surcharge_usd'] }} USD</bdi></dd>
            <dt class="col-sm-6">سعر العميل لكل قطعة</dt><dd class="col-sm-6"><bdi>{{ $cart->amazon_pricing['customer_unit_usd'] }} USD</bdi></dd>
            <dt class="col-sm-6">الكمية المطلوبة</dt><dd class="col-sm-6">{{ $cart->amazon_pricing['quantity'] }}</dd>
            <dt class="col-sm-6">سعر الصرف المحفوظ</dt><dd class="col-sm-6"><bdi>{{ $cart->amazon_pricing['exchange_rate'] }} LYD/USD</bdi></dd>
        </dl>
    @endif
</div>
