@extends('layout.master')

@section('style')
<link rel="stylesheet" href="{{ asset('assets/css/plugins/dataTables.bootstrap5.min.css') }}">

<style>
    body { direction: rtl; }

    .table td { vertical-align: middle; font-size: 13px; }

    @media print {

        body * { visibility: hidden; }

        #printArea, #printArea * { visibility: visible; }

        #printArea {
            position: absolute;
            right: 0;
            top: 0;
            width: 100%;
            padding: 30px;
            background: white;
        }

        .no-print { display: none !important; }

        .print-header {
            border-top: 10px solid #1e3a5f;
            padding-top: 20px;
            margin-bottom: 20px;
        }

        .print-header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .print-header img { width: 120px; }

        .print-title {
            text-align: center;
            flex: 1;
        }

        .print-title h2 {
            margin: 0;
            font-weight: bold;
            color: #1e3a5f;
        }

        .print-sub {
            margin-top: 8px;
            font-size: 14px;
        }

        .print-section-title {
            font-weight: bold;
            margin: 25px 0 10px;
            font-size: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        th, td {
            border: 1px solid #999;
            padding: 6px;
            text-align: center;
        }

        th {
            background-color: #cfe2f3 !important;
            color: #000;
        }

        .print-footer {
            margin-top: 50px;
            text-align: center;
            border-top: 1px solid #999;
            padding-top: 10px;
            font-size: 13px;
        }
    }
</style>
@endsection

@section('content')

