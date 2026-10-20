@extends('layout.master')

@section('title')
    <title> تعديل بيت الرحلات </title>
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
                                <li class="breadcrumb-item" aria-current="page">تعديل البيانات</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">تعديل بيانات <span class="text-primary">{{ $trip->code }}</span> </h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->

            @include('layout.validation-messages')


            <form action="{{ route('trip.update', $trip->id) }}" id="orders_form" method="post" enctype="multipart/form-data">
                @method('PUT')
                @csrf
                <input type="hidden" name="checked_order_ids" id="checked_order_ids">


                <!-- [ Main Content ] start -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>تعديل البيانات</h5>
                            </div>
                            <div class="card-body">

                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">كود الرحلة</label>
                                            <input type="text" name="code" value="{{ $trip->code }}"
                                                class="form-control" id="exampleInputName343" required
                                                placeholder="كود الرحلة"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال كود الرحلة')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">البلد المصدر</label>
                                            <input type="text" name="country" value="{{ $trip->country }}"
                                                class="form-control" id="exampleInputName343" required
                                                placeholder="البلد المصدر"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال البلد المصدر')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">تاريخ مغادرة الرحلة</label>
                                            <input type="date" name="out_date" value="{{ $trip->out_date }}"
                                                class="form-control" id="exampleInputName343" required
                                                placeholder="تاريخ مغادرة الرحلة"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال تاريخ مغادرة الرحلة')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">تاريخ الوصول المتوقع</label>
                                            <input type="date" name="arrive_date" value="{{ $trip->arrive_date }}"
                                                class="form-control" id="exampleInputName343" required
                                                placeholder="تاريخ الوصول المتوقع"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال تاريخ الوصول المتوقع')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName">وثيقة
                                                (إختياري)</label>
                                            <input type="file" name="doc" class="form-control" id="exampleInputName"
                                                aria-describedby="nameHelp" accept=".pdf,image/jpeg,image/png,image/jpg"
                                                placeholder="وثيقة من الرحلة ان وجدت">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName">ملاحطات (إختياري)</label>
                                            <textarea type="text" name="note" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                placeholder="ملاحظات (إختياري)">{{ $trip->note }}</textarea>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <button type="submit" onclick="submitCheckedOrders()" class="btn btn-primary mb-4">حفظ التعديلات</button>
                                    </div>
                                </div>

                            </div>

                        </div>
                    </div>

                </div>
                <!-- [ form-element ] end -->

                <!-- [ Main Content ] start -->
                <div class="row">
                    @if ($orders->count() > 0)
                        <!-- [ Hover-table ] start -->
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5>الطلبات المدرجة تحت الرحلة مع الطلبات الغير مدرجة لاي رحلة</h5>
                                </div>
                                <div class="card-body table-border-style">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>

                                                    <th class="sorting" tabindex="0" aria-controls="autofill"
                                                        rowspan="1" colspan="1" style="width: 175.717px;"
                                                        aria-label="Name: activate to sort column ascending">
                                                        #
                                                    </th>
                                                    <th class="sorting" tabindex="0" aria-controls="autofill"
                                                        rowspan="1" colspan="1" style="width: 175.717px;"
                                                        aria-label="Name: activate to sort column ascending">
                                                        كود الطلب
                                                    </th>
                                                    <th class="sorting" tabindex="0" aria-controls="autofill"
                                                        rowspan="1" colspan="1" style="width: 175.717px;"
                                                        aria-label="Product: activate to sort column ascending">
                                                        الزبون
                                                    </th>

                                                    <th class="sorting" tabindex="0" aria-controls="autofill"
                                                        rowspan="1" colspan="1" style="width: 175.717px;"
                                                        aria-label="Product: activate to sort column ascending">
                                                        حالة الطلب
                                                    </th>

                                                    <th class="sorting" tabindex="0" aria-controls="autofill"
                                                        rowspan="1" colspan="1" style="width: 175.717px;"
                                                        aria-label="Product: activate to sort column ascending">
                                                        كمية القطع
                                                    </th>


                                                    <th class="sorting" tabindex="0" aria-controls="autofill"
                                                        rowspan="1" colspan="1" style="width: 175.717px;"
                                                        aria-label="Product: activate to sort column ascending">
                                                        اجمالي سعر البضاعة
                                                    </th>

                                                    <th class="sorting" tabindex="0" aria-controls="autofill"
                                                        rowspan="1" colspan="1" style="width: 175.717px;"
                                                        aria-label="Product: activate to sort column ascending">
                                                        القيمة المدفوعة
                                                    </th>

                                                    <th class="sorting" tabindex="0" aria-controls="autofill"
                                                        rowspan="1" colspan="1" style="width: 175.717px;"
                                                        aria-label="Product: activate to sort column ascending">
                                                        وزن البضاعة
                                                    </th>

                                                    <th class="sorting" tabindex="0" aria-controls="autofill"
                                                        rowspan="1" colspan="1" style="width: 175.717px;"
                                                        aria-label="Product: activate to sort column ascending">
                                                        ملاحظات
                                                    </th>
                                                </tr>
                                            </thead>

                                            <tbody>

                                                @foreach ($orders as $order)
                                                    <tr class="odd"
                                                        onclick="checkRow(event, 'checkboxOrders{{ $order->id }}')">
                                                        <td>
                                                            @if ($order->trip)
                                                            <input type="checkbox" id="checkboxOrders{{ $order->id }}"
                                                            value="{{ $order->id }}" checked name="checkedBox"
                                                            onclick="toggleCheckbox(event, 'checkboxOrders{{ $order->id }}')">
                                                            @else
                                                            <input type="checkbox" id="checkboxOrders{{ $order->id }}"
                                                                value="{{ $order->id }}" name="checkedBox"
                                                                onclick="toggleCheckbox(event, 'checkboxOrders{{ $order->id }}')">
                                                            @endif
                                                        </td>
                                                        <td>{{ $order->code }}</td>

