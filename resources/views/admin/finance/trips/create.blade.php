@extends('layout.master')

@section('title')
    <title>رحلة شراء جديدة</title>
@endsection

@section('style')
    @include('admin.finance._style')
    <style>
        .pick-row { cursor: pointer; }
        .pick-row.on { background: #eaf6f1 !important; }
        .sticky-sum { position: sticky; top: 80px; }
    </style>
@endsection

@php
    use App\Services\TreasuryService as TS;
    $f = fn ($n) => TS::fmt($n);
    $oldIds = collect(old('order_ids', []))->map(fn ($v) => (int) $v)->all();
@endphp

@section('content')
    <section class="pc-container">
        <div class="pc-content">
            @include('admin.finance._header', ['title' => 'رحلة شراء جديدة'])
            @include('layout.validation-messages')

            <form method="post" action="{{ route('finance.trips.store') }}" id="tripForm">
                @csrf
                <div class="row g-3">
                    {{-- الإيصالات --}}
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-body">
                                <div class="fin-card-title"><span class="fin-step">1</span>اختار الإيصالات اللي بتشريهم مع بعض</div>
                                <input type="search" id="filter" class="form-control mb-2" placeholder="بحث باسم الزبون أو رقم الإيصال...">
                                <div class="table-responsive" style="max-height: 560px; overflow:auto">
                                    <table class="table table-sm table-hover fin-table mb-0">
                                        <thead class="sticky-top bg-white"><tr><th style="width:36px"><input type="checkbox" id="all" class="form-check-input"></th><th>الإيصال</th><th>الزبون</th><th class="text-end">القطع</th><th class="text-end">القيمة</th><th class="text-end">العربون</th><th>التاريخ</th></tr></thead>
                                        <tbody>
                                            @forelse ($orders as $o)
                                                <tr class="pick-row" data-search="{{ $o->code }} {{ optional($o->customer)->name }} {{ optional($o->customer)->code }} {{ $o->trip_details }}" data-trip="{{ $o->trip_details }}">
                                                    <td><input type="checkbox" class="form-check-input pick" name="order_ids[]" value="{{ $o->id }}" data-total="{{ $o->total_price }}" data-paid="{{ $o->paid_price }}" data-qty="{{ $o->qty }}" data-cur="{{ $o->currency ?: 'dinar' }}" @checked(in_array($o->id, $oldIds))></td>
                                                    <td>{{ $o->code }}@if ($o->trip_details)<br><small class="text-muted">رحلة {{ $o->trip_details }}</small>@endif</td>
                                                    <td>{{ optional($o->customer)->name }}</td>
                                                    <td class="fin-num">{{ $o->qty }}</td>
                                                    <td class="fin-num">{{ $f($o->total_price) }} {{ $o->currency_sign }}</td>
                                                    <td class="fin-num text-success">{{ $f($o->paid_price) }}</td>
                                                    <td><small>{{ $o->created_at->format('Y-m-d') }}</small></td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="7" class="text-center text-muted py-4">ما فيش إيصالات حرة. كل الإيصالات داخلة في رحلات.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- بيانات الرحلة + الربح --}}
                    <div class="col-lg-5">
                        <div class="sticky-sum">
                            <div class="card">
                                <div class="card-body">
                                    <div class="fin-card-title"><span class="fin-step">2</span>بيانات الرحلة</div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label">رقم الرحلة</label>
                                            <input name="trip_code" id="tripCode" class="form-control fin-big-input" value="{{ old('trip_code') }}" placeholder="مثلا 245" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">حالة الرحلة</label>
                                            <select name="trip_status" class="form-select" style="height:52px">
                                                @foreach ($statuses as $st)
                                                    <option value="{{ $st->name }}" @selected(old('trip_status') == $st->name)>{{ $st->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">كم دفعت (دولار)</label>
                                            <input type="number" step="any" min="0" name="cost" id="cost" class="form-control fin-big-input" value="{{ old('cost') }}" placeholder="0" required>
                                            <input type="hidden" name="cost_currency" value="dollar">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">سعر الصرف للرحلة</label>
                                            <input type="number" step="any" min="0" name="rate" id="rate" class="form-control fin-big-input" value="{{ old('rate', $latestRate) }}" placeholder="مثلا 7.2" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">دفعت من</label>
                                            <select name="payment_id" class="form-select">
                                                @foreach ($payments as $p)
                                                    <option value="{{ $p->id }}" @selected(old('payment_id', $visaId) == $p->id)>{{ $p->name }} ({{ $f($dollarBalances[$p->id]) }} $)</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">تاريخ الشراء</label>
                                            <input type="date" name="purchased_at" class="form-control" value="{{ old('purchased_at', now()->toDateString()) }}">
                                        </div>
                                        <div class="col-12">
                                            <div class="form-check mt-1">
                                                <input class="form-check-input" type="checkbox" name="coupon_used" value="1" id="coupon" @checked(old('coupon_used'))>
                                                <label class="form-check-label" for="coupon">استخدمت كوبون في الرحلة هذي</label>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <input name="note" class="form-control" value="{{ old('note') }}" placeholder="ملاحظة (اختياري)">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <div class="fin-card-title"><span class="fin-step">3</span>ربح الرحلة</div>
                                    <div class="fin-result">
                                        <div class="row-line"><span>عدد الإيصالات / القطع</span><b><span id="sCount">0</span> / <span id="sQty">0</span></b></div>
                                        <div class="row-line"><span>مجموع الإيصالات</span><b><span id="sSales">0</span> د.ل</b></div>
                                        <div class="row-line"><span>العربون المقبوض</span><b class="text-success"><span id="sPaid">0</span> د.ل</b></div>
                                        <div class="row-line"><span>المدفوع بالدينار (دولار × سعر)</span><b class="text-danger"><span id="sCost">0</span> د.ل</b></div>
                                        <div class="row-line big"><span>الربح</span><b><span id="sProfit">0</span> د.ل</b></div>
                                        <div class="row-line"><span>الربح بالدولار</span><b><span id="sProfitUsd">0</span> $</b></div>
                                        <div class="row-line"><span>نسبة الربح</span><b><span id="sMargin">0</span>%</b></div>
                                    </div>
                                    <button class="btn btn-primary btn-lg w-100 mt-3">حفظ الرحلة وخصم المبلغ من الخزينة</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection

@section('script')
    <script>
        (function () {
            var picks = Array.prototype.slice.call(document.querySelectorAll('.pick'));
            var cost = document.getElementById('cost'), rate = document.getElementById('rate');
            function fmt(n) { return (Math.round(n * 100) / 100).toLocaleString('en-US'); }
            function calc() {
                var r = parseFloat(rate.value) || 0, c = parseFloat(cost.value) || 0;
                var sales = 0, paid = 0, qty = 0, n = 0;
                picks.forEach(function (p) {
                    var row = p.closest('tr');
                    row.classList.toggle('on', p.checked);
                    if (!p.checked) return;
                    var k = p.dataset.cur === 'dinar' ? 1 : r;
                    sales += parseFloat(p.dataset.total) * k; paid += parseFloat(p.dataset.paid) * k;
                    qty += parseInt(p.dataset.qty || 0, 10); n++;
                });
                var costD = c * r, profit = sales - costD;
                document.getElementById('sCount').textContent = n;
                document.getElementById('sQty').textContent = qty;
                document.getElementById('sSales').textContent = fmt(sales);
                document.getElementById('sPaid').textContent = fmt(paid);
                document.getElementById('sCost').textContent = fmt(costD);
                var pe = document.getElementById('sProfit');
                pe.textContent = fmt(profit); pe.parentElement.className = profit < 0 ? 'text-danger' : 'text-success';
                document.getElementById('sProfitUsd').textContent = r > 0 ? fmt(profit / r) : '0';
                document.getElementById('sMargin').textContent = sales > 0 ? (profit / sales * 100).toFixed(1) : '0';
            }
            picks.forEach(function (p) {
                p.addEventListener('change', calc);
                p.closest('tr').addEventListener('click', function (e) { if (e.target !== p) { p.checked = !p.checked; calc(); } });
            });
            cost.addEventListener('input', calc); rate.addEventListener('input', calc);
            document.getElementById('all').addEventListener('change', function () {
                var on = this.checked;
                picks.forEach(function (p) { if (p.closest('tr').style.display !== 'none') p.checked = on; }); calc();
            });
            document.getElementById('filter').addEventListener('input', function () {
                var q = this.value.trim().toLowerCase();
                picks.forEach(function (p) { var tr = p.closest('tr'); tr.style.display = !q || tr.dataset.search.toLowerCase().indexOf(q) > -1 ? '' : 'none'; });
            });
            // لو كتبت رقم رحلة موجود على إيصالات، نختارهم تلقائيا
            document.getElementById('tripCode').addEventListener('change', function () {
                var code = this.value.trim();
                if (!code) return;
                picks.forEach(function (p) { if (p.closest('tr').dataset.trip === code) p.checked = true; }); calc();
            });
            calc();
        })();
    </script>
@endsection