<div class="pc-container">
    <div class="pc-content">

        {{-- ================= SEARCH FORM ================= --}}
        <div class="card mb-4 no-print">
            <div class="card-body">
                <h5 class="mb-3">تقرير المصروفات</h5>

                <form action="{{ route('purchase.report') }}" method="get">
                    <div class="row g-3 align-items-end">

                        <div class="col-md-3">
                            <label class="form-label fw-bold">التاريخ *</label>
                            <input type="date" name="fromDate" value="{{ request('fromDate') }}" class="form-control" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">إلى تاريخ (إختياري)</label>
                            <input type="date" name="toDate" value="{{ request('toDate') }}" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">نوع المصروفات</label>
                            <select name="type" required class="form-control">
                                <option value="all" {{ request('type') == 'all' ? 'selected' : '' }}>الكل</option>
                                <option value="ShippingCharges" {{ request('type') == 'ShippingCharges' ? 'selected' : '' }}>مصروفات الشحن</option>
                                <option value="Purchase" {{ request('type') == 'Purchase' ? 'selected' : '' }}>المشتريات</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <button class="btn btn-primary w-100">بحث</button>
                        </div>

                    </div>
                </form>
            </div>
        </div>

        {{-- ================= ACTION BUTTONS ================= --}}
        <div class="mb-3 d-flex gap-2 no-print">
            <button onclick="window.print()" class="btn btn-secondary">طباعة</button>
        </div>


        {{-- ================= PRINT AREA ================= --}}
        <div id="printArea">

            @if (isset($fromDate))
                <div class="print-header">
                    <div class="print-header-inner">
                        <img src="{{ asset('assets/images/logo.png') }}" width="200">
                        <div class="print-title">
                            <h2>{{ env('COMPANY_NAME') }}</h2>
                            <div class="print-sub">
                                تقرير المصروفات من {{ $fromDate }}
                                @if ($toDate)
                                    إلى {{ $toDate }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif


            {{-- ================= PURCHASES ================= --}}
            @if(isset($purchases) && $purchases->count())
                <div class="print-section-title">المشتريات</div>

                <table class="table table-bordered text-center">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الوصف</th>
                            <th>القيمة</th>
                            <th>طريقة الدفع</th>
                            <th>العملة</th>
                            <th>التاريخ</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchases as $i => $purchase)
                            <tr>
                                <td>{{ $i + 1 }}</td>

                                <td>{{ $purchase->desc->name ?? '-' }}</td>

                                <td style="color: green;">
                                    {{ $purchase->cost }}
                                </td>

                                <td>
                                    {{ $purchase->paymentMethod->name ?? '-' }}
                                </td>

                                <td>
                                    {{ $purchase->currency ?? 'dinar' }}
                                </td>

                                <td>{{ $purchase->created_at->format('Y-m-d') }}</td>

                                <td>{{ $purchase->note ?? '-' }}</td>
                            </tr>
                        @endforeach

                        <tr>
                            <td colspan="2"><b>الاجمالي</b></td>
                            <td colspan="5" style="color: green;">
                                {{ $purchases->sum('cost') }}
                            </td>
                        </tr>

                    </tbody>
                </table>
            @endif


            {{-- ================= SHIPPING CHARGES ================= --}}
            @if(isset($charges) && $charges->count())
                <div class="print-section-title">مصاريف الشحن</div>

                <table class="table table-bordered text-center">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نوع الشحن</th>
                            <th>الوزن</th>
                            <th>القيمة</th>
                            <th>طريقة الدفع</th>
                            <th>العملة</th>
                            <th>التاريخ</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($charges as $i => $charge)
                            <tr>
                                <td>{{ $i + 1 }}</td>

                                <td>{{ $charge->type->name ?? '-' }}</td>

                                <td>{{ $charge->weight ?? '-' }}</td>

                                <td style="color: green;">
                                    {{ $charge->cost }}
                                </td>

                                <td>
                                    {{ $charge->paymentMethod->name ?? '-' }}
                                </td>

                                <td>
                                    {{ $charge->currency ?? 'dinar' }}
                                </td>

                                <td>{{ $charge->created_at->format('Y-m-d') }}</td>

                                <td>{{ $charge->note ?? '-' }}</td>
                            </tr>
                        @endforeach

                        <tr>
                            <td colspan="3"><b>الاجمالي</b></td>
                            <td colspan="5" style="color: green;">
                                {{ $charges->sum('cost') }}
                            </td>
                        </tr>

                    </tbody>
                </table>
            @endif


            {{-- ================= SUMMARY PER PAYMENT ================= --}}
            @if(isset($totals) && count($totals))
                <div class="print-section-title">ملخص المصروفات حسب طريقة الدفع</div>

                <table class="table table-bordered text-center">
                    <thead>
                        <tr>
                            <th>طريقة الدفع</th>
                            <th>دينار</th>
                            <th>دولار</th>
                            <th>يورو</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($totals as $method)
                            @php
                                $hasValue =
                                    $method['currencies']['dinar'] > 0 ||
                                    $method['currencies']['dollar'] > 0 ||
                                    $method['currencies']['euro'] > 0;
                            @endphp

                            @if($hasValue)
                                <tr>
                                    <td>{{ $method['name'] }}</td>

                                    <td style="color: green;">
                                        {{ number_format($method['currencies']['dinar'], 2) }}
                                    </td>

                                    <td style="color: green;">
                                        {{ number_format($method['currencies']['dollar'], 2) }}
                                    </td>

                                    <td style="color: green;">
                                        {{ number_format($method['currencies']['euro'], 2) }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif


            {{-- ================= TOTAL PER CURRENCY ================= --}}
            @if(isset($currencyTotals))
                <div class="print-section-title">الإجمالي حسب العملة</div>

                <table class="table table-bordered text-center">
                    <thead>
                        <tr>
                            <th>دينار</th>
                            <th>دولار</th>
                            <th>يورو</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="color: green;">
                                {{ number_format($currencyTotals['dinar'], 2) }}
                            </td>

                            <td style="color: green;">
                                {{ number_format($currencyTotals['dollar'], 2) }}
                            </td>

                            <td style="color: green;">
                                {{ number_format($currencyTotals['euro'], 2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            @endif


            <div class="print-footer">
                {{ env('COMPANY_NAME') }}
            </div>

        </div>

    </div>
</div>

@endsection


@section('script')
<script>
    document.addEventListener("DOMContentLoaded", function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (el) {
            return new bootstrap.Tooltip(el);
        });
    });
</script>
@endsection
