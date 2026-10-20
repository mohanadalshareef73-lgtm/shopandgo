@extends('layout.master')

@php
    $retailer = $filters['retailer'] ?? null;
    $isAmazon = $retailer === 'amazon';
    $pageTitle = 'السلات';
@endphp

@section('title')
    <title>{{ $pageTitle }} | Carts</title>
@endsection

@section('style')
    @include('admin.carts.partials.page-styles')
@endsection

@section('content')
    <section class="pc-container shein-page">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item" aria-current="page">{{ $pageTitle }}</li>
                    </ul>
                    <div class="shein-heading">
                        <div>
                            <div class="shein-eyebrow"><span class="shein-wordmark" dir="ltr">CARTS</span><span>السلات وعروض الأسعار</span></div>
                            <h2>{{ $pageTitle }}</h2>
                            <p>سلات شي إن وروابط آي هيرب ومنتجات أمازون، من عرض السعر إلى تأكيد الطلب.</p>
                        </div>
                        @can('cart-create')
                            <div class="shein-heading-actions">
                                @foreach (['shein' => 'فحص سلة شي إن', 'iherb' => 'فحص آي هيرب', 'amazon' => 'فحص منتج أمازون'] as $store => $checkLabel)
                                    @if ($storeStates[$store])
                                        <a href="{{ route('carts.create', $store === 'shein' ? [] : ['retailer' => $store]) }}" class="btn btn-primary"><i class="ti ti-plus me-1" aria-hidden="true"></i>{{ $checkLabel }}</a>
                                    @else
                                        <span class="btn btn-outline-secondary disabled" aria-disabled="true">{{ $checkLabel }} · متوقف</span>
                                    @endif
                                @endforeach
                            </div>
                        @endcan
                    </div>
                </div>
            </div>

            @include('layout.validation-messages')
            @if($isAmazon)
                @include('admin.carts.partials.amazon-pricing')
                <p class="text-muted">تظهر هنا منتجات أمازون المرسلة عبر واتساب أو المضافة للفحص يدوياً.</p>
            @endif

            <div class="shein-overview" aria-label="ملخص السلات">
                <div class="shein-metric"><span>إجمالي السلات</span><strong class="shein-number">{{ $counts->sum() }}</strong></div>
                <div class="shein-metric"><span>بانتظار المتابعة</span><strong class="shein-number">{{ ($counts['new'] ?? 0) + ($counts['following_up'] ?? 0) + ($counts['editing'] ?? 0) }}</strong></div>
                <div class="shein-metric"><span>طلبات مؤكدة</span><strong class="shein-number">{{ ($counts['confirmed'] ?? 0) + ($counts['buying'] ?? 0) + ($counts['purchased'] ?? 0) }}</strong></div>
                <div class="shein-metric"><span>ملغاة أو مستبعدة</span><strong class="shein-number">{{ ($counts['cancelled'] ?? 0) + ($counts['dismissed'] ?? 0) }}</strong></div>
            </div>

            <nav class="shein-status-nav" aria-label="تصفية حسب حالة المتابعة">
                <a href="{{ route('carts.index', array_filter(['retailer' => $retailer, 'search' => $filters['search'] ?? null, 'session_uuid' => $filters['session_uuid'] ?? null, 'per_page' => $perPage])) }}"
                    class="shein-status-tab {{ empty($filters['operator_status']) ? 'is-active' : '' }}" @if(empty($filters['operator_status'])) aria-current="page" @endif>
                    الكل <span class="shein-count">{{ $counts->sum() }}</span>
                </a>
                @foreach (\App\Models\CartQuote::OPERATOR_STATUSES as $status => $label)
                    <a href="{{ route('carts.index', array_filter(['retailer' => $retailer, 'search' => $filters['search'] ?? null, 'session_uuid' => $filters['session_uuid'] ?? null, 'operator_status' => $status, 'per_page' => $perPage])) }}"
                        class="shein-status-tab {{ ($filters['operator_status'] ?? null) === $status ? 'is-active' : '' }}" @if(($filters['operator_status'] ?? null) === $status) aria-current="page" @endif>
                        {{ $label }} <span class="shein-count">{{ $counts[$status] ?? 0 }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="card">
                <div class="card-body">
                    <form method="get" action="{{ route('carts.index') }}" class="shein-toolbar">
                        @if (! empty($filters['operator_status']))
                            <input type="hidden" name="operator_status" value="{{ $filters['operator_status'] }}">
                        @endif
                        <div class="shein-search">
                            <label class="form-label" for="cart-search">البحث عن سلة أو عميل</label>
                            <input id="cart-search" name="search" type="search" class="form-control"
                                value="{{ $filters['search'] ?? '' }}" maxlength="200"
                                placeholder="اسم العميل، رقم واتساب، #رقم السلة أو الرابط">
                        </div>
                        <div class="shein-page-size">
                            <label class="form-label" for="cart-retailer">المتجر</label>
                            <select class="form-select" id="cart-retailer" name="retailer">
                                <option value="">كل المتاجر</option>
                                <option value="shein" @selected($retailer === 'shein')>شي إن</option>
                                <option value="amazon" @selected($isAmazon)>أمازون</option>
                                <option value="iherb" @selected($retailer === 'iherb')>آي هيرب</option>
                            </select>
                        </div>
                        <div class="shein-session-filter">
                            <label class="form-label" for="cart-session">رقم / جلسة الاستقبال</label>
                            <select class="form-select" id="cart-session" name="session_uuid">
                                <option value="">كل جلسات واتساب</option>
                                @foreach ($receivingSessions as $uuid => $session)
                                    <option value="{{ $uuid }}" @selected(($filters['session_uuid'] ?? '') === $uuid)>{{ $session['phone'] ? $session['phone'].' · ' : '' }}{{ $session['name'] ?? $uuid }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="shein-page-size">
                            <label class="form-label" for="cart-per-page">عدد السلات في الصفحة</label>
                            <select class="form-select" id="cart-per-page" name="per_page">
                                @foreach ([10, 25, 50, 100] as $size)
                                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} سلة</option>
                                @endforeach
                            </select>
                        </div>
                        <div><button class="btn btn-primary" type="submit">عرض النتائج</button></div>
                        @if (! empty($filters['search']) || ! empty($filters['operator_status']) || ! empty($filters['session_uuid']))
                            <div><a href="{{ route('carts.index', array_filter(['retailer' => $retailer, 'per_page' => $perPage])) }}" class="btn btn-outline-secondary">إلغاء التصفية</a></div>
                        @endif
                    </form>
                </div>
                <div class="shein-list-labels" aria-hidden="true"><span>السلة والمنتجات</span><span>العميل</span><span>الإجمالي</span><span>الحالة</span><span></span></div>
                <div class="shein-cart-list">
                    @forelse ($carts as $cart)
                        @php($previewItems = array_slice(data_get($cart->payload, 'result.items', []), 0, 3, true))
                        <article class="shein-cart-row">
                            <a href="{{ route('carts.show', $cart) }}" class="shein-cart-preview" aria-label="عرض {{ $cart->retailer === 'amazon' ? 'منتج أمازون' : 'السلة' }} #{{ $cart->id }}">
                                <div class="shein-cart-thumbnails">
                                    @forelse ($previewItems as $imageIndex => $imageItem)
                                        @include('admin.carts.partials.product-image', ['imageCart' => $cart])
                                    @empty
                                        <span class="shein-preview-empty"><i class="ti ti-shopping-bag" aria-hidden="true"></i></span>
                                    @endforelse
                                </div>
                                <div class="shein-cart-caption"><strong class="shein-cart-id">{{ $cart->retailer_label }} · {{ $cart->retailer === 'amazon' ? 'منتج' : 'سلة' }} #{{ $cart->id }}</strong>
                                    <span class="shein-secondary">{{ count(data_get($cart->payload, 'result.items', [])) }} منتجات · {{ $cart->origin === 'manual' ? 'إضافة يدوية' : 'من واتساب' }}</span>
                                </div>
                                @if($cart->retailer === 'amazon')<div class="fw-semibold mt-1">{{ data_get($cart->payload, 'result.items.0.name', 'منتج أمازون قيد الفحص') }}</div>@endif
                                @include('admin.carts.partials.receiving-source', ['compact' => true])
                            </a>
                            <div class="shein-customer">
                                <span class="shein-avatar" aria-hidden="true">{{ mb_substr($cart->incomingMessage?->name ?: 'ع', 0, 1) }}</span>
                                <div><div class="fw-semibold">{{ $cart->incomingMessage?->name ?? 'غير محدد' }}</div>
                                    @if ($cart->contact_phone)<div class="shein-secondary" dir="ltr">{{ $cart->contact_phone }}</div>
                                    @else <div class="shein-secondary">رقم الهاتف غير متاح</div>@endif
                                    <time class="shein-secondary d-block" dir="ltr" datetime="{{ $cart->created_at->toIso8601String() }}">{{ $cart->created_at->format('Y-m-d H:i') }}</time>
                                </div>
                            </div>
                            <div class="shein-row-total">
                                @if ($cart->status === 'succeeded')
                                    <span class="shein-total" dir="ltr">{{ data_get($viewTotals, $cart->id.'.amount', data_get($cart->payload, 'result.total.amount')) }} <small>{{ data_get($viewTotals, $cart->id.'.currency', data_get($cart->payload, 'result.total.currency')) }}</small></span>
                                    <span class="d-block shein-secondary">{{ data_get($viewTotals, $cart->id.'.item_count', data_get($cart->payload, 'result.itemCount')) }} قطع</span>
                                @else
                                    <span class="text-muted">لم يجهز العرض بعد</span>
                                @endif
                            </div>
                            <div class="shein-row-status">
                                @include('admin.carts.partials.operator-status')
                                <div class="mt-2">@include('admin.carts.partials.quote-status')</div>
                                @if ($cart->notified_at)<small class="d-block shein-secondary">تم إرسال الرد للعميل</small>@endif
                            </div>
                            <div class="shein-actions">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('carts.show', $cart) }}">عرض ومتابعة <i class="ti ti-arrow-left" aria-hidden="true"></i></a>
                                @if ($cart->whatsapp_url)
                                    <a class="btn btn-sm btn-light-success" href="{{ $cart->whatsapp_url }}" target="_blank" rel="noopener noreferrer" aria-label="واتساب {{ $cart->incomingMessage?->name }}"><i class="ti ti-brand-whatsapp" aria-hidden="true"></i></a>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="shein-empty"><i class="ti ti-shopping-bag shein-empty-icon" aria-hidden="true"></i>
                            <h5>{{ $isAmazon ? 'لا توجد عروض أمازون' : 'لا توجد سلات' }}{{ ! empty($filters['search']) || ! empty($filters['operator_status']) || ! empty($filters['session_uuid']) ? ' مطابقة للبحث' : ' حتى الآن' }}.</h5>
                            <p class="text-muted mb-0">{{ ! empty($filters['search']) || ! empty($filters['operator_status']) || ! empty($filters['session_uuid']) ? 'غيّر البحث أو ألغِ التصفية لعرض بقية السلات.' : 'ستظهر هنا سلات شي إن وروابط آي هيرب ومنتجات أمازون التي يرسلها العملاء عبر واتساب أو تضيفها للفحص.' }}</p>
                        </div>
                    @endforelse
                </div>
                <div class="shein-pagination">
                    <span>{{ $carts->firstItem() ?? 0 }}–{{ $carts->lastItem() ?? 0 }} من {{ $carts->total() }} سلة</span>
                    {{ $carts->links('pagination::bootstrap-5') }}
                </div>
            </div>
            @include('admin.carts.partials.wallet')
        </div>
    </section>
