@extends('layout.master')

@section('title')
    <title>
        الطلبات الغير مدفوعة </title>
@endsection

@section('style')
    <!-- data tables css -->
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/dataTables.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/select.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/autoFill.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/keyTable.bootstrap5.min.css') }}">
    <!-- [Page specific CSS] end -->
@endsection

@section('content')
    <section class="pc-container">
        <div class="pc-content">
            <!-- [ breadcrumb ] start -->
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">الرئيسية</a></li>
                                <li class="breadcrumb-item" aria-current="page">القائمة</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0"> الطلبات الغير مدفوعة <span class="text-primary">{{ $customer->code }} -
                                        {{ $customer->name }}</span> </h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->

            @include('layout.validation-messages')

            <!-- [ Main Content ] start -->
            <div class="row">
                <!-- Autofill table start -->
                <div class="col-sm-12">
                    <div class="card">
                        @if ($orders->count() == 0)
                            <div class="card-header">
                                <h5>لايوجد اي طلبات غير مدفوعة لهذا الزبون</h5>
                            </div>
                        @endif

                        <form action="{{ route('customer.pay', $customer->id) }}" onsubmit="return submitCheckedOrders();"
                            id="orders_form" method="post" enctype="multipart/form-data">
                            @method('PUT')
                            @csrf
                            <input type="hidden" name="checked_order_ids" id="checked_order_ids">
                            <input type="hidden" name="delivered_order_ids" id="delivered_order_ids">

                            <div class="card-body">
                                <div class="dt-responsive table-responsive">
                                    <div id="autofill_wrapper" class="dataTables_wrapper dt-bootstrap5">
                                        @if ($orders->count() > 0)
                                            <div class="row dt-row">
                                                <div class="col-sm-12">
                                                    <table class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>#</th>
                                                                <th>كود الطلب</th>
                                                                <th>حالة الطلب</th>
                                                                <th>تسليم الطلبية</th>
                                                                <th>كمية القطع</th>
                                                                <th>الرحلة</th>

                                                                <th>اجمالي سعر البضاعة</th>
                                                                <th>القيمة المتبقية</th>
                                                                <th>وزن البضاعة وسعر الشحن</th>
                                                                <th>المستحقات</th>
                                                                <th>ملاحظات</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($orders as $order)
                                                                @if ($order->status == 'paid' || $order->status == 'unpaid')
                                                                    <tr class="odd"
                                                                        onclick="checkRow(event, 'checkboxOrders{{ $order->id }}')">

                                                                        <td>
                                                                            <input type="checkbox"
                                                                                id="checkboxOrders{{ $order->id }}"
                                                                                value="{{ $order->id }}"
                                                                                name="checkedBox"
                                                                                onclick="toggleCheckbox(event, 'checkboxOrders{{ $order->id }}')"
                                                                                style="width: 25px; height: 25px;">
                                                                        </td>

                                                                        <td>{{ $order->code }}<br>{{ $order->customer->name }}
                                                                            - {{ $order->customer->code }}</td>

                                                                        @if ($order->status == 'paid_full')
                                                                            <td class="text-info"> مدفوعة
                                                                                @if ($order->delivered)
                                                                                    <br><span class="text-success">تم
                                                                                        التسليم
                                                                                        {{ $order->delivered_at }}</span>
                                                                                @elseif($order->arrived)
                                                                                    <br><span
                                                                                        class="text-primary">وصلت</span> -
                                                                                    <span
                                                                                        class="text-primary">{{ $order->arrived_at }}</span>
                                                                                @endif
                                                                            </td>
                                                                        @elseif ($order->status == 'unpaid')
                                                                            <td class="text-warning"> غير مدفوعة
                                                                                @if ($order->delivered)
                                                                                    <br><span class="text-success">تم
                                                                                        التسليم
                                                                                        {{ $order->delivered_at }}</span>
                                                                                @elseif($order->arrived)
                                                                                    <br><span
                                                                                        class="text-primary">وصلت</span> -
                                                                                    <span
                                                                                        class="text-primary">{{ $order->arrived_at }}</span>
                                                                                @endif
                                                                            </td>
                                                                        @elseif ($order->status == 'paid')
                                                                            <td class="text-info"> مدفوعة من غير الشحن
                                                                                @if ($order->delivered)
                                                                                    <br><span class="text-success">تم
                                                                                        التسليم
                                                                                        {{ $order->delivered_at }}</span>
                                                                                @elseif($order->arrived)
                                                                                    <br><span
                                                                                        class="text-primary">وصلت</span> -
                                                                                    <span
                                                                                        class="text-primary">{{ $order->arrived_at }}</span>
                                                                                @endif
                                                                            </td>
                                                                        @elseif ($order->status == 'canceld')
                                                                            <td class="text-danger"> ملغية
                                                                                @if ($order->delivered)
                                                                                    <br><span class="text-success">تم
                                                                                        التسليم
                                                                                        {{ $order->delivered_at }}</span>
                                                                                @elseif($order->arrived)
                                                                                    <br><span
                                                                                        class="text-primary">وصلت</span> -
                                                                                    <span
                                                                                        class="text-primary">{{ $order->arrived_at }}</span>
                                                                                @endif
                                                                            </td>
                                                                        @elseif ($order->status == 'delivered')
                                                                            <td class="text-success"> تم التسليم
                                                                                @if ($order->delivered)
                                                                                    <br><span class="text-success">تم
                                                                                        التسليم
                                                                                        {{ $order->delivered_at }}</span>
                                                                                @elseif($order->arrived)
                                                                                    <br><span
                                                                                        class="text-primary">وصلت</span> -
                                                                                    <span
                                                                                        class="text-primary">{{ $order->arrived_at }}</span>
                                                                                @endif
                                                                            </td>
                                                                        @endif

                                                                        <td>
                                                                            <input type="checkbox"
                                                                                id="checkboxOrders2{{ $order->id }}"
                                                                                value="{{ $order->id }}"
                                                                                @if ($order->delivered) checked @endif
                                                                                name="checkedDeliveredBox"
                                                                                style="width: 25px; height: 25px;">
                                                                        </td>


                                                                        <td class="">{{ $order->qty }}
                                                                            @if ($order->bags_num)
                                                                                <br> عدد الاكياس <span
                                                                                    style="color: blue;">{{ $order->bags_num }}</span>
                                                                            @endif
                                                                            @if ($order->bags_qty)
                                                                                <br> الكمية في الاكياس <span
                                                                                    style="color: blue;">{{ $order->bags_qty }}</span>
                                                                            @endif
                                                                        </td>

                                                                        @if ($order->trip)
                                                                            <td class="">{{ $order->trip->code }} -
                                                                                {{ $order->trip_details }}</td>
                                                                        @elseif($order->trip_details)
                                                                            <td class=""> {{ $order->trip_details }}
                                                                            </td>
                                                                        @else
                                                                            <td class=""> - </td>
                                                                        @endif

                                                                        <td class="" style="color: green;">
                                                                            {{ $order->total_price }} DLY
                                                                            {{-- <br> سعر التكلفة <span
                                                                                    style="color: green;">{{ $order->cost_price }}
                                                                                    DLY</span> --}}
                                                                        </td>


                                                                        <td class="text-warning"><span>
                                                                                {{ ($order->total_price-$order->discount) + $order->ship_price - $order->paid_price }}
                                                                                DLY </span></td>
                                                                        <td class="">
                                                                            @if ($order->kilo)
                                                                                ({{ $order->kilo }} KG)
                                                                                <br>
                                                                            @endif
                                                                            @if ($order->ship_price)
                                                                                إجمالي سعر الشحن <span
                                                                                    style="color: green;">{{ $order->ship_price }}
                                                                                    DLY</span>
                                                                            @endif
                                                                            @if (!$order->ship_price)
                                                                                -
                                                                            @endif
                                                                        </td>

                                                                        <td>
                                                                            @if ($order->ship_price)
                                                                                <input type="number" disabled
                                                                                    step="any" style="width: 150px;"
                                                                                    max="{{ ($order->total_price-$order->discount) + $order->ship_price - $order->paid_price }}"
                                                                                    name="price[{{ $order->id }}]"
                                                                                    class="form-control" required
                                                                                    placeholder="المدفوعات">
                                                                            @else
                                                                                <input type="number" disabled
                                                                                    step="any" style="width: 150px;"
                                                                                    max="{{ ($order->total_price-$order->discount) - $order->paid_price }}"
                                                                                    name="price[{{ $order->id }}]"
                                                                                    class="form-control" required
                                                                                    placeholder="المدفوعات">
                                                                            @endif
                                                                                                                    <div class="col-md-12">
                                            <div class="form-group"></div>
