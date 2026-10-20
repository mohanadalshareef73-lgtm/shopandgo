@extends('layout.master')

@section('title')
    <title> ديون الزبون </title>
@endsection

@section('style')
    <!-- data tables css -->
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/dataTables.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/select.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/autoFill.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/keyTable.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets\select2\css\select2.min.css') }}">

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
                                    <h2 class="mb-0">ديــون الـزبـون <span class="text-primary">{{ $cus->name }}</span> </h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->



            @if ($page == "all")
            @if (isset($totalDay) && isset($totalMonth) && isset($totalYear) && isset($totalDay2) && isset($totalMonth2) && isset($totalYear2))
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
                                    <h6 class="mb-0">قيمة الديون اليومي</h6>
                                </div>
                            </div>
                            <div class="bg-body p-3 mt-3 rounded">
                                <div class="mt-3 row align-items-center">

                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-primary"> الاجمالي </span>{{ $totalDay }} <span class="bg-light-primary">DLY</span>
                                        </h5>
                                    </div>

                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-success"> المسددة </span>{{ $totalDay2 }} <span class="bg-light-primary">DLY</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"><span class="text-danger"> غير مسددة </span> {{ $totalDay - $totalDay2 }} <span class="bg-light-primary">DLY</span>
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
                                    <h6 class="mb-0">قيمة الديون الشهري</h6>
                                </div>
                            </div>
                            <div class="bg-body p-3 mt-3 rounded">
                                <div class="mt-3 row align-items-center">

                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-primary"> الاجمالي </span>{{ $totalMonth }} <span class="bg-light-info">DLY</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-success"> المسددة </span>{{ $totalMonth2 }} <span class="bg-light-info">DLY</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"><span class="text-danger"> غير مسددة </span> {{ $totalMonth - $totalMonth2 }} <span class="bg-light-info">DLY</span>
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
                                    <h6 class="mb-0">قيمة الديون السنوي </h6>
                                </div>
                            </div>
                            <div class="bg-body p-3 mt-3 rounded">
                                <div class="mt-3 row align-items-center">

                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-primary"> الاجمالي </span>{{ $totalYear }} <span class="bg-light-secondary">DLY</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-success"> المسددة </span>{{ $totalYear2 }} <span class="bg-light-secondary">DLY</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"><span class="text-danger"> غير مسددة </span> {{ $totalYear - $totalYear2 }} <span class="bg-light-secondary">DLY</span>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @elseif($page == "paid")
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
                                    <h6 class="mb-0">قيمة الديون المسددة</h6>
                                </div>
                            </div>
                            <div class="bg-body p-3 mt-3 rounded">
                                <div class="mt-3 row align-items-center">
                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-primary"> الاجمالي </span>{{ $total }} <span class="bg-light-primary">DLY</span>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @elseif($page == "depts")
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
                                    <h6 class="mb-0">قيمة الديون الغير مسددة</h6>
                                </div>
                            </div>
                            <div class="bg-body p-3 mt-3 rounded">
                                <div class="mt-3 row align-items-center">
                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-primary"> الاجمالي </span>{{ $total }} <span class="bg-light-primary">DLY</span>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif




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
                            @can('dept-create')
                                <a class="btn btn-primary m-t-5" data-bs-toggle="collapse" href="#collapseExample"
                                    role="button" aria-expanded="false" aria-controls="collapseExample">اضافة دين جديد</a>
                            @endcan
                            <a class="btn btn-primary m-t-5" style="margin-right: 10px;" data-bs-toggle="collapse"
                                href="#collapseExample2" role="button" aria-expanded="false"
                                aria-controls="collapseExample2">الـبـحـث</a>


                                @can('dept-list')
                                <a class="btn btn-secondary m-t-5" style="margin-right: 10px; float: left;" href=".printTable"
                                    data-bs-toggle="modal">طـبـاعـة</a>
                            @endcan

                        </div>


                        <div class="collapse" id="collapseExample2">
                            <div class="card-body">
                                <form action="{{ route('dept.search') }}" method="get" enctype="multipart/form-data">
                                    @csrf

                                    <div class="row">
                                        @if (isset($query))
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">اسم الزبون او الجهة
                                                    </label>
                                                    <input type="text" name="name"
                                                        @if (isset($query['name'])) value="{{ $query['name'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="اسم الزبون او الجهة ">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">من التاريخ </label>
                                                    <input type="date" name="from"
                                                        @if (isset($query['from'])) value="{{ $query['from'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="من التاريخ  ">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">الي التاريخ </label>
                                                    <input type="date" name="to"
                                                        @if (isset($query['to'])) value="{{ $query['to'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="الي التاريخ">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">حالة الدين</label>
                                                    <select name="status" id="trip_select" class="form-control">
                                                        <option value="">-- اختر حالة الدين --</option>
                                                        @if (isset($query['status']) && $query['status'] == 'paid')
                                                            <option selected value="paid">تم التسديد</option>
                                                            <option value="dept">غير مسددة</option>
                                                        @elseif (isset($query['status']) && $query['status'] == 'dept')
                                                            <option value="dept">تم التسديد</option>
                                                            <option selected value="dept">غير مسددة</option>
                                                        @else
                                                            <option value="paid">تم التسديد</option>
                                                            <option value="dept">غير مسددة</option>
                                                        @endif
                                                    </select>
                                                </div>
                                            </div>
                                        @else
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">اسم الزبون او الجهة
                                                    </label>
                                                    <input type="text" name="name" class="form-control"
                                                        id="exampleInputName343" placeholder="اسم الزبون او الجهة ">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">من التاريخ </label>
                                                    <input type="date" name="from" class="form-control"
                                                        id="exampleInputName343" placeholder="من التاريخ  ">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">الي التاريخ
                                                    </label>
                                                    <input type="date" name="to" class="form-control"
                                                        id="exampleInputName343" placeholder="الي التاريخ">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">حالة الدين</label>
                                                    <select name="status" id="trip_select" class="form-control">
                                                        <option value="">-- اختر حالة الدين --</option>
                                                        <option value="paid">تم التسديد</option>
                                                        <option value="dept">غير مسددة</option>
                                                    </select>
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
                                                <a href="{{ route('dept.index') }}" class="btn btn-primary p-3"><i
                                                    class="fas fa-redo"></i></a>
                                            @elseif($page == 'depts')
                                            <a href="{{ route('dept.unpaidDepts') }}" class="btn btn-primary p-3"><i
                                                class="fas fa-redo"></i></a>
                                            @elseif($page == 'paid')
                                            <a href="{{ route('dept.paidDept') }}" class="btn btn-primary p-3"><i
                                                class="fas fa-redo"></i></a>
                                            @elseif($page == 'search')
                                            <a href="{{ route('dept.index') }}" class="btn btn-primary p-3"><i
                                                class="fas fa-redo"></i></a>
                                            @endif

                                            </div>
                                        </div>

                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="collapse" id="collapseExample">
                            <div class="card-body">
                                <form action="{{ route('dept.store') }}" method="post" enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">