@endsection

@section('script')
@include('admin.carts.partials.image-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('cart-retailer').addEventListener('change', function () { this.form.requestSubmit(); });
    document.getElementById('cart-per-page').addEventListener('change', function () { this.form.requestSubmit(); });
    document.getElementById('cart-session').addEventListener('change', function () { this.form.requestSubmit(); });
    const panel = document.getElementById('shein-wallet');
    const refresh = document.getElementById('wallet-refresh');
    const status = document.getElementById('wallet-status');
    const pauseStatus = document.getElementById('wallet-pause-status');
    const amounts = panel.querySelectorAll('[data-wallet-amount]');

    async function loadWallet() {
        refresh.disabled = true;
        panel.setAttribute('aria-busy', 'true');
        status.className = 'small text-muted mb-0';
        status.textContent = 'جارٍ تحديث رصيد المحفظة…';
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 12000);
        try {
            const response = await fetch(panel.dataset.url, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin', cache: 'no-store', signal: controller.signal
            });
            const data = await response.json();
            pauseStatus.hidden = !data.cart_replies_paused;
            pauseStatus.textContent = response.ok
                ? 'ردود جميع السلات متوقفة لحين شحن المحفظة. ستستأنف تلقائياً.'
                : 'ردود جميع السلات متوقفة مؤقتاً لحين التحقق من رصيد المحفظة.';
            if (!response.ok) throw new Error('Wallet unavailable');
            amounts.forEach(element => {
                const money = data.wallet[element.dataset.walletAmount];
                element.textContent = money.amount + ' ' + money.currency;
            });
            const time = new Date(data.checked_at).toLocaleTimeString('ar-LY');
            status.textContent = 'آخر تحديث: ' + time;
        } catch (error) {
            pauseStatus.hidden = panel.dataset.pauseOnEmptyWallet !== '1';
            pauseStatus.textContent = 'ردود جميع السلات متوقفة مؤقتاً لحين التحقق من رصيد المحفظة.';
            amounts.forEach(element => { element.textContent = '—'; });
            status.className = 'small text-danger mb-0';
            status.textContent = 'تعذر جلب رصيد المحفظة. حاول التحديث مجدداً.';
        } finally {
            clearTimeout(timer);
            panel.setAttribute('aria-busy', 'false');
            refresh.disabled = false;
        }
    }

    refresh.addEventListener('click', loadWallet);
    loadWallet();
});
</script>
@endsection
