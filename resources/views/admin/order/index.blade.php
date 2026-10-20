@extends('layout.master')

@section('title')
    <title>
        الطلبات </title>
@endsection

@section('style')
    <!-- data tables css -->
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/dataTables.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/select.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/autoFill.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/keyTable.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets\select2\css\select2.min.css') }}">
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
                                @if ($page == 'all')
                                    <h2 class="mb-0">كل الطلبات </h2>
                                @elseif($page == 'delivered')
                                    <h2 class="mb-0">طلبات مستلمة </h2>
                                @elseif($page == 'notDelivered')
                                    <h2 class="mb-0">طلبات غير مستلمة </h2>
                                @elseif($page == 'ready')
                                    <h2 class="mb-0">طلبات جاهزة للتسليم </h2>
                                @elseif($page == 'unpaid')
                                    <h2 class="mb-0">طلبات غير مدفوعة </h2>
                                @elseif($page == 'no_trip')
                                    <h2 class="mb-0">طلبات من غير رحلة لأكثر من 12 الساعة </h2>
                                @endif

                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->
            @can('treasury-list')
                @if (isset($totalDay) &&
                        isset($totalMonth) &&
                        isset($totalYear) &&
                        isset($totalDay2) &&
                        isset($totalMonth2) &&
                        isset($totalYear2))
                    <div class="row">
                        <div class="col-md-6 col-xxl-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s bg-light-primary">
                                                <span class="pc-micon">
                                                    <i class="fas fa-calendar-day" style="font-size: 17px;"></i></span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-0">قيمة الطلبات اليومية</h6>
                                        </div>
                                    </div>
                                    <div class="bg-body p-3 mt-3 rounded">
                                        <div class="mt-3 row align-items-center">

                                            <div class="col-12">
                                                <h5 class="mb-1"> <span class="text-success">
                                                    </span>{{ $totalDay + $totalDay2 }} <span
                                                        class="bg-light-primary">د،ل</span>
                                                </h5>
                                                <h5 class="mb-1"> <span class="text-success">
                                                    </span>{{ $totalDayDollar }} <span class="bg-light-primary">$</span>
                                                </h5>
                                                <h5 class="mb-1"> <span class="text-success">
                                                    </span>{{ $totalDayEuro }} <span class="bg-light-primary">€</span>
                                                </h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xxl-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">

                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s bg-light-info">
                                                <span class="pc-micon">
                                                    <i class="fas fa-calendar-day" style="font-size: 17px;"></i></span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-0">قيمة الطلبات الشهرية</h6>
                                        </div>
                                    </div>
                                    <div class="bg-body p-3 mt-3 rounded">
                                        <div class="mt-3 row align-items-center">

                                            <div class="col-12">
                                                <h5 class="mb-1"> <span class="text-success">
                                                    </span>{{ $totalMonth + $totalMonth2 }} <span
                                                        class="bg-light-info">د.ل</span>
                                                </h5>
                                                <h5 class="mb-1"> <span class="text-success">
                                                    </span>{{ $totalMonthDollar }} <span class="bg-light-primary">$</span>
                                                </h5>
                                                <h5 class="mb-1"> <span class="text-success">
                                                    </span>{{ $totalMonthEuro }} <span class="bg-light-primary">€</span>
                                                </h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xxl-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s bg-light-secondary">
                                                <span class="pc-micon">
                                                    <i class="fas fa-calendar-day" style="font-size: 17px;"></i></span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-0">قيمة الطلبات السنوية </h6>
                                        </div>
                                    </div>
                                    <div class="bg-body p-3 mt-3 rounded">
                                        <div class="mt-3 row align-items-center">

                                            <div class="col-12">
                                                <h5 class="mb-1"> <span class="text-success">
                                                    </span>{{ $totalYear + $totalYear2 }} <span
                                                        class="bg-light-secondary">د.ل</span>
                                                </h5>
                                                <h5 class="mb-1"> <span class="text-success">
                                                    </span>{{ $totalYearDollar }} <span class="bg-light-primary">$</span>
                                                </h5>
                                                <h5 class="mb-1"> <span class="text-success">
                                                    </span>{{ $totalYearEuro }} <span class="bg-light-primary">€</span>
                                                </h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endcan

            @include('layout.validation-messages')

            <!-- [ Main Content ] start -->
            <div class="row">
                <!-- Autofill table start -->
                <div class="col-sm-12">
                    <div class="card">
                        {{-- <div class="card-header">
                        <a href="{{ route('ad.create') }}" class=" btn btn-outline-success">{{ __('messages.ad.add') }}</a>
                    </div> --}}
                        <div class="card-header">

                            @can('order-create')
                                <a class="btn btn-primary m-t-5" data-bs-toggle="collapse" href="#collapseExample"
                                    role="button" aria-expanded="false" aria-controls="collapseExample">اضافة طلب جديدة</a>
                            @endcan
                            <a class="btn btn-primary m-t-5" style="margin-right: 10px;" data-bs-toggle="collapse"
                                href="#collapseExample2" role="button" aria-expanded="false"
                                aria-controls="collapseExample2">الـبـحـث</a>

                            @if ($page != 'ready')
                                @can('order-list')
                                    <a class="btn btn-secondary m-t-5" style="margin-right: 10px; float: left;"
                                        href=".printTable" data-bs-toggle="modal">طـبـاعـة</a>
                                @endcan
                            @endif

                            <a class="btn btn-info m-t-5" style="margin-right: 10px; float: left;"
                                data-bs-toggle="collapse" href="#collapseExample23" role="button" aria-expanded="false"
                                aria-controls="collapseExample23">تعديل رحلات</a>


                            {{-- @can('order-create')
                            <a class="btn btn-primary m-t-5" style="margin-right: 10px; float: left;"
                                data-bs-toggle="collapse" href="#collapseExampleAddRecipt" role="button" aria-expanded="false"
                                aria-controls="collapseExampleAddRecipt">اضـافـة إيصـال</a>
                            @endcan --}}


                            @if ($page == 'ready')
                                <button type="button" class="btn btn-light-info" data-bs-toggle="modal"
                                    style="margin-right: 10px;"
                                    data-bs-target="#exampleModalLivePrintReadyOrders">طـبـاعـة</button>
                                <div id="exampleModalLivePrintReadyOrders" class="modal fade" tabindex="-1"
                                    role="dialog" aria-labelledby="exampleModalLiveLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <div class="modal-body text-center">
                                                <div class="container">
                                                    <div>
                                                        <i class="ti ti-printer text-primary fa-5x mb-3"></i>
                                                    </div>
                                                    <p style="font-size: 18px; word-wrap: break-word;">
                                                        تحديد تاريخ وصول الطلبية</p>
                                                    <form action="{{ route('order.ready.print') }}" target="_blank"
                                                        method="get">
                                                        @csrf
                                                        <div class="col-md-12">
                                                            <div class="form-group">
                                                                <label class="form-label" for="exampleInputName232">اتاريخ
                                                                    الوصول من</label>
                                                                <input type="date" name="from" class="form-control"
                                                                    value="{{ now()->format('Y-m-d') }}"
                                                                    id="exampleInputName232"required
                                                                    placeholder="تاريخ وصول الطلبية من"
                                                                    oninvalid="this.setCustomValidity('الرجاء ادخال تاريخ وصول الطلبية')"
                                                                    oninput="this.setCustomValidity('')">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <div class="form-group">
                                                                <label class="form-label" for="exampleInputName232">اتاريخ
                                                                    الوصول الي (اختياري)</label>
                                                                <input type="date" name="to" class="form-control"
                                                                    id="exampleInputName232"
                                                                    placeholder="تاريخ وصول الطلبية الي">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer d-flex justify-content-center">
                                                            <button type="submit"
                                                                class="btn btn-primary">طـبـاعـة</button>
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">إغــلاق</button>
                                                        </div>
                                                    </form>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                            @endif

                        </div>


                        <div class="collapse" id="collapseExample23">
                            <div class="card-body">
                                <form action="{{ route('trip.update') }}" method="post" enctype="multipart/form-data">
                                    @csrf


                                    <div class="row" id="trip-fields">
                                        <div class="col-md-3">
                                            <div class="form-group pt-4">
                                                <label class="form-label">اضف كود دحلة</label><br>
                                                <button type="button" id="add-trip" class="btn btn-primary p-3">
                                                    <i class="fas fa-plus-circle"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label">حالة الرحلات</label>
                                                <select name="trip_status" required class="form-control">
                                                    @foreach ($status as $status1)
                                                        @if ($loop->iteration == 2)
                                                            <option selected value="{{ $status1->name }}">
                                                                {{ $status1->name }}</option>
                                                        @else
                                                            <option value="{{ $status1->name }}">{{ $status1->name }}
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label">ملاحظات الرحلات</label>
                                                <textarea name="trip_note" class="form-control" placeholder="ملاحظات الرحلة"></textarea>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary mb-4">حفظ تعديل الرحلات</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- JS لإضافة وحذف الحقول -->
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                let counter = 0;

                                document.getElementById('add-trip').addEventListener('click', function() {
                                    counter++;

                                    let newField = `
                <div class="col-md-3 trip-input d-flex align-items-end">
                    <div class="form-group w-100">
                        <label class="form-label">رقم الرحلة ${counter}</label>
                        <div class="input-group">
                            <input type="text" name="trip_number[]" required class="form-control" placeholder="رقم الرحلة ${counter}">
                            <button type="button" class="btn btn-danger remove-trip">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;

                                    document.getElementById('trip-fields').insertAdjacentHTML('beforeend', newField);
                                });

                                // حذف الحقول عند الضغط على X
                                document.getElementById('trip-fields').addEventListener('click', function(e) {
                                    if (e.target.closest('.remove-trip')) {
                                        e.target.closest('.trip-input').remove();
                                    }
                                });
                            });
                        </script>



                        <div class="collapse" id="collapseExample2">
                            <div class="card-body">
                                <form action="{{ route('order.search') }}" method="get" enctype="multipart/form-data">
                                    @csrf

                                    <div class="row">
                                        @if (isset($query))
                                            <input type="hidden"
                                                @if (isset($query['page'])) value="{{ $query['page'] }}" @endif
                                                name="page">

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">اسم الزبون
                                                    </label>
                                                    <input type="text" name="customer_name"
                                                        @if (isset($query['customer_name'])) value="{{ $query['customer_name'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="اسم الزبون">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">كود
                                                        الطلبية</label>
                                                    <input type="text" name="code"
                                                        @if (isset($query['code'])) value="{{ $query['code'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="كود الطلبية">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">حالة
                                                        الطلبية</label>

                                                    <select name="status" id="trip_select" class="form-control">
                                                        <option>-- اختر حالة الطلبية --</option>
                                                        @if (isset($query['status']) && $query['status'] == 'paid_full')
                                                            <option selected value="paid_full">مدفوعة بالكامل (مع الشحن)
                                                            </option>
                                                            <option value="paid">مدفوعة</option>
                                                            <option value="unpaid">غير مدفوعة</option>
                                                            <option value="canceld">ملغية</option>
                                                            <option value="delivered">مستلمة</option>
                                                        @elseif (isset($query['status']) && $query['status'] == 'paid')
                                                            <option selected value="paid">مدفوعة</option>
                                                            <option value="paid_full">مدفوعة بالكامل (مع الشحن)</option>
                                                            <option value="unpaid">غير مدفوعة</option>
                                                            <option value="canceld">ملغية</option>
                                                            <option value="delivered">مستلمة</option>
                                                        @elseif (isset($query['status']) && $query['status'] == 'unpaid')
                                                            <option selected value="unpaid">غير مدفوعة</option>
                                                            <option value="paid_full">مدفوعة بالكامل (مع الشحن)</option>
                                                            <option value="paid">مدفوعة</option>
                                                            <option value="canceld">ملغية</option>
                                                            <option value="delivered">مستلمة</option>
                                                        @elseif (isset($query['status']) && $query['status'] == 'canceld')
                                                            <option selected value="canceld">ملغية</option>
                                                            <option value="paid_full">مدفوعة بالكامل (مع الشحن)</option>
                                                            <option value="paid">مدفوعة</option>
                                                            <option value="unpaid">غير مدفوعة</option>
                                                            <option value="delivered">مستلمة</option>
                                                        @elseif (isset($query['status']) && $query['status'] == 'delivered')
                                                            <option selected value="delivered">مستلمة</option>
                                                            <option value="paid_full">مدفوعة بالكامل (مع الشحن)</option>
                                                            <option value="paid">مدفوعة</option>
                                                            <option value="unpaid">غير مدفوعة</option>
                                                            <option value="canceld">ملغية</option>
                                                        @endif
                                                    </select>

                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName44343">كود
                                                        الرحلة</label>
                                                    <input type="text" name="trip_code"
                                                        @if (isset($query['trip_code'])) value="{{ $query['trip_code'] }}" @endif
                                                        class="form-control" id="exampleInputName44343"
                                                        placeholder="كود الرحلة">
                                                </div>
                                            </div>
                                        @else
                                            <input type="hidden" value="{{ $page }}" name="page">

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">اسم الزبون
                                                    </label>
                                                    <input type="text" name="customer_name" class="form-control"
                                                        id="exampleInputName343" placeholder="اسم الزبون">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">كود
                                                        الطلبية</label>
                                                    <input type="text" name="code" class="form-control"
                                                        id="exampleInputName343" placeholder="كود الطلبية">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">حالة
                                                        الطلبية</label>

                                                    <select name="status" id="trip_select" class="form-control">
                                                        <option>-- اختر حالة الطلبية --</option>
                                                        <option value="paid_full">مدفوعة بالكامل (مع الشحن)</option>
                                                        <option value="paid">مدفوعة</option>
                                                        <option value="unpaid">غير مدفوعة</option>
                                                        <option value="canceld">ملغية</option>
                                                        <option value="delivered">مستلمة</option>
                                                    </select>

                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName44343">كود
                                                        الرحلة</label>
                                                    <input type="text" name="trip_code" class="form-control"
                                                        id="exampleInputName44343" placeholder="كود الرحلة">
                                                </div>
                                            </div>
                                        @endif

                                        <div class="col-md-2">
                                            <div class="form-group pt-4">
                                                <label class="form-label" for="exampleInputName343"></label>
                                                <button type="submit" class="btn btn-primary p-3"><i
                                                        class="fas fa-search"></i></button>
                                            </div>
                                        </div>

                                        <div class="col-md-2">
                                            <div class="form-group pt-4">
                                                <label class="form-label" for="exampleInputName343"></label>
                                                @if ($page == 'all')
                                                    <a href="{{ route('order.index') }}" class="btn btn-primary p-3"><i
                                                            class="fas fa-redo"></i></a>
                                                @elseif($page == 'delivered')
                                                    <a href="{{ route('order.delivered.index') }}"
                                                        class="btn btn-primary p-3"><i class="fas fa-redo"></i></a>
                                                @elseif($page == 'notDelivered')
                                                    <a href="{{ route('order.notDelivered.index') }}"
                                                        class="btn btn-primary p-3"><i class="fas fa-redo"></i></a>
                                                @elseif($page == 'ready')
                                                    <a href="{{ route('order.ready.index') }}"
                                                        class="btn btn-primary p-3"><i class="fas fa-redo"></i></a>
                                                @elseif($page == 'unpaid')
                                                    <a href="{{ route('order.unpaid.index') }}"
                                                        class="btn btn-primary p-3"><i class="fas fa-redo"></i></a>
                                                @elseif($page == 'no_trip')
                                                    <a href="{{ route('order.no_trip.index') }}"
                                                        class="btn btn-primary p-3"><i class="fas fa-redo"></i></a>
                                                @endif
                                            </div>
                                        </div>

                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="collapse" id="collapseExample">
                            <div class="card-body">
                                <form action="{{ route('order.store') }}" method="post" enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="customer_select">الزبون</label>
                                                <br>
                                                <select name="customer_id" style="width: 100%;"
                                                    class="form-control js-example-basic-single" multiple="single"
                                                    id="customer_select" onchange="toggleCustomerFields()"
                                                    data-placeholder="اختر الزبون">
                                                    <option selected value="new">زبون جديد</option>
                                                    @foreach ($customers as $customer)
                                                        <option value="{{ $customer->id }}">{{ $customer->name }} -
                                                            {{ $customer->code }} - {{ $customer->phone_1 }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        {{-- <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="trip_select">الرحلة (إختياري)</label>
                                                <br>
                                                <select name="trip_id" style="width: 100%;"
                                                    class="form-control js-example-basic-single" multiple="single"
                                                    id="trip_select" data-placeholder="اختر الرحلة">
                                                    <option></option> <!-- Empty option for the placeholder -->
                                                    @foreach ($trips as $trip)
                                                        <option value="{{ $trip->id }}">{{ $trip->code }} -
                                                            {{ $trip->country }} - {{ $trip->out_date }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div> --}}

                                        <!-- New Customer Fields -->
                                        <div class="col-md-4 new_customer_fields">
                                            <div class="form-group">
                                                <label class="form-label" for="new_customer_name">اسم الزبون</label>
                                                <input type="text" name="name" class="form-control"
                                                    id="new_customer_name" placeholder="اسم الزبون"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال اسم الزبون')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-4 new_customer_fields">
                                            <div class="form-group">
                                                <label class="form-label" for="new_customer_phone_1">رقم الهاتف</label>
                                                <input type="text" name="phone_1" class="form-control"
                                                    id="new_customer_phone_1" placeholder="رقم الهاتف"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال رقم الهاتف')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>


                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="platform_select">المنصة </label>
                                                <select name="platform_id" required id="platform_select"
                                                    class="form-control">
                                                    <option value="">-- اختر المنصة --</option>
                                                    @foreach ($platforms as $platform)
                                                        <option value="{{ $platform->id }}">{{ $platform->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">الكمية</label>
                                                <input type="number" name="qty" class="form-control"
                                                    id="exampleInputName343" required placeholder="الكمية"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال الكمية')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>


                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">اجمالي سعر
                                                    البضاعة</label>
                                                <input type="number" step="any" name="total_price"
                                                    class="form-control" id="exampleInputName343" required
                                                    placeholder="اجمالي سعر البضاعة"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال اجمالي سعر البضاعة')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>


                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">القيمة
                                                    المدفوعة</label>
                                                <input type="number" step="any" value="0" name="paid_price"
                                                    class="form-control" id="exampleInputName343" required
                                                    placeholder="القيمة المدفوعة"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال القيمة المدفوعة')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>


                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="currency">العملة </label>
                                                <select name="currency" required id="currency" class="form-control">
                                                    <option>-- اختر العملة --</option>
                                                    <option selected value="dinar">دينار ليبي - د.ل</option>
                                                    <option value="dollar">دولار - $</option>
                                                    <option value="euro">يورو - €</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="payment_id">طريقة الدفع </label>
                                                <select name="payment_id" required id="payment_id" class="form-control">
                                                    <option>-- اختر طريقة الدفع --</option>
                                                    @foreach ($payments as $index => $payment)
                                                        @if ($payment->id == 1)
                                                            <option value="{{ $payment->id }}" selected>
                                                                {{ $payment->name }}</option>
                                                        @else
                                                            <option value="{{ $payment->id }}">{{ $payment->name }}
                                                            </option>
                                                        @endif
                                                    @endforeach

                                                </select>
                                            </div>
                                        </div>



                                        <div class="col-md-12 text-center mb-3">
                                            <button type="button" onclick="toggleMoreFields()"
                                                class="btn btn-secondary">
                                                إظهار المزيد من المعلومات
                                            </button>
                                        </div>

                                        <div id="moreFields" class="row" style="display: none;">

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="trip_details">بيانات الرحلة</label>
                                                    <input type="text" name="trip_details" class="form-control"
                                                        id="trip_details" placeholder="بيانات الرحلة">
                                                </div>
                                            </div>


                                            <div class="col-md-4 new_customer_fields">
                                                <div class="form-group">
                                                    <label class="form-label" for="new_customer_phone_2">رقم الهاتف 2
                                                        (إختياري)</label>
                                                    <input type="text" name="phone_2" class="form-control"
                                                        id="new_customer_phone_2" placeholder="رقم الهاتف 2 (إختياري)">
                                                </div>
                                            </div>

                                            <div class="col-md-4 new_customer_fields">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">كود الزبون
                                                        (إختياري)</label>
                                                    <input type="text" name="code" class="form-control"
                                                        id="exampleInputName343" placeholder="كود الزبون (إختياري)">
                                                </div>
                                            </div>

                                            <div class="col-md-4 new_customer_fields">
                                                <div class="form-group">
                                                    <label class="form-label" for="new_customer_id_number">العنوان
                                                        (إختياري)</label>
                                                    <input type="text" name="address" class="form-control"
                                                        id="new_customer_id_number" placeholder="العنوان (إختياري)">
                                                </div>
                                            </div>



                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="order_type">نوع الطرد</label>
                                                    <input type="text" name="order_type" class="form-control"
                                                        id="order_type" placeholder="نوع الطرد">
                                                </div>
                                            </div>


                                            {{-- <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">اجمالي سعر
                                                    التكلفة</label>
                                                <input type="number" step="any" name="cost_price"
                                                    class="form-control" id="exampleInputName343" required
                                                    placeholder="اجمالي سعر التكلفة"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال اجمالي سعر التكلفة')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div> --}}

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">عدد الطرود
                                                        (إختياري)</label>
                                                    <input type="number" name="bags_num" class="form-control"
                                                        id="exampleInputName343" placeholder="عدد الطرود">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">الكمية في الطرود
                                                        (إختياري)</label>
                                                    <input type="number" name="bags_qty" class="form-control"
                                                        id="exampleInputName343" placeholder="الكمية في الطرود">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">اجمالي سعر الشحن
                                                        (إختياري)</label>
                                                    <input type="number" step="any" value="0"
                                                        name="ship_price" class="form-control" id="exampleInputName343"
                                                        placeholder="سعر الشحن للكيلو">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">الوزن بالكيلو
                                                        (إختياري)</label>
                                                    <input type="number" step="any" name="kilo"
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="الوزن بالكيلو">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="storage_num">رقم التخزين
                                                        (إختياري)</label>
                                                    <input type="number" name="storage_num" class="form-control"
                                                        id="storage_num" placeholder="رقم التخزين">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName">ملاحطات
                                                        (إختياري)</label>
                                                    <textarea type="text" name="note" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                        placeholder="ملاحظات (إختياري)"></textarea>
                                                </div>
                                            </div>
                                        </div>


                                        <div id="addedItems">

                                        </div>
                                        <div class="col-md-12 d-flex justify-content-center">
                                            <div class="form-group">
                                                <button type="button" onclick="addItem()"
                                                    class="btn btn-light-info mb-4">اضافة المنتج</button>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary mb-4">حفظ الطلب</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>


                        <div class="collapse" id="collapseExampleAddRecipt">
                            <div class="card-body">
                                <form action="{{ route('order.store2') }}" method="post" enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="customer_select2">الزبون</label>
                                                <br>
                                                <select name="customer_id" style="width: 100%;"
                                                    class="form-control js-example-basic-single" multiple="single"
                                                    id="customer_select2" onchange="toggleCustomerFields2()"
                                                    data-placeholder="اختر الزبون">
                                                    <option selected value="new">زبون جديد</option>
                                                    @foreach ($customers as $customer)
                                                        <option value="{{ $customer->id }}">{{ $customer->name }} -
                                                            {{ $customer->code }} - {{ $customer->phone_1 }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <!-- New Customer Fields -->
                                        <div class="col-md-4 new_customer_fields2">
                                            <div class="form-group">
                                                <label class="form-label" for="new_customer_name">اسم الزبون</label>
                                                <input type="text" name="name" class="form-control"
                                                    id="new_customer_name" placeholder="اسم الزبون"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال اسم الزبون')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-4 new_customer_fields2">
                                            <div class="form-group">
                                                <label class="form-label" for="new_customer_phone_1">رقم الهاتف</label>
                                                <input type="text" name="phone_1" class="form-control"
                                                    id="new_customer_phone_1" placeholder="رقم الهاتف"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال رقم الهاتف')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                            <div class="col-md-4 new_customer_fields2">
                                                <div class="form-group">
                                                    <label class="form-label" for="new_customer_phone_2">رقم الهاتف 2
                                                        (إختياري)</label>
                                                    <input type="text" name="phone_2" class="form-control"
                                                        id="new_customer_phone_2" placeholder="رقم الهاتف 2 (إختياري)">
                                                </div>
                                            </div>

                                            <div class="col-md-4 new_customer_fields2">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">كود الزبون
                                                        (إختياري)</label>
                                                    <input type="text" name="code" class="form-control"
                                                        id="exampleInputName343" placeholder="كود الزبون (إختياري)">
                                                </div>
                                            </div>

                                            <div class="col-md-4 new_customer_fields2">
                                                <div class="form-group">
                                                    <label class="form-label" for="new_customer_id_number">العنوان
                                                        (إختياري)</label>
                                                    <input type="text" name="address" class="form-control"
                                                        id="new_customer_id_number" placeholder="العنوان (إختياري)">
                                                </div>
                                            </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="platform_select">المنصة </label>
                                                <select name="platform_id" required id="platform_select"
                                                    class="form-control">
                                                    <option value="">-- اختر المنصة --</option>
                                                    @foreach ($platforms as $platform)
                                                        <option value="{{ $platform->id }}">{{ $platform->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">اجمالي سعر
                                                    البضاعة</label>
                                                <input type="number" step="any" name="total_price"
                                                    class="form-control" id="exampleInputName343" required
                                                    placeholder="اجمالي سعر البضاعة"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال اجمالي سعر البضاعة')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">القيمة
                                                    المدفوعة</label>
                                                <input type="number" step="any" value="0" name="paid_price"
                                                    class="form-control" id="exampleInputName343" required
                                                    placeholder="القيمة المدفوعة"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال القيمة المدفوعة')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="currency">العملة </label>
                                                <select name="currency" required id="currency" class="form-control">
                                                    <option>-- اختر العملة --</option>
                                                    <option selected value="dinar">دينار ليبي - د.ل</option>
                                                    <option value="dollar">دولار - $</option>
                                                    <option value="euro">يورو - €</option>
                                                </select>
                                            </div>
                                        </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="trip_details">بيانات الرحلة</label>
                                                    <input type="text" name="trip_details" class="form-control"
                                                        id="trip_details" placeholder="بيانات الرحلة">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="order_type">نوع الطرد</label>
                                                    <input type="text" name="order_type" class="form-control"
                                                        id="order_type" placeholder="نوع الطرد">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">عدد الطرود
                                                        (إختياري)</label>
                                                    <input type="number" name="bags_num" class="form-control"
                                                        id="exampleInputName343" placeholder="عدد الطرود">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">الكمية في الطرود
                                                        (إختياري)</label>
                                                    <input type="number" name="bags_qty" class="form-control"
                                                        id="exampleInputName343" placeholder="الكمية في الطرود">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">اجمالي سعر الشحن
                                                        (إختياري)</label>
                                                    <input type="number" step="any" value="0"
                                                        name="ship_price" class="form-control" id="exampleInputName343"
                                                        placeholder="سعر الشحن للكيلو">
                                                </div>
                                            </div>


                                                                                        <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">اجمالي سعر الشحن بالدولار $
                                                        (إختياري)</label>
                                                    <input type="number" step="any" value="0"
                                                        name="ship_price_dollar" class="form-control" id="exampleInputName343"
                                                        placeholder="سعر الشحن بالدولار $">
                                                </div>
                                            </div>


                                                                                        <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">اجمالي سعر الشحن باليورو
                                                        (إختياري)</label>
                                                    <input type="number" step="any" value="0"
                                                        name="ship_price_euro" class="form-control" id="exampleInputName343"
                                                        placeholder="سعر الشحن باليورو €">
                                                </div>
                                            </div>


                                                                                        <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">اجمالي سعر الشحن بالمصرف
                                                        (إختياري)</label>
                                                    <input type="number" step="any" value="0"
                                                        name="ship_price_bank" class="form-control" id="exampleInputName343"
                                                        placeholder="سعر الشحن بالمصرف">
                                                </div>
                                            </div>


                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">الوزن بالكيلو
                                                        (إختياري)</label>
                                                    <input type="number" step="any" name="kilo"
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="الوزن بالكيلو">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName">ملاحطات
                                                        (إختياري)</label>
                                                    <textarea type="text" name="note" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                        placeholder="ملاحظات (إختياري)"></textarea>
                                                </div>
                                            </div>

                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary mb-4">حفظ طلب الايصال</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>


                        @include('admin.order.components.order')






                    </div>
                </div>
                <!-- Autofill table end -->

            </div>
            <!-- [ Main Content ] end -->
        </div>
    </section>



    <!-- Modal -->
    <div class="row" dir="rtl">
        <div class="col-12">
            <div class="modal fade printTable" tabindex="-1" role="dialog" aria-labelledby="orderdetailsModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="orderdetailsModalLabel">طـبـاعـة الـطـلـبـات </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <form action="{{ route('order.print.date') }}" target="_blank" method="GET">
                            @csrf
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="recipient-name"
                                                class="col-form-label">{{ __('messages.startDate') }}</label>
                                            <input type="date" name="startDate" placeholder="من التاريخ"
                                                class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="message-text"
                                                class="col-form-label">{{ __('messages.endDate') }}</label>
                                            <input type="date" name="endDate" placeholder="الي التاريخ"
                                                class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="message-text" class="col-form-label">حـالـة الـطـلـبـيـة</label>
                                            <select name="status" id="trip_select" class="form-control">
                                                <option value="">-- اختر حالة الطلبية --</option>
                                                <option value="paid_full">مدفوعة بالكامل (مع الشحن)</option>
                                                <option value="paid">مدفوعة</option>
                                                <option value="unpaid">غير مدفوعة</option>
                                                <option value="canceld">ملغية</option>
                                                <option value="delivered">مستلمة</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer d-flex justify-content-center">
                                    <button type="submit" class="btn btn-primary">طـبـاعـة</button>
                        </form>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغــلاق</button>
                    </div>
                </div>
                </form>
            </div>
        </div>
    </div>
    <!-- end modal -->





@endsection
@section('script')
    <!-- datatable Js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    {{-- <script src="../assets/js/plugins/apexcharts.min.js"></script>
 <script src="../assets/js/pages/dashboard-default.js"></script> --}}

    <script src="{{ asset('assets/js/plugins/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.autoFill.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/autoFill.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.keyTable.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/keyTable.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.select.min.js') }}"></script>
    <script src="{{ asset('assets\select2\js\select2.full.min.js') }}" charset="utf-8"></script>


    <script>
        // [ Autofill ]
        $('#autofill').DataTable({
            autoFill: true
        });
        // [ KeyTable Integration ]
        $('#key-integration').DataTable({
            keys: true,
            autoFill: true
        });
        // [ Column Selector ]
        $('#confirm-table').DataTable({
            autoFill: {
                alwaysAsk: true
            }
        });

        // [ scroll fill ]
        var safill = $('#scroll-fill').dataTable({
            scrollY: 400,
            scrollCollapse: true,
            paging: false,
            autoFill: true
        });
        $('#colum-select').DataTable({
            columnDefs: [{
                orderable: false,
                className: 'select-checkbox',
                targets: 0
            }],
            select: {
                style: 'os',
                selector: 'td:first-child'
            },
            order: [
                [1, 'asc']
            ],
            autoFill: {
                columns: ':not(:first-child)'
            }
        });

        $(document).ready(function() {
            $('.js-example-basic-single').select2({
                dir: "rtl",
                maximumSelectionLength: 1,
            });
            $('.js-example-basic-multiple').select2();

            $('#rtl-mode-switch').trigger('click');
        });


        function toggleCustomerFields() {
            var customerSelect = document.getElementById('customer_select');
            var newCustomerFields = document.getElementsByClassName('new_customer_fields');
            for (var i = 0; i < newCustomerFields.length; i++) {
                if (customerSelect.value === 'new') {
                    newCustomerFields[i].style.display = 'block';
                } else {
                    newCustomerFields[i].style.display = 'none';
                }
            }
        }

        function toggleCustomerFields2() {
            var customerSelect = document.getElementById('customer_select2');
            var newCustomerFields = document.getElementsByClassName('new_customer_fields2');
            for (var i = 0; i < newCustomerFields.length; i++) {
                if (customerSelect.value === 'new') {
                    newCustomerFields[i].style.display = 'block';
                } else {
                    newCustomerFields[i].style.display = 'none';
                }
            }
        }

        function calculateTotals() {
            let totalQty = 0;
            let totalPrice = 0;

            // جمع الكميات
            $('input[name="items[quantity][]"]').each(function() {
                let value = parseFloat($(this).val());
                if (!isNaN(value)) {
                    totalQty += value;
                }
            });

            // جمع الأسعار
            $('input[name="items[price][]"]').each(function() {
                let value = parseFloat($(this).val());
                if (!isNaN(value)) {
                    totalPrice += value;
                }
            });

            // وضع القيم في الحقول الرئيسية
            $('input[name="qty"]').val(totalQty);
            $('input[name="total_price"]').val(totalPrice.toFixed(2));
        }

        addItemCount = 0;

        function addItem() {
            addItemCount++;
            var itemHtml = `<div class="row itemRow">
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">اسم المنتج ${addItemCount}</label>
                <input type="text" name="items[name][]" required class="form-control" placeholder="اسم المنتج">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">الكمية</label>
                <input type="number" name="items[quantity][]" required
                    class="form-control item-qty"
                    placeholder="الكمية" oninput="calculateTotals()">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">إجمالي السعر</label>
                <input type="number" step="any"
                    name="items[price][]" required
                    class="form-control item-price"
                    placeholder="إجمالي السعر"
                    oninput="calculateTotals()">
            </div>
        </div>
        <div class="col-md-2 d-flex justify-content-end m-auto">
            <button type="button" onclick="removeItem(this)" class="btn btn-danger">حذف المنتج</button>
        </div>
    </div>`;

            $('#addedItems').append(itemHtml);
        }

        function removeItem(button) {
            $(button).closest('.itemRow').remove();
            calculateTotals(); // إعادة الحساب بعد الحذف
        }

        function toggleMoreFields() {
            let more = document.getElementById("moreFields");

            if (more.style.display === "none") {
                more.style.display = "flex"; // 👈 هذا الحل
            } else {
                more.style.display = "none";
            }
        }
    </script>
@endsection
