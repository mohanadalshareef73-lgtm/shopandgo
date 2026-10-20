@extends('layout.master')

@section('title')<title>الرئيسية — SHOP AND GO</title>@endsection
@section('style')
    <link rel="stylesheet" href="{{ asset('assets/css/owner-dashboard.css') }}">
@endsection

@section('content')
<div class="pc-container owner-dashboard">
    <main class="pc-content db-content" dir="rtl">
        @include('layout.validation-messages')
        <header class="db-heading">
            <div>
                <div class="db-eyebrow">SHOP AND GO <span>/</span> الرئيسية</div>
                <h1>نظرة عامة على أعمالك</h1>
            </div>
            <div class="db-actions">
                @can('cart-create')<a class="db-button db-button-secondary" href="{{ route('carts.create') }}"><i class="ti ti-shopping-cart" aria-hidden="true"></i> فحص سلة</a>@endcan
                @can('order-create')<a class="db-button db-button-primary" href="{{ route('order.create') }}"><i class="ti ti-plus" aria-hidden="true"></i> إضافة طلب</a>@endcan
            </div>
        </header>

        @if($finance)
            <section class="db-panel db-position" aria-labelledby="db-position-title">
                <div class="db-section-heading">
                    <h2 id="db-position-title">الوضع المالي الحالي</h2>
                    <span class="db-scope">حالياً · لا يتأثر بالفترة</span>
                </div>
                <div class="db-currency-grid">
                    @foreach($currencies as $currency => $meta)
                        <article class="db-currency-position">
                            <div class="db-currency-heading"><strong>{{ $meta['name'] }}</strong><bdi>{{ $meta['code'] }}</bdi></div>
                            <div class="db-cash-label">رصيد الخزينة</div>
                            <div class="db-cash-value {{ $finance[$currency]['cash'] < 0 ? 'db-negative' : '' }}">
                                <bdi>{{ number_format($finance[$currency]['cash'], $meta['decimals']) }}</bdi>
                                @can('treasury-list')<a href="{{ route('safe.history', ['currency' => $currency]) }}" aria-label="حركة الخزينة — {{ $meta['name'] }}"><i class="ti ti-arrow-up-left" aria-hidden="true"></i></a>@endcan
                            </div>
                            <div class="db-debt-line"><span>مستحقات الزبائن</span><bdi>{{ number_format($finance[$currency]['debt'], $meta['decimals']) }}</bdi></div>
                        </article>
                    @endforeach
                </div>
                <div class="db-position-note"><span>المستحقات: الطلبات غير المدفوعة والمدفوعة جزئياً.</span>@can('order-list')<a class="db-text-link" href="{{ route('order.unpaid.index') }}">متابعة التحصيل <i class="ti ti-arrow-up-left" aria-hidden="true"></i></a>@endcan</div>
            </section>
        @endif

        <section class="db-panel db-period" aria-labelledby="db-period-title">
            <div class="db-section-heading">
                <div class="db-period-title"><h2 id="db-period-title">نشاط الفترة</h2><span class="db-scope"><bdi>{{ $start->format('d/m') }} — {{ $updatedAt->format('d/m/Y') }}</bdi></span></div>
                <nav class="db-period-options" aria-label="الفترة الزمنية">
                    @foreach(['today' => 'اليوم', 'week' => 'آخر ٧ أيام', 'month' => 'هذا الشهر'] as $value => $label)
                        <a href="{{ route('admin.home', ['period' => $value, 'queue' => $queue]) }}" class="{{ $period === $value ? 'is-active' : '' }}" @if($period === $value) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
            <div class="db-period-body {{ $finance ? '' : 'db-period-no-finance' }}">
                <div class="db-period-counts" aria-label="نشاط الأعمال — {{ $periodLabel }}">
                    <div><span>طلبات جديدة</span><strong><bdi>{{ number_format($newOrders) }}</bdi></strong></div>
                    <div><span>تم تسليمها</span><strong><bdi>{{ number_format($deliveries) }}</bdi></strong></div>
                    <div><span>زبائن جدد</span><strong><bdi>{{ number_format($newCustomers) }}</bdi></strong></div>
                </div>
                @if($finance)
                    <div class="db-period-finances" aria-label="النتائج المالية — {{ $periodLabel }}">
                        <div class="db-period-money-grid">
                            <div class="db-money-labels" aria-hidden="true">
                                <span class="db-money-heading">{{ $periodLabel }}</span>
                                <span>تحصيلات الطلبات</span>
                                <span>قيمة الطلبات الجديدة</span>
                                @if($finance['dinar']['recordedProfit'] !== null)<span>الأرباح المسجلة</span>@endif
                            </div>
                            @foreach($currencies as $currency => $meta)
                                <div class="db-money-currency">
                                    <bdi class="db-money-heading">{{ $meta['code'] }}</bdi>
                                    <div><span class="db-mobile-label">تحصيلات الطلبات</span><bdi>{{ number_format($finance[$currency]['collections'], $meta['decimals']) }}</bdi></div>
                                    <div><span class="db-mobile-label">قيمة الطلبات الجديدة</span><bdi>{{ number_format($finance[$currency]['orderValue'], $meta['decimals']) }}</bdi></div>
                                    @if($finance[$currency]['recordedProfit'] !== null)<div><span class="db-mobile-label">الأرباح المسجلة</span><bdi>{{ number_format($finance[$currency]['recordedProfit'], $meta['decimals']) }}</bdi></div>@endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            @if($finance)
                <div class="db-period-note"><span>القيمة تشمل الشحن وتستثني الطلبات الملغاة؛ ليست ربحاً.</span>@if($finance['dinar']['recordedProfit'] !== null)<span>الأرباح من السجل، وليست صافي ربح محسوباً.</span><a class="db-text-link" href="{{ route('profits.search', ['fromDate' => $start->toDateString(), 'toDate' => $updatedAt->toDateString()]) }}">سجل الأرباح <i class="ti ti-arrow-up-left" aria-hidden="true"></i></a>@endif</div>
            @endif
        </section>

        @if(count($queues))
            <section class="db-panel db-queue" aria-labelledby="db-queue-title">
                <div class="db-section-heading">
                    <h2 id="db-queue-title">قائمة المتابعة</h2>
                    @if($activeOrders !== null)<span class="db-workload">قيد التنفيذ حالياً <strong><bdi>{{ number_format($activeOrders) }}</bdi></strong></span>@endif
                </div>
                <nav class="db-queue-tiles" aria-label="قوائم المتابعة الحالية">
                    @foreach($queues as $key => $item)
                        <a class="db-queue-tile {{ $queue === $key ? 'is-active' : '' }}" href="{{ route('admin.home', ['period' => $period, 'queue' => $key]) }}#db-queue-title" @if($queue === $key) aria-current="page" @endif>
                            <i class="ti {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span><strong><bdi>{{ number_format($item['count']) }}</bdi></strong>
                        </a>
                    @endforeach
                </nav>
                <div class="db-queue-caption"><span>{{ $queue === 'ready' ? 'الأقدم وصولاً أولاً · حتى ٦ طلبات' : ($queue === 'late_trips' ? 'الأقدم تأخراً أولاً · حتى ٦ رحلات' : 'الأقدم تحديثاً أولاً · حتى ٦ طلبات') }}</span><a class="db-text-link" href="{{ route($queues[$queue]['route']) }}">عرض الكل <i class="ti ti-arrow-up-left" aria-hidden="true"></i></a></div>
                @if($queue === 'late_trips' && $queueTrips->isNotEmpty())
                    <div class="db-table-scroll">
                        <table class="db-table"><thead><tr><th scope="col">الرحلة</th><th scope="col">بلد المصدر</th><th scope="col">مدة التأخير</th><th scope="col"><span class="visually-hidden">عرض</span></th></tr></thead><tbody>
                            @foreach($queueTrips as $trip)
                                <tr><td class="db-row-code"><a href="{{ route('trip.show', $trip->id) }}"><bdi>{{ $trip->code }}</bdi></a></td><td class="db-row-name">{{ $trip->country }}</td><td class="db-row-context"><span class="db-context-value">{{ \Carbon\Carbon::parse($trip->arrive_date)->startOfDay()->diffInDays($updatedAt->copy()->startOfDay()) }} يوم</span><small>موعد الوصول <bdi>{{ \Carbon\Carbon::parse($trip->arrive_date)->format('d/m/Y') }}</bdi></small></td><td class="db-row-action"><a href="{{ route('trip.show', $trip->id) }}" aria-label="عرض الرحلة {{ $trip->code }}"><i class="ti ti-arrow-up-left" aria-hidden="true"></i></a></td></tr>
                            @endforeach
                        </tbody></table>
                    </div>
                @elseif($queue !== 'late_trips' && $queueOrders->isNotEmpty())
                    <div class="db-table-scroll">
                        <table class="db-table"><thead><tr><th scope="col">الطلب</th><th scope="col">الزبون</th><th scope="col">{{ ['ready' => 'منذ الوصول', 'unpaid' => 'المبلغ المستحق', 'no_trip' => 'بدون رحلة منذ آخر تحديث'][$queue] }}</th><th scope="col"><span class="visually-hidden">عرض</span></th></tr></thead><tbody>
                            @foreach($queueOrders as $order)
                                <tr>
                                    <td class="db-row-code"><a href="{{ route('order.show', $order->id) }}"><bdi>{{ $order->code }}</bdi></a></td>
                                    <td class="db-row-name">{{ $order->customer?->name ?? 'زبون غير متاح' }}</td>
                                    <td class="db-row-context">
                                        @if($queue === 'unpaid')
                                            @php($orderCurrency = $currencies[$order->currency] ?? ['code' => $order->currency, 'decimals' => 2])
                                            <span class="db-context-value"><bdi>{{ number_format(($order->total_price + ($order->ship_price ?? 0)) - $order->paid_price, $orderCurrency['decimals']) }} {{ $orderCurrency['code'] }}</bdi></span>
                                        @elseif($queue === 'ready')
                                            @if($order->arrived_at)<span class="db-context-value">{{ \Carbon\Carbon::parse($order->arrived_at)->startOfDay()->diffInDays($updatedAt->copy()->startOfDay()) }} يوم</span><small>منذ الوصول</small>@else<span class="db-context-value db-context-unknown">تاريخ الوصول غير مسجل</span>@endif
                                        @else
                                            @php($hours = max(0, $order->updated_at->diffInHours($updatedAt, false)))
                                            <span class="db-context-value">{{ $hours >= 48 ? floor($hours / 24).' يوم' : $hours.' ساعة' }}</span><small>منذ آخر تحديث</small>
                                        @endif
                                    </td>
                                    <td class="db-row-action"><a href="{{ route('order.show', $order->id) }}" aria-label="عرض الطلب {{ $order->code }}"><i class="ti ti-arrow-up-left" aria-hidden="true"></i></a></td>
                                </tr>
                            @endforeach
                        </tbody></table>
                    </div>
                @else
                    <div class="db-empty"><i class="ti ti-circle-check" aria-hidden="true"></i><span>{{ $queue === 'late_trips' ? 'لا توجد رحلات متأخرة' : 'لا توجد طلبات في هذه القائمة' }}</span></div>
                @endif
                <p class="db-queue-note">القوائم تعرض الوضع الحالي، وقد يظهر الطلب في أكثر من قائمة.</p>
            </section>
        @endif

        @if($period !== 'today' && $days->sum('count') > 0)
            <section class="db-panel db-trend" aria-labelledby="db-trend-title">
                <div class="db-section-heading"><h2 id="db-trend-title">توزيع الطلبات الجديدة</h2><span class="db-scope">{{ $periodLabel }} · عدد الطلبات يومياً</span></div>
                <div class="db-chart" role="img" aria-label="الطلبات الجديدة — {{ $periodLabel }}: {{ $days->map(fn ($day) => $day['label'].': '.$day['count'])->implode('، ') }}">
                    @foreach($days as $day)
                        <div class="db-chart-column" title="{{ $day['label'] }}: {{ $day['count'] }} طلب">
                            <div class="db-chart-track"><div class="db-chart-bar {{ $loop->last ? 'is-today' : '' }}" style="--bar-height: {{ $day['count'] / $chartMax * 100 }}%"></div></div>
                            <bdi>{{ $loop->index % $chartTickInterval === 0 || $loop->last ? $day['label'] : '' }}</bdi>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        <footer class="db-footnote"><span>آخر تحديث للصفحة <bdi>{{ $updatedAt->format('H:i') }}</bdi></span><span>كل عملة مستقلة؛ دون تحويل أو جمع بين العملات.</span></footer>
    </main>
</div>
@endsection
