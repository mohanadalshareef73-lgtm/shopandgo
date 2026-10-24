@extends('layout.master')

@section('style')
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/dataTables.bootstrap5.min.css') }}">

    <style>
        body {
            direction: rtl;
        }

        .table td {
            vertical-align: middle;
            font-size: 13px;
        }

        .print-section-title {
            font-weight: bold;
            margin: 25px 0 10px;
            font-size: 16px;
        }

        /* ======================= */
        /*          PRINT          */
        /* ======================= */
        @media print {
            body * {
                visibility: hidden;
            }

            #printArea,
            #printArea * {
                visibility: visible;
            }

            #printArea {
                position: absolute;
                right: 0;
                top: 0;
                width: 100%;
                padding: 30px;
                background: white;
            }

            .no-print {
                display: none !important;
            }

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

            .print-header img {
                width: 120px;
            }

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

            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 12px;
            }

            th,
            td {
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
                    <h5 class="mb-3">التقرير العام</h5>

                    <form action="{{ route('reports.index') }}" method="get">
                        <div class="row g-3 align-items-end">

                            <div class="col-md-3">
                                <label class="form-label fw-bold">التاريخ *</label>
                                <input type="date" name="fromDate" value="{{ request('fromDate') }}" class="form-control"
                                    required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold">إلى تاريخ (إختياري)</label>
                                <input type="date" name="toDate" value="{{ request('toDate') }}" class="form-control">
                            </div>

                            <div class="col-md-2">
                                <button class="btn btn-primary w-100">
                                    بحث
                                </button>
                            </div>

                        </div>
                    </form>
                </div>
            </div>

            {{-- ================= ACTION BUTTONS ================= --}}
            <div class="mb-3 d-flex gap-2 no-print">
                <button onclick="window.print()" class="btn btn-secondary">
                    طباعة
                </button>
            </div>

            {{-- ================= PRINT AREA ================= --}}
            <div id="printArea">

                @if (isset($fromDate))
                    <div class="print-header">
                        <div class="print-header-inner">
                            <img src="{{ asset('assets/images/logo.png') }}" width="200">
                            <div class="print-title">
                                <h2>{{ config('system.company_name') }}</h2>
                                <div class="print-sub">
                                    تقرير عام يوم {{ $fromDate }}
                                    @if (isset($toDate))
                                        إلى {{ $toDate }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif


                {{-- ================= OUTGOING ================= --}}
                @if (isset($ordersget) && $ordersget->count())
                    <div class="print-section-title d-flex justify-content-between align-items-center">
                        <span>الطلبيات المنشاة</span>

                        <button class="btn btn-sm btn-outline-primary no-print"
                            onclick="toggleTable('ordersgetTable')">
                            عرض التفاصيل
                        </button>
                    </div>

                    {{-- SUMMARY TABLE --}}
                    <table class="table table-bordered text-center">
                        <thead>
                            <tr>
                                <th>عدد الطلبيات</th>
                                <th>إجمالي الشحن</th>
                                <th>إجمالي المتبقي</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ $ordersget->count() }}</td>
                                <td style="color: green;">
                                    {{ $ordersget->sum('ship_price') }}
                                </td>
                                <td style="color: green;">
                                    {{ $ordersget->sum(fn($o)=> ($o->ship_price ?? 0) + $o->total_price - $o->paid_price) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    {{-- DETAILS TABLE (HIDDEN) --}}
                    <div id="ordersgetTable" style="display:none;">
                        <table class="table table-bordered text-center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>كود الزبون</th>
                                    <th>العنوان</th>
                                    <th>المتبقي</th>
                                    <th>قيمة الشحن</th>
                                    <th>عدد الطرود والقطع</th>
                                    <th>الرحلة</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ordersget as $i => $order)
                                    <tr style="color:#282E4D;">
                                        <td>{{ $i + 1 }}</td>

                                        <td>
                                            {{ $order->customer->name }}
                                            <br>
                                            {{ $order->customer->code }}
                                        </td>

                                        <td>{{ $order->customer->addess ?? '-' }}</td>

                                        <td style="color: green;">
                                            {{ ($order->ship_price ?? 0) + $order->total_price - $order->paid_price }}
                                            {{ $order->currency_sign }}
                                        </td>

                                        <td style="color: green;">
                                            {{ $order->ship_price ?? '-' }}
                                            {{ $order->currency_sign }}
                                        </td>

                                        <td>
                                            {{ $order->bags_num ?? '-' }}
                                            <br>
                                            @if($order->bags_qty)
                                                الكمية في الطرود <span style="color: blue;">{{ $order->bags_qty }}</span>
                                            @endif
                                        </td>

                                        <td>
                                            {{ $order->trip->code ?? '-' }}
                                            @if(isset($order->trip->country))
                                                - {{ $order->trip->country }}
                                            @endif
                                        </td>

                                        <td>{{ $order->created_at->format('Y-m-d') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif


                {{-- ================= INCOME ================= --}}
                @if (isset($income) && $income->count())
                    <div class="print-section-title d-flex justify-content-between align-items-center">
                        <span>الايرادات</span>

                        <button class="btn btn-sm btn-outline-primary no-print"
                            onclick="toggleTable('incomeTable')">
                            عرض التفاصيل
                        </button>
                    </div>

                    {{-- SUMMARY --}}
                    <table class="table table-bordered text-center">
                        <thead>
                            <tr>
                                <th>عدد العمليات</th>
                                <th>الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ $income->count() }}</td>
                                <td style="color: green;">
                                    {{ $income->sum('paid_price') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    {{-- DETAILS --}}
                    <div id="incomeTable" style="display:none;">
                        <table class="table table-bordered text-center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>اسم الزبون</th>
                                    <th>القيمة</th>
                                    <th>الطلبية</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($income as $i => $paid)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>

                                        <td>
                                            {{ $paid->customer->name }}
                                            <br>
                                            <a style="color: green" href="https://wa.me/{{ $paid->customer->phone_1 }}"
                                                target="_blank">{{ $paid->customer->code }}</a>
                                        </td>

                                        <td style="color: green;">
                                            {{ $paid->paid_price }}
                                            {{ $paid->order->currency_sign }}
                                            <br>
                                            <span class="text-secondary">{{ $paid->paymentMethod->name }}</span>
                                        </td>

                                        <td>{{ $paid->order->code }}</td>
                                        <td>{{ $paid->created_at->format('Y-m-d') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif


                {{-- ================= INCOME SUMMARY ================= --}}
                @if(isset($totals) && count($totals))
                    <div class="print-section-title">ملخص الإيرادات حسب طريقة الدفع</div>

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
                                <tr>
                                    <td>{{ $method['name'] }}</td>

                                    <td style="color: green;">
                                        {{ number_format($method['currencies']['dinar'], 2) }} د.ل
                                    </td>

                                    <td style="color: green;">
                                        {{ number_format($method['currencies']['dollar'], 2) }} $
                                    </td>

                                    <td style="color: green;">
                                        {{ number_format($method['currencies']['euro'], 2) }} €
                                    </td>
                                </tr>
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
                                    {{ number_format($currencyTotals['dinar'], 2) }} د.ل
                                </td>
                                <td style="color: green;">
                                    {{ number_format($currencyTotals['dollar'], 2) }} $
                                </td>
                                <td style="color: green;">
                                    {{ number_format($currencyTotals['euro'], 2) }} €
                                </td>
                            </tr>
                        </tbody>
                    </table>
                @endif


                {{-- ================= EXPENSES ================= --}}
                @if (
                    (isset($expensesPurchase) && $expensesPurchase->count()) ||
                    (isset($expensesShippingCharges) && $expensesShippingCharges->count()) ||
                    (isset($expensesPaymentExpenses) && $expensesPaymentExpenses->count())
                )

                    {{-- PURCHASE --}}
                    @if (isset($expensesPurchase) && $expensesPurchase->count())
                        <div class="print-section-title d-flex justify-content-between align-items-center">
                            <span>المشتريات</span>

                            <button class="btn btn-sm btn-outline-primary no-print"
                                onclick="toggleTable('purchaseTable')">
                                عرض التفاصيل
                            </button>
                        </div>

                        <table class="table table-bordered text-center">
                            <thead>
                                <tr>
                                    <th>عدد العمليات</th>
                                    <th>الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ $expensesPurchase->count() }}</td>
                                    <td style="color: green;">{{ $expensesPurchase->sum('cost') }} DLY</td>
                                </tr>
                            </tbody>
                        </table>

                        <div id="purchaseTable" style="display:none;">
                            <table class="table table-bordered text-center">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>الوصف</th>
                                        <th>القيمة</th>
                                        <th>التاريخ</th>
                                        <th>ملاحظات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($expensesPurchase as $i => $purchase)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $purchase->desc->name }}</td>
                                            <td style="color: green;">{{ $purchase->cost }} DLY</td>
                                            <td>{{ $purchase->date }}</td>
                                            <td>{{ $purchase->note }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif


                    {{-- SHIPPING --}}
                    @if (isset($expensesShippingCharges) && $expensesShippingCharges->count())
                        <div class="print-section-title d-flex justify-content-between align-items-center">
                            <span>مصاريف الشحن</span>

                            <button class="btn btn-sm btn-outline-primary no-print"
                                onclick="toggleTable('shippingTable')">
                                عرض التفاصيل
                            </button>
                        </div>

                        <table class="table table-bordered text-center">
                            <thead>
                                <tr>
                                    <th>عدد العمليات</th>
                                    <th>الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ $expensesShippingCharges->count() }}</td>
                                    <td style="color: green;">{{ $expensesShippingCharges->sum('cost') }} DLY</td>
                                </tr>
                            </tbody>
                        </table>

                        <div id="shippingTable" style="display:none;">
                            <table class="table table-bordered text-center">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>نوع الشحن</th>
                                        <th>الوزن</th>
                                        <th>القيمة</th>
                                        <th>التاريخ</th>
                                        <th>ملاحظات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($expensesShippingCharges as $i => $charge)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $charge->type->name }}</td>
                                            <td>{{ $charge->weight }}</td>
                                            <td style="color: green;">{{ $charge->cost }} DLY</td>
                                            <td>{{ $charge->date }}</td>
                                            <td>{{ $charge->note }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif


                    {{-- PAYMENT EXPENSES --}}
                    @if (isset($expensesPaymentExpenses) && $expensesPaymentExpenses->count())
                        <div class="print-section-title d-flex justify-content-between align-items-center">
                            <span>مصاريف الدفع</span>

                            <button class="btn btn-sm btn-outline-primary no-print"
                                onclick="toggleTable('paymentExpenseTable')">
                                عرض التفاصيل
                            </button>
                        </div>

                        <table class="table table-bordered text-center">
                            <thead>
                                <tr>
                                    <th>عدد العمليات</th>
                                    <th>الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ $expensesPaymentExpenses->count() }}</td>
                                    <td style="color: green;">{{ $expensesPaymentExpenses->sum('cost') }} DLY</td>
                                </tr>
                            </tbody>
                        </table>

                        <div id="paymentExpenseTable" style="display:none;">
                            <table class="table table-bordered text-center">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>من - إلى</th>
                                        <th>المنصة</th>
                                        <th>القيمة</th>
                                        <th>الحالة</th>
                                        <th>التاريخ</th>
                                        <th>ملاحظات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($expensesPaymentExpenses as $i => $expense)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $expense->from }} - {{ $expense->to }}</td>
                                            <td>{{ $expense->platform->name }}</td>
                                            <td style="color: green;">{{ $expense->cost }} DLY</td>

                                            @if ($expense->status)
                                                <td class="text-primary">مدفوعة</td>
                                            @else
                                                <td class="text-danger">غير مدفوعة</td>
                                            @endif

                                            <td>{{ $expense->date }}</td>
                                            <td>{{ $expense->note }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif


                {{-- ================= DELIVERED ================= --}}
                @if (isset($ordersDelivered) && $ordersDelivered->count())
                    <div class="print-section-title d-flex justify-content-between align-items-center">
                        <span>الطلبيات المستلمة</span>

                        <button class="btn btn-sm btn-outline-primary no-print"
                            onclick="toggleTable('deliveredTable')">
                            عرض التفاصيل
                        </button>
                    </div>

                    <table class="table table-bordered text-center">
                        <thead>
                            <tr>
                                <th>عدد الطلبيات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ $ordersDelivered->count() }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div id="deliveredTable" style="display:none;">
                        <table class="table table-bordered text-center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>كود الزبون</th>
                                    <th>العنوان</th>
                                    <th>المتبقي</th>
                                    <th>قيمة الشحن</th>
                                    <th>عدد الطرود والقطع</th>
                                    <th>الرحلة</th>
                                    <th>تاريخ التسليم</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ordersDelivered as $i => $order)
                                    <tr style="color:#282E4D;">
                                        <td>{{ $i + 1 }}</td>

                                        <td>
                                            {{ $order->customer->name }}
                                            <br>
                                            {{ $order->customer->code }}
                                        </td>

                                        <td>{{ $order->customer->addess ?? '-' }}</td>

                                        <td style="color: green;">
                                            {{ ($order->ship_price ?? 0) + $order->total_price - $order->paid_price }}
                                            {{ $order->currency_sign }}
                                        </td>

                                        <td style="color: green;">
                                            {{ $order->ship_price ?? '-' }}
                                            {{ $order->currency_sign }}
                                        </td>

                                        <td>
                                            {{ $order->bags_num ?? '-' }}
                                            <br>
                                            @if($order->bags_qty)
                                                الكمية في الطرود <span style="color: blue;">{{ $order->bags_qty }}</span>
                                            @endif
                                        </td>

                                        <td>{{ $order->trip->code ?? '-' }}</td>

                                        <td>{{ $order->delivered_at }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif


                {{-- ================= UPDATED ORDERS ================= --}}
                @if (isset($ordersUpdatedget) && $ordersUpdatedget->count())
                    <div class="print-section-title d-flex justify-content-between align-items-center">
                        <span>الطلبيات التي تم تعديلها</span>

                        <button class="btn btn-sm btn-outline-primary no-print"
                            onclick="toggleTable('updatedTable')">
                            عرض التفاصيل
                        </button>
                    </div>

                    <table class="table table-bordered text-center">
                        <thead>
                            <tr>
                                <th>عدد التعديلات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ $ordersUpdatedget->count() }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div id="updatedTable" style="display:none;">
                        <table class="table table-bordered text-center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>العنوان</th>
                                    <th>تفاصيل</th>
                                    <th>التاريخ</th>
                                    <th>المستخدم</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ordersUpdatedget as $i => $t)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $t->title }}</td>

                                        <td>
                                            @if (strlen($t->log) > 50)
                                                <span data-bs-toggle="tooltip" title="{{ $t->log }}">
                                                    {{ substr($t->log, 0, 50) }}...
                                                </span>
                                            @else
                                                {{ $t->log }}
                                            @endif
                                        </td>

                                        <td>{{ $t->created_at->format('Y-m-d') }}</td>
                                        <td>{{ $t->user->name }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif


                {{-- ================= DELETED ORDERS ================= --}}
                @if (isset($ordersDeletedget) && $ordersDeletedget->count())
                    <div class="print-section-title d-flex justify-content-between align-items-center">
                        <span>الطلبيات التي تم حذفها</span>

                        <button class="btn btn-sm btn-outline-primary no-print"
                            onclick="toggleTable('deletedTable')">
                            عرض التفاصيل
                        </button>
                    </div>

                    <table class="table table-bordered text-center">
                        <thead>
                            <tr>
                                <th>عدد الحذف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ $ordersDeletedget->count() }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div id="deletedTable" style="display:none;">
                        <table class="table table-bordered text-center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>العنوان</th>
                                    <th>تفاصيل</th>
                                    <th>التاريخ</th>
                                    <th>المستخدم</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ordersDeletedget as $i => $t)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $t->title }}</td>

                                        <td>
                                            @if (strlen($t->log) > 50)
                                                <span data-bs-toggle="tooltip" title="{{ $t->log }}">
                                                    {{ substr($t->log, 0, 50) }}...
                                                </span>
                                            @else
                                                {{ $t->log }}
                                            @endif
                                        </td>

                                        <td>{{ $t->created_at->format('Y-m-d') }}</td>
                                        <td>{{ $t->user->name }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif


                <div class="print-footer">
                    {{ config('system.company_name') }}
                </div>

            </div>

        </div>
    </div>

@endsection

@section('script')
<script>
    function toggleTable(id) {
        let el = document.getElementById(id);

        if (el.style.display === "none") {
            el.style.display = "block";
        } else {
            el.style.display = "none";
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (el) {
            return new bootstrap.Tooltip(el);
        });
    });
</script>
@endsection