<div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="customer_select">الزبون</label>
                                                <br>
                                                <select name="customer_id" style="width: 100%;" required
                                                    oninvalid="this.setCustomValidity('الرجاء تحديد الزبون او زبون جديد ان كان جديد')"
                                                    oninput="this.setCustomValidity('')"
                                                    class="form-control js-example-basic-single" multiple="single"
                                                    id="customer_select" onchange="toggleCustomerFields()"
                                                    data-placeholder="اختر الزبون">
                                                    <option selected value="new">زبون جديد</option>
                                                    @foreach ($customers as $customer)
                                                    @if ($customer->id == $cus->id)
                                                        <option value="{{ $customer->id }}" selected>{{ $customer->name }} -
                                                            {{ $customer->code }} - {{ $customer->phone_1 }}</option>
                                                    @else
                                                        <option value="{{ $customer->id }}">{{ $customer->name }} -
                                                            {{ $customer->code }} - {{ $customer->phone_1 }}</option>
                                                    @endif
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
                                                <label class="form-label" for="exampleInputName343">اسم الجهة
                                                    (إختياري)</label>
                                                <input type="text" name="org_name" class="form-control"
                                                    id="exampleInputName343" placeholder="اسم الجهة (إختياري)">
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">قيمة الدين</label>
                                                <input type="number" step="any" name="total" class="form-control"
                                                    id="exampleInputName343" required placeholder="قيمة الدين"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال قيمة الدين')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">تاريخ
                                                    الديون</label>
                                                <input type="date" name="date" class="form-control"
                                                    id="exampleInputName343" required placeholder="تاريخ  الديون"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال تاريخ  الديون')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName">ملاحطات (إختياري)</label>
                                                <textarea type="text" name="note" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                    placeholder="ملاحظات (إختياري)"></textarea>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary mb-4">حفظ الدين</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="dt-responsive table-responsive">
                                <div id="autofill_wrapper" class="dataTables_wrapper dt-bootstrap5">
                                    <div class="row dt-row">
                                        <div class="col-sm-12">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Name: activate to sort column ascending">
                                                            اسم الدائن
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            القيمة
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            القيمة المسددة
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            حالة الدين
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            التاريخ
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            ملاحظات
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            المنشئ
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 285.883px;"
                                                            aria-label="Position: activate to sort column ascending">
                                                            الاجراءات
                                                        </th>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                    @foreach ($depts as $dept)
                                                        <tr class="odd">

                                                            @if ($dept->customer)
                                                                <td class="">{{ $dept->customer->name }}</td>
                                                            @else
                                                                <td class="">{{ $dept->org }}</td>
                                                            @endif

                                                            <td class="" style="color: green;">
                                                                {{ $dept->total }}
                                                                DLY
                                                                @if ($dept->total - $dept->paids->sum('paid_price') > 0)
                                                            <br><span class="text-warning"> القيمة المتبقية {{ $dept->total - $dept->paids->sum('paid_price') }}</span></td>
                                                                @endif

                                                            <td class="" style="color: green;">
                                                                {{ $dept->paids->sum('paid_price') }}
                                                                DLY</td>

                                                                @if ($dept->status == 'paid')
                                                            <td class="text-success">تم التسديد</td>
                                                        @elseif ($dept->status == 'dept')
                                                            <td class="text-danger">غير مسددة</td>
                                                    @endif

                                                    <td class="">{{ $dept->date }}</td>
                                                    <td class="">{{ $dept->note }}</td>

                                                    <td class="">{{ $dept->createdUser->name }}</td>

                                                    <td>
                                                    @can('dept-edit')
                                                        <a href="{{ route('dept.edit', $dept->id) }}"
                                                            class="btn btn-light-primary">تعديل
                                                        </a>
                                                    @endcan

                                                    @can('dept-delete')
                                                        @if ($dept->status == 'dept' && $dept->paids->sum('paid_price') < $dept->total)
                                                            <button type="button" class="btn btn-light-info"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#exampleModalLivePatRest{{ $dept->id }}">دفع
                                                                الباقي</button>
                                                            <div id="exampleModalLivePatRest{{ $dept->id }}"
                                                                class="modal fade" tabindex="-1" role="dialog"
                                                                aria-labelledby="exampleModalLiveLabel" aria-hidden="true">
                                                                <div class="modal-dialog modal-dialog-centered"
                                                                    role="document">
                                                                    <div class="modal-content">
                                                                        <div class="modal-body text-center">
                                                                            <div class="container">
                                                                                <div>
                                                                                    <i
                                                                                        class="ti ti-alert-circle text-primary fa-5x mb-3"></i>
                                                                                </div>

                                                                                <p
                                                                                        style="font-size: 18px; word-wrap: break-word;">
                                                                                        اجمالي القيمة المتبقية من
                                                                                        الدين
                                                                                        @if ($dept->customer)
                                                                                        <span class="text-primary">{{ $dept->customer->name }}</span><br>
                                                                                    @else
                                                                                        <span class="text-primary">{{ $dept->org }}</span><br>
                                                                                    @endif
                                                                                        هي <span
                                                                                            class="text-success">{{ $dept->total-$dept->paids->sum('paid_price') }}
                                                                                            DLY</span></p>

                                                                                <form
                                                                                    action="{{ route('dept.payRest', $dept->id) }}"
                                                                                    method="post">
                                                                                    @csrf
                                                                                    <div class="col-md-12">
                                                                                        <div class="form-group">
                                                                                            <label class="form-label"
                                                                                                for="exampleInputName232">القيمة
                                                                                                التي تريد تسديدها</label>
                                                                                                <input type="number"
                                                                                                    step="any"
                                                                                                    min="1"
                                                                                                    max="{{ $dept->total-$dept->paids->sum('paid_price') }}"
                                                                                                    name="paid_price"
                                                                                                    class="form-control"
                                                                                                    id="exampleInputName232"required
                                                                                                    placeholder="القيمة المسددة"
                                                                                                    oninvalid="this.setCustomValidity('الرجاء ادخال القيمة المسددة')"
                                                                                                    oninput="this.setCustomValidity('')">
                                                                                        </div>
                                                                                    </div>

                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label class="form-label" for="platform_select">طريقة التسديد </label>
                                                <select name="payment_id" required id="platform_select"
                                                    class="form-control">
                                                    <option value="">-- اختر طريقة التسديد --</option>
                                                    @foreach (auth()->user()->payments() as $payment)
                                                    @if ($payment->id == 1)
                                                        <option selected value="{{ $payment->id }}">{{ $payment->name }}</option>

                                                        @else
                                                        <option value="{{ $payment->id }}">{{ $payment->name }}</option>

                                                    @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label class="form-label" for="currency">العملة </label>
                                                <select name="currency" required id="currency" class="form-control">
                                                    <option value="">-- اختر العملة --</option>
                                                    <option selected value="dinar">دينار ليبي - د.ل</option>
                                                    <option value="dollar">دولار - $</option>
                                                    <option value="euro">يورو - €</option>
                                                </select>
                                            </div>
                                        </div>


                                                                                    <div
                                                                                        class="modal-footer d-flex justify-content-center">
                                                                                        <button type="submit"
                                                                                            class="btn btn-primary">نـعـم</button>
                                                                                        <button type="button"
                                                                                            class="btn btn-secondary"
                                                                                            data-bs-dismiss="modal">إغـلاق</button>
                                                                                    </div>
                                                                                </form>
                                                                            </div>

                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @endcan

                                                    @can('dept-delete')
                                                        @if ($dept->paids->count() > 0)
                                                            <a href="{{ route('dept.paidhistory', $dept->id) }}"
                                                                class="btn btn-light-warning">سجل التسديدات
                                                            </a>
                                                        @endif
                                                    @endcan

                                                    @can('dept-edit')
                                                    @if ($dept->status == 'dept' && $dept->paids->sum('paid_price') < $dept->total)
                                                        <button type="button" class="btn btn-light-danger"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#exampleModalLiveDelete{{ $dept->id }}">حذف</button>

                                                        <div id="exampleModalLiveDelete{{ $dept->id }}"
                                                            class="modal fade" tabindex="-1" role="dialog"
                                                            aria-labelledby="exampleModalLiveLabel" aria-hidden="true">
                                                            <div class="modal-dialog modal-dialog-centered" role="document">
                                                                <div class="modal-content">
                                                                    <div class="modal-body text-center">
                                                                        <div>
                                                                            <i
                                                                                class="ti ti-alert-circle text-danger fa-5x mb-3"></i>
                                                                        </div>
                                                                        <p style="font-size: 18px;">
                                                                            هل انت متأكد من حذف الدين
                                                                            @if ($dept->customer)
                                                                                <span
                                                                                    class="text-danger">{{ $dept->customer->name }}</span>
                                                                            @else
                                                                                <span class="text-danger">{{ $dept->org }}</span>
                                                                            @endif
                                                                            بقيمة <span class="text-danger">{{ $dept->total }}</span> !
                                                                        </p>
                                                                        <form
                                                                            action="{{ route('dept_delete.destroy', $dept->id) }}"
                                                                            method="post">
                                                                            @csrf
                                                                            @method('DELETE')
                                                                    </div>
                                                                    <div class="modal-footer d-flex justify-content-center">
                                                                        <button type="submit"
                                                                            class="btn btn-danger">نـعـم</button>
                                                                        </form>
                                                                        <button type="button" class="btn btn-secondary"
                                                                            data-bs-dismiss="modal">إغـلاق</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        @endif
                                                    @endcan

                                                    </td>

                                                    </tr>
                                                    @endforeach

                                                </tbody>

                                                <tfoot>

                                                    <tr>
                                                        <th rowspan="1" colspan="1">اسم الدائن</th>
                                                        <th rowspan="1" colspan="1">القيمة</th>
                                                        <th rowspan="1" colspan="1">القيمة المسددة</th>
                                                        <th rowspan="1" colspan="1">حالة الدين</th>
                                                        <th rowspan="1" colspan="1">التاريخ</th>
                                                        <th rowspan="1" colspan="1">ملاحظات</th>
                                                        <th rowspan="1" colspan="1">المنشئ</th>
                                                        <th rowspan="1" colspan="1">الاجراءات</th>
                                                    </tr>


                                                </tfoot>
                                            </table>

                                            @if (isset($query))
                                                <span
                                                    id="paginate">{{ $depts->appends($query)->links('pagination::bootstrap-4') }}</span>
                                            @else
                                                <span id="paginate">{{ $depts->links('pagination::bootstrap-4') }}</span>
                                            @endif
                                        </div>
                                    </div>

                                </div>

                            </div>
                        </div>
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
                            <h5 class="modal-title" id="orderdetailsModalLabel">طـبـاعـة الـديـون </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <form action="{{ route('dept.print') }}" target="_blank" method="GET">
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
                                            <label for="message-text" class="col-form-label">حـالـة الـديـون</label>
                                            <select name="status" class="form-control">
                                                <option value="">-- اختر حالة الديون --</option>
                                                <option value="paid">الـمـسـددة</option>
                                                <option value="dept">الـغـيـر مـسـددة</option>
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



    <script>
        function redirectToAd(route) {
            // Redirect to the specified route
            window.location.href = route;
        }
    </script>
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

$(document).ready(function() {
            $('.js-example-basic-single').select2({
                dir: "rtl",
                maximumSelectionLength: 1,
            });
            $('.js-example-basic-multiple').select2();

            document.getElementById("rtl-mode-switch").trigger('click');
        });

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
    </script>
@endsection
