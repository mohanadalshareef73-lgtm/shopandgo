@extends('layout.master')

@section('title')
    <title>رحلات الشراء</title>
@endsection

@section('style')
    @include('admin.finance._style')
@endsection

@php
    use App\Services\TreasuryService as TS;
    $f = fn ($n) => TS::fmt($n);
@endphp

@section('content')
    <section class="pc-container">
        <div class="pc-content">
            @include('admin.finance._header', ['title' => 'رحلات الشراء'])
            @include('layout.validation-messages')

            <div class="row g-3 mb-3">
                <div class="col-6 col-xl-3"><div class="fin-kpi accent"><div class="lbl">ربح الرحلات (المعروضة)</div><div class="val">{{ $f($totals['profit_dinar']) }} <small>د.ل</small></div><div class="sub">{{ $f($totals['profit_dollar']) }} $</div></div></div>
                <div class="col-6 col-xl-3"><div class="fin-kpi"><div class="lbl">مجموع الإيصالات</div><div class="val">{{ $f($totals['sales_dinar']) }} <small>د.ل</small></div></div></div>
                <div class="col-6 col-xl-3"><div class="fin-kpi"><div class="lbl">المدفوع للمواقع</div><div class="val">{{ $f($totals['cost_dollar']) }} <small>$</small></div></div></div>
                <div class="col-6 col-xl-3">
                    <div class="fin-kpi {{ $freeCount ? 'bad' : '' }}">
                        <div class="lbl">إيصالات ما دخلتش في رحلة</div>
                        <div class="val">{{ $freeCount }}</div>
                        @can('trip-purchase-create')<a href="{{ route('finance.trips.create') }}" class="btn btn-sm btn-primary mt-1">+ رحلة شراء جديدة</a>@endcan
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="get" class="row g-2 mb-3">
                        <div class="col-md-4"><input name="q" class="form-control" placeholder="رقم الرحلة" value="{{ request('q') }}"></div>
                        <div class="col-5 col-md-3"><input type="date" name="from" class="form-control" value="{{ request('from') }}"></div>
                        <div class="col-5 col-md-3"><input type="date" name="to" class="form-control" value="{{ request('to') }}"></div>
                        <div class="col-2 col-md-2"><button class="btn btn-dark w-100">بحث</button></div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover fin-table">
                            <thead>
                                <tr>
                                    <th>الرحلة</th><th>الحالة</th><th>التاريخ</th><th class="text-end">إيصالات</th>
                                    <th class="text-end">مجموع الإيصالات</th><th class="text-end">دفعنا</th><th class="text-end">سعر الصرف</th>
                                    <th class="text-end">الربح د.ل</th><th class="text-end">الربح $</th><th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($trips as $t)
                                    @php $s = $t->summary(); @endphp
                                    <tr>
                                        <td><b>{{ $t->trip_code }}</b> @if ($t->coupon_used)<span class="badge bg-light-success text-success">كوبون</span>@endif</td>
                                        <td><span class="badge bg-light-primary text-primary">{{ $s['status'] ?: '—' }}</span></td>
                                        <td>{{ $t->purchased_at->format('Y-m-d') }}</td>
                                        <td class="fin-num">{{ $s['orders'] }}</td>
                                        <td class="fin-num">{{ $f($s['sales_dinar']) }}</td>
                                        <td class="fin-num">{{ $f($t->cost) }} {{ TS::sign($t->cost_currency) }}</td>
                                        <td class="fin-num">{{ $f($t->rate, 3) }}</td>
                                        <td class="fin-num {{ $s['profit_dinar'] < 0 ? 'text-danger' : 'text-success' }}">{{ $f($s['profit_dinar']) }}</td>
                                        <td class="fin-num {{ $s['profit_dollar'] < 0 ? 'text-danger' : 'text-success' }}">{{ $f($s['profit_dollar']) }}</td>
                                        <td><a href="{{ route('finance.trips.show', $t->id) }}" class="btn btn-sm btn-outline-primary">فتح</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10" class="text-center text-muted py-5">ما فيش رحلات شراء مسجلة.<br>@can('trip-purchase-create')<a href="{{ route('finance.trips.create') }}" class="btn btn-primary mt-2">سجل أول رحلة</a>@endcan</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $trips->links() }}
                </div>
            </div>
        </div>
    </section>
@endsection