<select name="payment_id[{{ $order->id }}]" disabled required class="form-control payment-select">                                                                                <option>-- اختر طريقة الدفع --</option>
                                                                                @foreach ($payments as $index => $payment)
                                                                                @if ($payment->id == 1)
                                                                                    <option value="{{ $payment->id }}" selected>{{ $payment->name }}</option>
                                                                                @else
                                                                                    <option value="{{ $payment->id }}">{{ $payment->name }}</option>
                                                                                @endif
                                                                                @endforeach
                                                                            </select>
                                                                            </div>
                                                                        </div>
                                                                        </td>
                                                                        @if ($order->note)
                                                                            <td>{{ $order->note }}</td>
                                                                        @else
                                                                            <td> - </td>
                                                                        @endif
                                                                    </tr>
                                                                @endif
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="col-md-12">
                                                    <button type="submit" class="btn btn-primary mb-4">حفظ
                                                        التعديلات</button>
                                                </div>

                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Autofill table end -->
            </div>
            <!-- [ Main Content ] end -->
        </div>
    </section>
@endsection

@section('script')
    <!-- datatable Js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="{{ asset('assets/js/plugins/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.autoFill.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/autoFill.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.keyTable.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/keyTable.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.select.min.js') }}"></script>


    <script>
        function toggleCheckbox(event, id) {
            event.stopPropagation();
            const checkbox = document.getElementById(id);
            checkbox.checked = !checkbox.checked;
            togglePriceInput(id, checkbox.checked);
        }

        function toggleCheckbox2(event, id) {
            event.stopPropagation();
            const checkbox = document.getElementById(id);
            checkbox.checked = !checkbox.checked;
        }

        function checkRow(event, id) {
if (
    event.target.type === 'checkbox' ||
    event.target.type === 'number' ||
    event.target.tagName === 'SELECT'
) {
    return;
}
            const checkbox = document.getElementById(id);
            checkbox.checked = !checkbox.checked;
            togglePriceInput(id, checkbox.checked);
        }

function togglePriceInput(id, isChecked) {
    const orderId = id.replace('checkboxOrders', '');
    const priceInput = document.querySelector(`input[name="price[${orderId}]"]`);
    const paymentSelect = document.querySelector(`select[name="payment_id[${orderId}]"]`);

    if (priceInput && paymentSelect) {
        if (isChecked) {
            priceInput.removeAttribute('disabled');
            paymentSelect.removeAttribute('disabled');
        } else {
            priceInput.setAttribute('disabled', 'disabled');
            paymentSelect.setAttribute('disabled', 'disabled');
        }
    }
}

        function submitCheckedOrders() {
            const checkedBoxes = document.querySelectorAll('input[name="checkedBox"]:checked');
            const checkedBoxes2 = document.querySelectorAll('input[name="checkedDeliveredBox"]:checked');
            const ids = Array.from(checkedBoxes).map(cb => cb.value);
            const ids2 = Array.from(checkedBoxes2).map(cb => cb.value);
            document.getElementById('checked_order_ids').value = ids.join(',');
            document.getElementById('delivered_order_ids').value = ids2.join(',');

            if (ids.length === 0 && ids2.length === 0) {
                return false;
            }
            return true;
        }
    </script>
@endsection
