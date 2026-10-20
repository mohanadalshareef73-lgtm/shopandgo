@extends('layout.master')

@section('title')
    <title>لوحة المالية</title>
@endsection

@section('style')
    @include('admin.finance._style')
@endsection

@php
    use App\Services\TreasuryService as TS;
    $f = fn ($n) => TS::fmt($n);
    // نعرض الدينار دائما، والعملات الثانية فقط لو فيها حركة
    $currencies = collect(array_keys(TS::CURRENCIES))->filter(function ($c) use ($report) {
        if ($c === 'dinar') return true;
        foreach (['sales', 'shipIncome', 'shipCost', 'expenses', 'net'] as $k) {
            if (abs($report[$k][$c]) > 0.001) return true;
        }
        return false;
    })->values();
    $balTotals = TS::CURRENCIES;
    foreach ($balTotals as $c => $_) { $balTotals[$c] = collect($balances)->sum($c); }
    $partnerRows = [];
    foreach ($report['partners'] as $row) {
        $partnerRows[$row->partner_id][$row->type][$row->currency] = (float) $row->total;
    }
@endphp

@section('content')
    <section class="pc-container">
        <div class="pc-content">
            @include('admin.finance._header', ['title' => 'لوحة المالية'])
            @include('layout.validation-messages')

            {{-- الفترة --}}
            <div class="card">
                <div class="card-body py-3">
                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-auto">
                            <div class="btn-group" role="group">
                                @foreach (['today' => 'اليوم', 'week' => 'الأسبوع', 'month' => 'الشهر', 'year' => 'السنة'] as $k => $v)
                                    <a href="{{ route('finance.index', ['preset' => $k]) }}" class="btn {{ $preset === $k ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $v }}</a>
                                @endforeach
                            </div>
                        </div>
                        <input type="hidden" name="preset" value="custom">
                        <div class="col-auto"><label class="form-label mb-1">من</label><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
                        <div class="col-auto"><label class="form-label mb-1">إلى</label><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
                        <div class="col-auto"><button class="btn btn-dark">عرض</button></div>
                    </form>
                </div>
            </div>

            {{-- الأرقام الرئيسية --}}
            <div class="row g-3 mb-3">
                <div class="col-6 col-xl-3">
                    <div class="fin-kpi accent">
                        <div class="lbl">الربح الصافي للفترة</div>
                        <div class="val">{{ $f($report['net']['dinar']) }} <small>د.ل</small></div>
                        <div class="sub">بعد المصاريف والشحن</div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="fin-kpi">
                        <div class="lbl">الفلوس في الخزائن (دينار)</div>
                        <div class="val">{{ $f($balTotals['dinar']) }} <small>د.ل</small></div>
                        <div class="sub">كاش + مصرف + غيرها</div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="fin-kpi">
                        <div class="lbl">الدولار (فيزا / USDT)</div>
                        <div class="val">{{ $f($balTotals['dollar']) }} <small>$</small></div>
                        <div class="sub">آخر سعر صرف: {{ $latestRate ? $f($latestRate) : '—' }}</div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="fin-kpi bad">
                        <div class="lbl">ديون على الزبائن</div>
                        <div class="val">{{ $f($receivables['amounts']['dinar']) }} <small>د.ل</small></div>
                        <div class="sub">{{ $receivables['count'] }} طلب عليه باقي (مع الشحن)</div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                {{-- حساب الربح --}}
                <div class="col-lg-7">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="fin-card-title">حساب الربح {{ $from ? 'من ' . $from : '' }} {{ $to ? 'إلى ' . $to : '' }}</div>
                            <table class="fin-flow">
                                @if ($currencies->count() > 1)
                                    <tr><td></td>@foreach ($currencies as $c)<td class="n text-muted">{{ TS::CURRENCY_NAMES[$c] }}</td>@endforeach</tr>
                                @endif
                                <tr>
                                    <td>مجموع الإيصالات في رحلات الشراء <small class="text-muted">({{ $report['counts']['trips'] }} رحلة · {{ $report['counts']['orders'] }} إيصال)</small></td>
                                    @foreach ($currencies as $c)<td class="n">{{ $f($report['sales'][$c]) }}</td>@endforeach
                                </tr>
                                <tr>
                                    <td>− المدفوع للمواقع (دولار × سعر الصرف) <small class="text-muted">({{ $report['counts']['coupons'] }} رحلة بكوبون)</small></td>
                                    @foreach ($currencies as $c)<td class="n minus">{{ $f($report['cost'][$c]) }}</td>@endforeach
                                </tr>
                                <tr>
                                    <td><b>= ربح الرحلات</b> <small class="text-muted">({{ $f($report['goodsProfitDollar']) }} $)</small></td>
                                    @foreach ($currencies as $c)<td class="n plus">{{ $f($report['goodsProfit'][$c]) }}</td>@endforeach
                                </tr>
                                <tr>
                                    <td>+ الشحن المحسوب على الزبائن</td>
                                    @foreach ($currencies as $c)<td class="n plus">{{ $f($report['shipIncome'][$c]) }}</td>@endforeach
                                </tr>
                                <tr>
                                    <td>− المدفوع لشركة الشحن <small class="text-muted">({{ $f($report['shipWeight']) }} كيلو)</small></td>
                                    @foreach ($currencies as $c)<td class="n minus">{{ $f($report['shipCost'][$c]) }}</td>@endforeach
                                </tr>
                                <tr>
                                    <td>− مصاريف الشركة</td>
                                    @foreach ($currencies as $c)<td class="n minus">{{ $f($report['expenses'][$c]) }}</td>@endforeach
                                </tr>
                                @if ($report['paymentExpenses']['dinar'] > 0)
                                    <tr>
                                        <td>− مصروفات الدفع</td>
                                        @foreach ($currencies as $c)<td class="n minus">{{ $f($report['paymentExpenses'][$c]) }}</td>@endforeach
                                    </tr>
                                @endif
                                <tr class="total">
                                    <td>الربح الصافي</td>
                                    @foreach ($currencies as $c)<td class="n {{ $report['net'][$c] < 0 ? 'minus' : 'plus' }}">{{ $f($report['net'][$c]) }}</td>@endforeach
                                </tr>
                            </table>
                            <div class="fin-hint mt-3">
                                الربح يتحسب من رحلات الشراء: لما تجمع الإيصالات تحت رقم رحلة وتكتب سعر الصرف وكم دفعت بالدولار. الإيصالات اللي ما دخلتش في رحلة ما تدخلش في الربح.
                            </div>
                        </div>
                    </div>
                </div>

                {{-- الشركاء --}}
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="fin-card-title">الشركاء</div>
                            <table class="fin-flow">
                                @forelse ($partners as $p)
                                    @php
                                        $w = $partnerRows[$p->id]['withdrawal']['dinar'] ?? 0;
                                        $d = $partnerRows[$p->id]['distribution']['dinar'] ?? 0;
                                    @endphp
                                    <tr>
                                        <td>{{ $p->name }}<br><small class="text-muted">سحب يومي {{ $f($w) }} · توزيع {{ $f($d) }}</small></td>
                                        <td class="n minus">{{ $f($w + $d) }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="text-muted">ما فيش شركاء مسجلين. <a href="{{ route('finance.partners.index') }}">أضف الشركاء</a></td></tr>
                                @endforelse
                            </table>
                            <div class="fin-result mt-3">
                                <div class="row-line"><span>الربح الصافي من البداية</span><b>{{ $f($surplus['dinar']['net']) }} د.ل</b></div>
                                <div class="row-line"><span>− كل اللي سحبوه الشركاء</span><b class="text-danger">{{ $f($surplus['dinar']['drawn']) }} د.ل</b></div>
                                <div class="row-line big"><span>الفائض اللي ما تقسمش</span><b class="{{ $surplus['dinar']['surplus'] < 0 ? 'text-danger' : 'text-success' }}">{{ $f($surplus['dinar']['surplus']) }} د.ل</b></div>
                            </div>
                            @if ($surplus['dinar']['surplus'] < 0)
                                <div class="fin-hint mt-2 text-danger">السحوبات أكثر من الأرباح المسجلة. تأكد إن كل الإيصالات داخلة في رحلات شراء.</div>
                            @endif
                            @can('partner-create')
                                <a href="{{ route('finance.partners.index') }}" class="btn btn-outline-primary w-100 mt-3">سحب / توزيع فائض</a>
                            @endcan
                        </div>
                    </div>
                </div>

                {{-- أرصدة الخزائن --}}
                <div class="col-lg-7">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="fin-card-title">أرصدة الخزائن الآن</div>
                            <div class="table-responsive">
                                <table class="table table-sm fin-table mb-0">
                                    <thead><tr><th>الخزينة</th><th class="text-end">دينار</th><th class="text-end">دولار</th><th class="text-end">يورو</th></tr></thead>
                                    <tbody>
                                        @foreach ($balances as $pid => $b)
                                            <tr>
                                                <td>{{ $b['name'] }}</td>
                                                @foreach (['dinar', 'dollar', 'euro'] as $c)
                                                    <td class="fin-num {{ $b[$c] < 0 ? 'text-danger' : ($b[$c] == 0 ? 'text-muted' : '') }}">{{ $f($b[$c]) }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                        <tr class="fw-bold"><td>الإجمالي</td>@foreach (['dinar', 'dollar', 'euro'] as $c)<td class="fin-num">{{ $f($balTotals[$c]) }}</td>@endforeach</tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- المقبوض + الصرف --}}
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="fin-card-title">المقبوض من الزبائن في الفترة</div>
                            <table class="fin-flow">
                                @forelse ($report['collected'] as $row)
                                    <tr><td>{{ $row->method }}</td><td class="n">{{ $f($row->total) }} {{ TS::sign($row->currency) }}</td></tr>
                                @empty
                                    <tr><td class="text-muted">ما فيش مقبوضات في الفترة</td></tr>
                                @endforelse
                            </table>
                            <div class="fin-card-title mt-4">شراء الدولار في الفترة</div>
                            <table class="fin-flow">
                                <tr><td>دفعنا دينار</td><td class="n">{{ $f($report['exchange']->paid) }} د.ل</td></tr>
                                <tr><td>استلمنا دولار / USDT</td><td class="n">{{ $f($report['exchange']->got) }} $</td></tr>
                                <tr><td>متوسط سعر الصرف</td><td class="n">{{ $report['exchange']->got > 0 ? $f($report['exchange']->paid / $report['exchange']->got, 3) : '—' }}</td></tr>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- طلبات تحتاج تسجيل شراء --}}
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="fin-card-title">إيصالات ما دخلتش في رحلة شراء <span class="badge bg-light-warning text-warning ms-2">{{ $pendingPurchases->count() }}</span>
                                @can('trip-purchase-create')<a href="{{ route('finance.trips.create') }}" class="btn btn-sm btn-primary ms-auto">+ رحلة شراء</a>@endcan</div>
                            <div class="table-responsive">
                                <table class="table table-sm fin-table mb-0">
                                    <thead><tr><th>الإيصال</th><th>الزبون</th><th class="text-end">القيمة</th><th class="text-end">العربون</th></tr></thead>
                                    <tbody>
                                        @forelse ($pendingPurchases as $o)
                                            <tr>
                                                <td>{{ $o->code }}</td>
                                                <td>{{ optional($o->customer)->name }}</td>
                                                <td class="fin-num">{{ $f($o->total_price) }}</td>
                                                <td class="fin-num">{{ $f($o->paid_price) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-muted">كل الإيصالات داخلة في رحلات 👍</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- وصلت وعليها فلوس --}}
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="fin-card-title">بضاعة وصلت وعليها باقي <span class="badge bg-light-danger text-danger ms-2">{{ $arrivedUnpaid->count() }}</span></div>
                            <div class="table-responsive">
                                <table class="table table-sm fin-table mb-0">
                                    <thead><tr><th>الطلب</th><th>الزبون</th><th>الرف</th><th class="text-end">الباقي مع الشحن</th></tr></thead>
                                    <tbody>
                                        @forelse ($arrivedUnpaid as $o)
                                            <tr>
                                                <td>{{ $o->code }}</td>
                                                <td>{{ optional($o->customer)->name }}</td>
                                                <td>{{ $o->storage_num ?: '—' }}</td>
                                                <td class="fin-num text-danger">{{ $f($o->remaining()) }} {{ $o->currency_sign }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-muted">ما فيش</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- المصاريف حسب النوع --}}
                @if ($report['expensesByDesc']->count())
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="fin-card-title">مصاريف الشركة حسب النوع</div>
                                <div class="row g-2">
                                    @foreach ($report['expensesByDesc'] as $e)
                                        <div class="col-6 col-md-3">
                                            <div class="fin-kpi"><div class="lbl">{{ $e->name }}</div><div class="val" style="font-size:18px">{{ $f($e->total) }} <small>{{ TS::sign($e->currency) }}</small></div></div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
