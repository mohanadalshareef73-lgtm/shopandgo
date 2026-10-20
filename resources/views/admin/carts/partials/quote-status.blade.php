@php
    $color = $cart->status === 'succeeded' ? 'success' : (in_array($cart->status, ['pending', 'submitted']) ? 'warning' : 'danger');
@endphp
<span class="badge bg-light-{{ $color }}">{{ \App\Models\CartQuote::QUOTE_STATUSES[$cart->status] ?? $cart->status }}</span>
@if (in_array($cart->last_error, [\App\Services\CartWalletPausedException::EMPTY, \App\Services\CartWalletPausedException::INSUFFICIENT], true))
    <small class="d-block text-warning mt-1">بانتظار شحن المحفظة — الرد متوقف مؤقتاً</small>
@elseif ($cart->last_error === \App\Services\CartWalletPausedException::UNAVAILABLE)
    <small class="d-block text-warning mt-1">الرد متوقف لحين التحقق من رصيد المحفظة</small>
@endif
