@extends('layout.master')

@section('title')
    <title> مصروفات الدفع </title>
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
                                <h2 class="mb-0">كل مصروفات الدفع </h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->



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
                                    <h6 class="mb-0">قيمة مصروفات الدفع اليومي</h6>
                                </div>
                            </div>
                            <div class="bg-body p-3 mt-3 rounded">
                                <div class="mt-3 row align-items-center">

                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-success"> المدفوعة </span>{{ $totalDay }} <span class="bg-light-primary">DLY</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"><span class="text-danger"> الغير مدفوعة </span> {{ $totalDay2 }} <span class="bg-light-primary">DLY</span>
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
                                    <h6 class="mb-0">قيمة مصروفات الدفع الشهري</h6>
                                </div>
                            </div>
                            <div class="bg-body p-3 mt-3 rounded">
                                <div class="mt-3 row align-items-center">

                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-success"> المدفوعة </span>{{ $totalMonth }} <span class="bg-light-info">DLY</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"><span class="text-danger"> الغير مدفوعة </span> {{ $totalMonth2 }} <span class="bg-light-info">DLY</span>
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
                                    <h6 class="mb-0">قيمة مصروفات الدفع السنوي </h6>
                                </div>
                            </div>
                            <div class="bg-body p-3 mt-3 rounded">
                                <div class="mt-3 row align-items-center">

                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-success"> المدفوعة </span>{{ $totalYear }} <span class="bg-light-secondary">DLY</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"><span class="text-danger"> الغير مدفوعة </span> {{ $totalYear2 }} <span class="bg-light-secondary">DLY</span>
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
                            @can('paymentExpenses-create')
                                <a class="btn btn-primary m-t-5" data-bs-toggle="collapse" href="#collapseExample"
                                    role="button" aria-expanded="false" aria-controls="collapseExample">اضافة مصروفات الدفع</a>
                            @endcan
                            <a class="btn btn-primary m-t-5" style="margin-right: 10px;" data-bs-toggle="collapse"
                                href="#collapseExample2" role="button" aria-expanded="false"
                                aria-controls="collapseExample2">الـبـحـث</a>

                                @can('paymentExpenses-list')
                                <a class="btn btn-secondary m-t-5" style="margin-right: 10px; float: left;" href=".printTable"
                                    data-bs-toggle="modal">طـبـاعـة</a>
                            @endcan

                        </div>

                        <div class="collapse" id="collapseExample2">
                            <div class="card-body">
                                <form action="{{ route('paymentExpenses.search') }}" method="get" enctype="multipart/form-data">
                                    @csrf

                                    <div class="row">
                                        @if (isset($query))


                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">من او الي كود الرحلة</label>
                                                    <input type="number" name="from_to"
                                                        @if (isset($query['from_to'])) value="{{ $query['from_to'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="من او الي كود الرحلة">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">تحديد المنصة </label>
                                                    <select name="platform_id" class="form-control">
                                                        <option>-- اختر المنصة --</option>
                                                        @foreach ($platforms as $platform)
                                                            @if (isset($query['platform_id']) && $query['platform_id'] == $platform->id)
                                                                <option value="{{ $platform->id }}">
                                                                    {{ $platform->name }}
                                                                @else
                                                                <option value="{{ $platform->id }}">
                                                                    {{ $platform->name }}
                                                            @endif
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">قيمة المصروفات</label>
                                                    <input type="number" step="any" name="cost"
                                                        @if (isset($query['cost'])) value="{{ $query['cost'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="قيمة المصروفات">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">تاريخ الصرف</label>
                                                    <input type="date" name="dte"
                                                        @if (isset($query['date'])) value="{{ $query['dte'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="تاريخ الصرف">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">ملاحظات
                                                        (إختياري)</label>
                                                    <input type="date" name="note"
                                                        @if (isset($query['note'])) value="{{ $query['note'] }}" @endif
                                                        class="form-control" id="exampleInputName343" placeholder="ملاحظات">
                                                </div>
                                            </div>
                                        @else


                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">من او الي كود الرحلة</label>
                                                <input type="number" name="from_to"
                                                    class="form-control" id="exampleInputName343"
                                                    placeholder="من او الي كود الرحلة">
                                            </div>
                                        </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">تحديد المنصة </label>
                                                    <select name="platform_id"class="form-control">
                                                        <option>-- اختر المنصة --</option>
                                                        @foreach ($platforms as $platform)
                                                            <option value="{{ $platform->id }}">
                                                                {{ $platform->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">قيمة
                                                        المشتريات</label>
                                                    <input type="number" step="any" name="cost"
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="قيمة المصروفات">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">تاريخ
                                                        الصرف</label>
                                                    <input type="date" name="date" class="form-control"
                                                        id="exampleInputName343" placeholder="تاريخ الصرف">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">ملاحظات</label>
                                                    <input type="text" name="note" class="form-control"
                                                        id="exampleInputName343" placeholder="ملاحظات">
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
                                                <a href="{{ route('paymentExpenses.index') }}" class="btn btn-primary p-3"><i
                                                        class="fas fa-redo"></i></a>
                                            </div>
                                        </div>

                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="collapse" id="collapseExample">
                            <div class="card-body">
                                <form action="{{ route('paymentExpenses.store') }}" method="post"
                                    enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">

                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">من كود الرحلة</label>
                                                <input type="number" name="from" class="form-control"
                                                    id="exampleInputName343" required placeholder="من كود الرحلة"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال من كود الرحلة')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">الي كود الرحلة</label>
                                                <input type="number" name="to" class="form-control"
                                                    id="exampleInputName343" required placeholder="الي كود الرحلة"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال الي كود الرحلة')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-6" id="desc_select_container">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">تحديد المنصة</label>
                                                <select name="platform_id" id="desc_select" class="form-control">
                                                    <option>-- اختر المنصة --</option>
                                                    @foreach ($platforms as $platform)
                                                        <option value="{{ $platform->id }}">{{ $platform->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-6" style="display: none;" id="new_desc">
                                            <div class="form-group">
                                                <label class="form-label" for="new_desc_name">منصة جديدة</label>
                                                <input type="text" name="name" class="form-control"
                                                    id="new_desc_name" placeholder="منصة جديدة">
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="newDescription"
                                                        onchange="toggleDescription()">
                                                    <label class="form-check-label" for="newDescription">منصة
                                                        جديدة</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">قيمة المصروفات</label>
                                                <input type="number" step="any" name="cost" class="form-control"
                                                    id="exampleInputName343" required placeholder="قيمة المصروفات"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال قيمة المصروفات')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">تاريخ
                                                    مصروفات الدفع</label>
                                                <input type="date" name="date" class="form-control"
                                                    id="exampleInputName343" required
                                                    placeholder="تاريخ  مصروفات الدفع"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال تاريخ  مصروفات الدفع')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName">ملاحطات (إختياري)</label>
                                                <textarea type="text" name="note" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                    placeholder="ملاحظات (إختياري)"></textarea>
                                            </div>
                                        </div>


                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="status"
                                                        id="invalidCheck">
                                                    <label class="form-check-label" for="invalidCheck">مـدفـوعـة</label>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary mb-4">حفظ مصروفات الدفع</button>
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
                                                            من والي الرحلة
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                        rowspan="1" colspan="1" style="width: 175.717px;"
                                                        aria-label="Product: activate to sort column ascending">
                                                        المنصة
                                                    </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            قيمة المصروفات
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                        rowspan="1" colspan="1" style="width: 175.717px;"
                                                        aria-label="Product: activate to sort column ascending">
                                                         حالة المصروفات
                                                    </th>


                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            تـاريـخ مصروفات الدفع
                                                        </th>


                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            ملاحظات
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 285.883px;"
                                                            aria-label="Position: activate to sort column ascending">
                                                            الاجراءات
                                                        </th>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                    @foreach ($expenses as $expense)
                                                        <tr class="odd">

                                                            <td class="">{{ $expense->from }} - {{ $expense->to }}</td>
                                                            <td class="">{{ $expense->platform->name }}</td>

                                                            <td class="">
                                                                <span style="color: green;">{{ $expense->cost }}
                                                                    DLY</span>
                                                            </td>

                                                            @if ($expense->status)
                                                                <td class="text-primary">مدفوعة</td>
                                                            @else
                                                                <td class="text-danger">غير مدفوعة</td>
                                                            @endif

                                                            <td class="">{{ $expense->date }}</td>

                                                            <td class="">{{ $expense->note }}</td>

                                                            <td>
                                                                @can('paymentExpenses-edit')
                                                                        <a href="{{ route('paymentExpenses.edit', $expense->id) }}"
                                                                            class="btn btn-light-primary">تعديل
                                                                        </a>
                                                                @endcan

                                                                @can('paymentExpenses-edit')
                                                                @if (!$expense->status)
                                                                <button type="button" class="btn btn-light-info"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#exampleModalLivePay{{ $expense->id }}">تم الدفع</button>
                                                                    <div id="exampleModalLivePay{{ $expense->id }}"
                                                                        class="modal fade" tabindex="-1" role="dialog"
                                                                        aria-labelledby="exampleModalLiveLabel"
                                                                        aria-hidden="true">
                                                                        <div class="modal-dialog modal-dialog-centered"
                                                                            role="document">
                                                                            <div class="modal-content">
                                                                                <div class="modal-body text-center">
                                                                                    <div>
                                                                                        <i
                                                                                            class="ti ti-alert-circle text-success fa-5x mb-3"></i>
                                                                                    </div>
                                                                                    <p style="font-size: 18px;">
                                                                                        هل انت متأكد من دفع المصروفات <br> للمنصة <span
                                                                                            class="text-success">{{ $expense->platform->name }}</span>
                                                                                        بقيمة <span
                                                                                            class="text-success">{{ $expense->cost }}</span>
                                                                                        <br>لايمكنك تعديل الطلبية بعد الدفع !</p>
                                                                                    <form
                                                                                        action="{{ route('paymentExpenses.pay', $expense->id) }}"
                                                                                        method="post">
                                                                                        @csrf
                                                                                </div>
                                                                                <div
                                                                                    class="modal-footer d-flex justify-content-center">
                                                                                    <button type="submit"
                                                                                        class="btn btn-success">نـعـم</button>
                                                                                    </form>
                                                                                    <button type="button"
                                                                                        class="btn btn-secondary"
                                                                                        data-bs-dismiss="modal">إغـلاق</button>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                            @endif
                                                            @endcan
                                                                @can('paymentExpenses-delete')
                                                                    <button type="button" class="btn btn-light-danger"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#exampleModalLiveDelete{{ $expense->id }}">حذف</button>

                                                                    <div id="exampleModalLiveDelete{{ $expense->id }}"
                                                                        class="modal fade" tabindex="-1" role="dialog"
                                                                        aria-labelledby="exampleModalLiveLabel"
                                                                        aria-hidden="true">
                                                                        <div class="modal-dialog modal-dialog-centered"
                                                                            role="document">
                                                                            <div class="modal-content">
                                                                                <div class="modal-body text-center">
                                                                                    <div>
                                                                                        <i
                                                                                            class="ti ti-alert-circle text-danger fa-5x mb-3"></i>
                                                                                    </div>
                                                                                    <p style="font-size: 18px;">
                                                                                        هل انت متأكد من حذف مصروفات الدفع <br> للمنصة <span
                                                                                            class="text-danger">{{ $expense->platform->name }}</span>
                                                                                        بقيمة <span
                                                                                            class="text-danger">{{ $expense->cost }}</span>
                                                                                        ؟</p>
                                                                                    <form
                                                                                        action="{{ route('paymentExpenses.destroy', $expense->id) }}"
                                                                                        method="post">
                                                                                        @csrf
                                                                                        @method('DELETE')
                                                                                </div>
                                                                                <div
                                                                                    class="modal-footer d-flex justify-content-center">
                                                                                    <button type="submit"
                                                                                        class="btn btn-danger">نـعـم</button>
                                                                                    </form>
                                                                                    <button type="button"
                                                                                        class="btn btn-secondary"
                                                                                        data-bs-dismiss="modal">إغـلاق</button>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                @endcan

                                                            </td>

                                                        </tr>
                                                    @endforeach

                                                </tbody>

                                                <tfoot>

                                                    <tr>
                                                        <th rowspan="1" colspan="1">من والي الرحلة</th>
                                                        <th rowspan="1" colspan="1">المنصة</th>
                                                        <th rowspan="1" colspan="1">قيمة المصروفات</th>
                                                        <th rowspan="1" colspan="1">حالة المصروفات</th>
                                                        <th rowspan="1" colspan="1">تـاريـخ مصروفات الدفع</th>
                                                        <th rowspan="1" colspan="1">ملاحظات</th>
                                                        <th rowspan="1" colspan="1">الاجراءات</th>
                                                    </tr>

                                                </tfoot>
                                            </table>

                                            @if (isset($query))
                                                <span
                                                    id="paginate">{{ $expenses->appends($query)->links('pagination::bootstrap-4') }}</span>
                                            @else
                                                <span
                                                    id="paginate">{{ $expenses->links('pagination::bootstrap-4') }}</span>
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
                            <h5 class="modal-title" id="orderdetailsModalLabel">طـبـاعـة مـصـروفـات الـدفـع </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <form action="{{ route('paymentExpenses.print') }}" target="_blank" method="GET">
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
                                            <label for="message-text" class="col-form-label">الـمـنـصـة</label>
                                            <select name="platform_id" id="desc_select" class="form-control">
                                                <option value="">-- اختر المنصة --</option>
                                                @foreach ($platforms as $platform)
                                                    <option value="{{ $platform->id }}">{{ $platform->name }}
                                                    </option>
                                                @endforeach
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

        function toggleDescription() {
            const newDescriptionCheckbox = document.getElementById('newDescription');
            const newDescDiv = document.getElementById('new_desc');
            const newDescInpute = document.getElementById('new_desc_name');
            const descSelectContainer = document.getElementById('desc_select_container');
            const descSelect = document.getElementById('desc_select');
            if (newDescriptionCheckbox.checked) {
                newDescDiv.style.display = 'block';
                descSelectContainer.style.display = 'none';
                descSelect.selectedIndex = 0;
            } else {
                newDescDiv.style.display = 'none';
                descSelectContainer.style.display = 'block';
                newDescInpute.value = "";
            }
        }

    </script>
@endsection
