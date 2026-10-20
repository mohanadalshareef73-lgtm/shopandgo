{{-- زر رجوع (ما يطلعش في الصفحة الرئيسية) · $home = رابط الرئيسية، $float = عائم تحت --}}
@php
    $isHome = request()->routeIs('sg.home', 'admin.home', 'home', 'courier.home', 'customer.dashboard')
        && !request()->filled('d') && in_array(request('tab'), [null, '', 'home'], true);
@endphp
@unless ($isHome)
<button type="button" class="no-print sg-back" data-home="{{ $home }}" onclick="if (history.length > 1) { history.back(); } else { location.href = this.dataset.home; }"
    style="{{ !empty($float) ? 'position:fixed;bottom:92px;left:14px;z-index:60;box-shadow:0 6px 18px rgba(0,0,0,.18);' : 'margin:0 0 10px;' }}display:inline-flex;align-items:center;gap:6px;height:38px;padding:0 14px;border-radius:99px;border:1px solid #dfe2ea;background:#fff;color:#262c4a;font:inherit;font-size:14px;font-weight:700;cursor:pointer">
    <span style="font-size:17px">→</span> رجوع
</button>
@endunless
