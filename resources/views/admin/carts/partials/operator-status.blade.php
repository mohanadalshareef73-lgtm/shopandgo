@php
    $colors = ['new' => 'primary', 'following_up' => 'info', 'editing' => 'warning', 'confirmed' => 'success',
        'buying' => 'warning', 'purchased' => 'success', 'cancelled' => 'danger', 'dismissed' => 'secondary'];
@endphp
<span class="badge bg-light-{{ $colors[$cart->operator_status] ?? 'secondary' }}">
    {{ \App\Models\CartQuote::OPERATOR_STATUSES[$cart->operator_status] ?? $cart->operator_status }}
</span>
