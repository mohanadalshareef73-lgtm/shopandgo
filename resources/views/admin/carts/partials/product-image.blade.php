<span class="shein-product-image">
    <span class="shein-image-placeholder" aria-hidden="true"><i class="ti ti-photo"></i><small>لا توجد صورة</small></span>
    @if (is_string($imageItem['imageUrl'] ?? null) && $imageItem['imageUrl'] !== '')
        <img src="{{ route('carts.products.image', [$imageCart, $imageIndex]) }}"
            alt="{{ $imageItem['name'] ?? 'صورة المنتج' }}" loading="lazy" decoding="async" data-product-image>
    @endif
</span>
