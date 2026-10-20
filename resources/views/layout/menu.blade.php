<ul class="pc-navbar">
    <li class="pc-item pc-caption">
        <label>لوحة التحكم</label>
        <i class="ti ti-dashboard"></i>
    </li>


    <li class="pc-item {{ request()->routeIs('admin.home') ? 'active' : '' }}">
        <a href="{{ route('admin.home') }}" class="pc-link">
            <span class="pc-micon">
                <svg class="pc-icon">
                    <use xlink:href="#custom-home"></use>
                </svg>
            </span>
            <span class="pc-mtext">الرئيسية</span></a>
    </li>

    @can('customer-list')
    <li class="pc-item pc-hasmenu">
        <a href="{{ route('customer.index') }}" class="pc-link">
            <span class="pc-micon">
                <i class="fas fa-shopping-cart" style="font-size: 17px;"></i></span>
            <span class="pc-mtext">الزبائن</span></a>
    </li>
    @endcan

    @can('cart-list')
    <li class="pc-item {{ request()->routeIs('carts.*') ? 'active' : '' }}">
        <a href="{{ route('carts.index') }}" class="pc-link">
            <span class="pc-micon"><i class="ti ti-shopping-cart" style="font-size: 25px;"></i></span>
            <span class="pc-mtext">السلات</span>
        </a>
    </li>
    @endcan

    {{-- @can('trip-list')
    <li class="pc-item pc-hasmenu">
        <a href="#!" class="pc-link">
            <span class="pc-micon">
                <i class="fas fa-ship"></i></span>
            <span class="pc-mtext">الرحلات</span><span class="pc-arrow"><i
                    data-feather="chevron-right"></i></span></a>
        <ul class="pc-submenu">
            <li class="pc-item"><a class="pc-link" href="{{ route('trip.index') }}">كل الرحلات</a></li>
            <li class="pc-item"><a class="pc-link" href="{{ route('trip.onway.index') }}">رحلات في الطريق</a></li>
            <li class="pc-item"><a class="pc-link" href="{{ route('trip.arrived.index') }}">رحلات تم وصولها</a></li>
            <li class="pc-item"><a class="pc-link" href="{{ route('trip.delay.index') }}">رحلات متأخرة</a></li>
        </ul>
    </li>
    @endcan --}}

    @can('order-list')
    <li class="pc-item pc-hasmenu">
        <a href="#!" class="pc-link">
            <span class="pc-micon">
                <i class="ti ti-package" style="font-size: 25px;"></i></span>
            <span class="pc-mtext">الطلبات</span><span class="pc-arrow"><i
                    data-feather="chevron-right"></i></span></a>
        <ul class="pc-submenu">
            <li class="pc-item"><a class="pc-link" href="{{ route('order.index') }}">كل الطلبات</a></li>
            {{-- <li class="pc-item"><a class="pc-link" href="{{ route('order.ready.index') }}">طلبات جاهزة للتسليم</a></li>
            <li class="pc-item"><a class="pc-link" href="{{ route('order.unpaid.index') }}">طلبات غير مدفوعة</a></li> --}}
            <li class="pc-item"><a class="pc-link" href="{{ route('order.delivered.index') }}">طلبات مستلمة</a></li>
            <li class="pc-item"><a class="pc-link" href="{{ route('order.notDelivered.index') }}">طلبات غير مستلمة</a></li>
        </ul>
    </li>
    @endcan

    @canany(['finance-dashboard','trip-purchase-list','exchange-list','partner-list'])
    <li class="pc-item pc-hasmenu {{ request()->routeIs('finance.*') ? 'active pc-trigger' : '' }}">
        <a href="#!" class="pc-link">
            <span class="pc-micon"><i class="ti ti-report-money" style="font-size: 25px;"></i></span>
            <span class="pc-mtext">المالية</span><span class="pc-arrow"><i data-feather="chevron-right"></i></span></a>
        <ul class="pc-submenu">
            @can('finance-dashboard')<li class="pc-item"><a class="pc-link" href="{{ route('finance.index') }}">لوحة المالية</a></li>@endcan
            @can('trip-purchase-list')<li class="pc-item"><a class="pc-link" href="{{ route('finance.trips.index') }}">رحلات الشراء</a></li>@endcan
            @can('exchange-list')<li class="pc-item"><a class="pc-link" href="{{ route('finance.exchanges.index') }}">شراء دولار / USDT</a></li>@endcan
            @can('partner-list')<li class="pc-item"><a class="pc-link" href="{{ route('finance.partners.index') }}">الشركاء</a></li>@endcan
        </ul>
    </li>
    @endcanany

    @canany(['purchase-list','shippingCharge-list','paymentExpenses-list'])
    <li class="pc-item pc-hasmenu">
        <a href="#!" class="pc-link">
            <span class="pc-micon">
                <i class="fas fa-money-bill-wave"></i></span>
            <span class="pc-mtext">المصروفات</span><span class="pc-arrow"><i
                    data-feather="chevron-right"></i></span></a>
        <ul class="pc-submenu">
            @can('purchase-list')
                <li class="pc-item"><a class="pc-link" href="{{ route('purchase.index') }}">المشتريات</a></li>
            @endcan
            @can('shippingCharge-list')
                <li class="pc-item"><a class="pc-link" href="{{ route('shippingCharges.index') }}">مصروفات الشحن</a></li>
            @endcan
            @can('paymentExpenses-list')
                <li class="pc-item"><a class="pc-link" href="{{ route('purchase.report') }}">التقرير</a></li>
            @endcan
        </ul>
    </li>
    @endcan


        @can('profits-list')

    <li class="pc-item pc-hasmenu">
        <a href="{{ route('profits.index') }}" class="pc-link">
            <span class="pc-micon">
                <i class="fas fa-chart-line" style="font-size: 17px;"></i></span>
            <span class="pc-mtext">الأربـاح</span></a>
    </li>

    @endcan


    @canany(['dept-list'])
    <li class="pc-item pc-hasmenu">
        <a href="#!" class="pc-link">
            <span class="pc-micon">
                <i class="fas fa-hand-holding-usd" style="font-size: 22px;"></i></span>
            <span class="pc-mtext">الديـون</span><span class="pc-arrow"><i
                    data-feather="chevron-right"></i></span></a>
        <ul class="pc-submenu">
            @can('purchase-list')
                <li class="pc-item"><a class="pc-link" href="{{ route('dept.index') }}">كل الديـون</a></li>
            @endcan
            @can('shippingCharge-list')
                <li class="pc-item"><a class="pc-link" href="{{ route('dept.unpaidDepts') }}">الديـون الغير مسددة</a></li>
            @endcan
            @can('paymentExpenses-list')
                <li class="pc-item"><a class="pc-link" href="{{ route('dept.paidDept') }}">الديـون المسددة</a></li>
            @endcan
        </ul>
    </li>
    @endcan

    @can('reports-list')

    <li class="pc-item pc-hasmenu">
        <a href="{{ route('reports.index') }}" class="pc-link">
            <span class="pc-micon">
                <i class="fas fa-file-alt" style="font-size: 17px;"></i></span>
            <span class="pc-mtext">التقارير</span></a>
    </li>

    @endcan

    @can('notification-list')
    <li class="pc-item pc-hasmenu">
        <a href="{{ route('notification.index') }}" class="pc-link">
            <span class="pc-micon">
                <svg class="pc-icon">
                    <use xlink:href="#custom-notification"></use>
                </svg>
            </span>
            <span class="pc-mtext">الاشعارات</span></a>
    </li>
    @endcan


    @canany(['system-list', 'system-edit', 'log-list', 'platform-list', 'user-list', 'role-list', 'shipping_type-list', 'purchase_description-list', 'status-list', 'invoice-list'])
    <li class="pc-item pc-hasmenu">
        <a href="#!" class="pc-link"><span class="pc-micon">
                <svg class="pc-icon">
                    <use xlink:href="#custom-setting-2"></use>
                </svg> </span><span class="pc-mtext">إعدادات النظام</span><span class="pc-arrow"><i
                    data-feather="chevron-right"></i></span></a>
        <ul class="pc-submenu">

            @canany(['system-list', 'system-edit'])
            <li class="pc-item"><a class="pc-link" href="{{ route('settings.edit') }}">إعدادات شي إن وأمازون · سعر الصرف USD → LYD</a></li>
            <li class="pc-item"><a class="pc-link" href="{{ route('settings.whatsapp.edit') }}">إعدادات واتساب</a></li>
            @endcanany

            @can('platform-list')
            <li class="pc-item"><a class="pc-link" href="{{ route('platform.index') }}">المنصات</a></li>
         @endcan


        @can('aiRoles-list')
            <li class="pc-item"><a class="pc-link" href="{{ route('ai-roles.index') }}">قواعد الذكاء الاصطناعي</a></li>
         @endcan

         @can('shipping_type-list')
         <li class="pc-item"><a class="pc-link" href="{{ route('shippingType.index') }}">انواع الشحن</a></li>
      @endcan

      @can('purchase_description-list')
      <li class="pc-item"><a class="pc-link" href="{{ route('purchaseDesc.index') }}">اوصاف المشتريات</a></li>
   @endcan

                   @can('payment-methods-list')
                    <li class="pc-item"><a class="pc-link" href="{{ route('payment-methods.index') }}">طرق الدفع</a></li>
                @endcan

        @can('status-list')
            <li class="pc-item"><a class="pc-link" href="{{ route('status.index') }}">حالات الرحلة</a></li>
            <li class="pc-item"><a class="pc-link" href="{{ route('whatsapp-message.index') }}">رسائل الواتساب</a></li>
        @endcan

         @can('invoice-list')
         <li class="pc-item"><a class="pc-link" href="{{ route('invoice.index') }}">الفاتورة</a></li>
      @endcan

            @can('log-list')
               <li class="pc-item"><a class="pc-link" href="{{ route('log.index') }}">الحركات</a></li>
            @endcan

            @can('user-list')
                <li class="pc-item"><a class="pc-link" href="{{ route('users.index') }}">المستخدمين</a></li>
            @endcan

            @can('role-list')
                <li class="pc-item"><a class="pc-link" href="{{ route('roles.index') }}">الصلاحيات</a></li>
            @endcan

        </ul>
    </li>
    @endcan


</ul>
