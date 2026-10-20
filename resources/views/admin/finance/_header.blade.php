<div class="page-header">
    <div class="page-block">
        <div class="row align-items-center">
            <div class="col-md-12">
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('finance.index') }}">المالية</a></li>
                    <li class="breadcrumb-item" aria-current="page">{{ $title }}</li>
                </ul>
            </div>
            <div class="col-md-12">
                <div class="page-header-title">
                    <h2 class="mb-0">{{ $title }}</h2>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="fin-tabs">
    @can('finance-dashboard')
        <a href="{{ route('finance.index') }}" class="{{ request()->routeIs('finance.index') ? 'active' : '' }}"><i class="ti ti-chart-pie"></i> لوحة المالية</a>
    @endcan
    @can('trip-purchase-list')
        <a href="{{ route('finance.trips.index') }}" class="{{ request()->routeIs('finance.trips.*') ? 'active' : '' }}"><i class="ti ti-plane"></i> رحلات الشراء</a>
    @endcan
    @can('exchange-list')
        <a href="{{ route('finance.exchanges.index') }}" class="{{ request()->routeIs('finance.exchanges.*') ? 'active' : '' }}"><i class="ti ti-currency-dollar"></i> شراء دولار / USDT</a>
    @endcan
    @can('partner-list')
        <a href="{{ route('finance.partners.index') }}" class="{{ request()->routeIs('finance.partners.*') ? 'active' : '' }}"><i class="ti ti-users"></i> الشركاء</a>
    @endcan
    @can('treasury-list')
        <a href="{{ route('safe.history', 'dinar') }}"><i class="ti ti-building-bank"></i> الخزينة</a>
    @endcan
    @can('purchase-list')
        <a href="{{ route('purchase.index') }}"><i class="ti ti-receipt"></i> مصاريف الشركة</a>
    @endcan
    @can('shippingCharge-list')
        <a href="{{ route('shippingCharges.index') }}"><i class="ti ti-truck"></i> دفع شركة الشحن</a>
    @endcan
</div>