<td class="">{{ $order->customer->name }}
                                                                <br>{{ $order->customer->code }}
                                                            </td>

                                                            @if ($order->status == 'paid_full')
                                                            <td class="text-info"> مدفوعة
                                                                @if ($order->delivered)
                                                                    <br><span class="text-success">تم التسليم</span>
                                                                @elseif($order->arrived)
                                                                    <br><span class="text-primary">وصلت</span>
                                                                @endif
                                                            </td>
                                                        @elseif ($order->status == 'unpaid')
                                                            <td class="text-warning"> غير مدفوعة
                                                                @if ($order->delivered)
                                                                    <br><span class="text-success">تم التسليم</span>
                                                                @elseif($order->arrived)
                                                                    <br><span class="text-primary">وصلت</span>
                                                                @endif
                                                            </td>
                                                        @elseif ($order->status == 'paid')
                                                            <td class="text-info"> مدفوعة من غير الشحن
                                                                @if ($order->delivered)
                                                                    <br><span class="text-success">تم التسليم</span>
                                                                @elseif($order->arrived)
                                                                    <br><span class="text-primary">وصلت</span>
                                                                @endif
                                                            </td>
                                                        @elseif ($order->status == 'canceld')
                                                            <td class="text-danger"> ملغية
                                                                @if ($order->delivered)
                                                                    <br><span class="text-success">تم التسليم</span>
                                                                @elseif($order->arrived)
                                                                    <br><span class="text-primary">وصلت</span>
                                                                @endif
                                                            </td>
                                                        @elseif ($order->status == 'delivered')
                                                            <td class="text-success"> تم التسليم
                                                                @if ($order->delivered)
                                                                    <br><span class="text-success">تم التسليم</span>
                                                                @elseif($order->arrived)
                                                                    <br><span class="text-primary">وصلت</span>
                                                                @endif
                                                            </td>
                                                        @endif

                                                        <td>{{ $order->qty }}
                                                            @if ($order->bags_qty)
                                                                <br> الكمية في الاكياس <span
                                                                    style="color: blue;">{{ $order->bags_qty }}</span>
                                                            @endif
                                                        </td>
                                                        <td style="color: green;">
                                                            {{ $order->total_price }} DLY
                                                            @if ($order->cost_price)
                                                            <br> سعر التكلفة <span
                                                            style="color: green;">{{ $order->cost_price }}
                                                            DLY</span>
                                                            @endif
                                                        </td>
                                                        <td><span style="color: green;">{{ $order->paid_price }}
                                                                DLY</span></td>

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
                                                                    @if (!$order->ship_price && !$order->kilo)
                                                                        -
                                                                    @endif
                                                                </td>

                                                        @if ($order->note)
                                                            <td>{{ $order->note }}</td>
                                                        @else
                                                            <td> - </td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit" onclick="submitCheckedOrders()" class="btn btn-primary mb-4">حفظ التعديلات</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- [ Hover-table ] end -->
                    @endif
                </div>

            </form>


        </div>
        </div>
    </section>
@endsection

@section('script')
    <script>
        function toggleCheckbox(event, id) {
            // Prevent the checkbox click event from bubbling up to the row click event
            event.stopPropagation();
            const checkbox = document.getElementById(id);
            checkbox.checked = !checkbox.checked;
        }

        function checkRow(event, id) {
            const checkbox = document.getElementById(id);
            checkbox.checked = !checkbox.checked;
        }

        function submitCheckedOrders() {
            const checkedBoxes = document.querySelectorAll('input[name="checkedBox"]:checked');
            const ids = Array.from(checkedBoxes).map(cb => cb.value);
            document.getElementById('checked_order_ids').value = ids.join(',');
            document.getElementById('orders_form').submit();
        }
    </script>
@endsection
