@extends('layout.master')

@section('title')
    <title>أربـــاح </title>
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
                                <li class="breadcrumb-item"><a href="javascript: void(0)">أربـــاح</a></li>
                                <li class="breadcrumb-item" aria-current="page">القائمة</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">أربـــاح</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->


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
                                        <h5 class="mb-1"> <span class="text-primary"> الاجمالي </span>{{ $totalDay }} <span class="bg-light-primary">د.ل</span>
                                        </h5>
                                    </div>

                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-success"> المسددة </span>{{ $totalDayDollar }} <span class="bg-light-primary">$</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"><span class="text-danger"> غير مسددة </span> {{ $totalDayEuro }} <span class="bg-light-primary">€</span>
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
                                        <h5 class="mb-1"> <span class="text-primary"> الاجمالي </span>{{ $totalMonth }} <span class="bg-light-info">د.ل</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-success"> الاجمالي </span>{{ $totalMonthDollar }} <span class="bg-light-info">$</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"><span class="text-danger"> الاجمالي </span> {{ $totalMonthEuro }} <span class="bg-light-info">€</span>
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
                                        <h5 class="mb-1"> <span class="text-primary"> الاجمالي </span>{{ $totalYear }} <span class="bg-light-secondary">د.ل</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-success"> الاجمالي </span>{{ $totalYearDollar }} <span class="bg-light-secondary">$</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"><span class="text-danger"> الاجمالي </span> {{ $totalYearEuro }} <span class="bg-light-secondary">€</span>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>



            @include('layout.validation-messages')

            <!-- [ Main Content ] start -->
            <div class="row">
                <!-- Autofill table start -->
                <div class="col-sm-12">
                    <div class="card">
                        {{-- <div class="card-header">
                        <a href="{{ route('ad.create') }}" class=" btn btn-outline-success">{{ __('messages.ad.add') }}</a>
                    </div> --}}


                        <div class="card-header d-flex justify-content-between">

                                                        <div>
                                @can('profits-create')
                                    <a class="btn btn-primary m-t-5" data-bs-toggle="collapse" href="#collapseExample"
                                        role="button" aria-expanded="false" aria-controls="collapseExample">
                                        اضافة أربـاح جديدة
                                    </a>
                                @endcan
                            </div>


                            <div>
                                @can('profits-list')
                                    <a class="btn btn-primary m-t-5" data-bs-toggle="collapse" href="#collapseExamplesearch"
                                        role="button" aria-expanded="false" aria-controls="collapseExamplesearch">
                                        الـبـحـث
                                    </a>
                                @endcan
                            </div>

                        </div>



                        @can('profits-list')
                            <div class="collapse" id="collapseExamplesearch">
                                <div class="card-body">
                                    <form action="{{ route('profits.search') }}" method="get"
                                        enctype="multipart/form-data">
                                        @csrf
                                        <div class="row">
                                            @if (isset($query))
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="form-label" for="exampleInputName">من تاريخ</label>
                                                        <input type="date" name="fromDate"
                                                            class="form-control" id="exampleInputName"
                                                            @if (isset($query['fromDate'])) value="{{ $query['fromDate'] }}" @endif
                                                            aria-describedby="nameHelp" placeholder="البحث هنا...">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="form-label" for="exampleInputName">الي تاريخ</label>
                                                        <input type="date" name="toDate" class="form-control"
                                                            id="exampleInputName"
                                                            @if (isset($query['toDate'])) value="{{ $query['toDate'] }}" @endif aria-describedby="nameHelp" placeholder="البحث هنا...">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="form-label" for="exampleInputName">المنصة</label>
                                                <select name="platform_id" id="platform_select"
                                                    class="form-control">
                                                    <option disabled>-- اختر المنصة --</option>
                                                    @foreach ($platforms as $platform)
                                                        <option value="{{ $platform->id }}" @if (isset($query['platform_id']) && $query['platform_id'] == $platform->id) selected @endif >{{ $platform->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="form-label" for="exampleInputName">من تاريخ</label>
                                                        <input type="date" name="fromDate" value="{{ old('fromDate') }}"
                                                            class="form-control" id="exampleInputName"
                                                            aria-describedby="nameHelp" placeholder="البحث هنا...">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="form-label" for="exampleInputName">الي تاريخ</label>
                                                        <input type="date" name="toDate" value="{{ old('toDate') }}" class="form-control"
                                                            id="exampleInputName" aria-describedby="nameHelp" placeholder="البحث هنا...">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="form-label" for="exampleInputName">المنصة</label>
                                                <select name="platform_id" id="platform_select"
                                                    class="form-control">
                                                    <option disabled>-- اختر المنصة --</option>
                                                    @foreach ($platforms as $platform)
                                                        <option value="{{ $platform->id }}" @if (old('toDate') && old('toDate') == $platform->id) selected @endif >{{ $platform->name }}
                                                        </option>
                                                    @endforeach
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
                                                    <a href="{{ route('profits.index') }}"
                                                        class="btn btn-primary p-3"><i class="fas fa-redo"></i></a>
                                                </div>
                                            </div>

                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endcan


                        @can('profits-create')
                            <div class="collapse" id="collapseExample">
                                <div class="card-body">
                                    <form action="{{ route('profits.store') }}" method="post"
                                        enctype="multipart/form-data">
                                        @csrf
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName">قيمة الارباح</label>
                                                    <input type="number" step="any" name="cost"
                                                        value="{{ old('cost') }}" class="form-control"
                                                        id="exampleInputName" required aria-describedby="nameHelp"
                                                        placeholder="قيمة الارباح"
                                                        oninvalid="this.setCustomValidity('الرجاء ادخال قيمة الارباح')"
                                                        oninput="this.setCustomValidity('')">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName">تاريخ الارباح</label>
                                                    <input type="date" name="date"
                                                        value="{{ old('date') }}" class="form-control"
                                                        id="exampleInputName" required aria-describedby="nameHelp"
                                                        placeholder="تاريخ الارباح"
                                                        oninvalid="this.setCustomValidity('الرجاء ادخال تاريخ الارباح')"
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
                                        <div class="col-md-6">
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

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInp2utName">ملاحظات
                                                        (إختياري)</label>
                                                    <textarea name="notes" class="form-control" id="exampleInp2utName" placeholder="ملاحظات (إختياري)">{{ old('notes') }}</textarea>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <button type="submit" class="btn btn-primary mb-4">حفظ أربـاح
                                                    الجديدة</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endcan


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
                                                            #
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                        قيمة الارباح
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Status: activate to sort column ascending">
                                                            المنصة
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Status: activate to sort column ascending">
                                                            التاريخ
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Status: activate to sort column ascending">
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
                                                    @foreach ($profits as $index => $payment)
                                                        <tr class="odd">

                                                            <td class="">{{ ++$index }}</td>
                                                            <td class="text-success"> {{ $payment->cost }} {{ $payment->currency == 'dinar' ? 'د.ل' : ($payment->currency == 'dollar' ? '$' : '€') }} </td>
                                                            </td>
                                                            <td class="">{{ $payment->platform->name }}
                                                            </td>
                                                            <td class="">{{ $payment->date }}</td>

                                                            <td class="">{{ $payment->notes }} </td>

                                                                <td>


                                                                    @can('profits-edit')

                                                                    <a href="{{ route('profits.edit', $payment->id) }}"
                                                                        class="btn btn-light-primary">تعديل
                                                                    </a>
                                                            @endcan


                                                                @can('profits-delete')
                                                                    <button type="button" class="btn btn-light-danger"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#exampleModalLiveDelete{{ $payment->id }}">حذف</button>

                                                                    <div id="exampleModalLiveDelete{{ $payment->id }}"
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
                                                                                        هل انت متأكد من حذف الاربـاح <span
                                                                                            class="text-danger">{{ $payment->platform->name }}</span>
                                                                                        ؟</p>
                                                                                    <form
                                                                                        action="{{ route('profits.destroy', $payment->id) }}"
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
                                                        <th rowspan="1" colspan="1">#</th>
                                                        <th rowspan="1" colspan="1">قيمة الارباح</th>
                                                        <th rowspan="1" colspan="1">المنصة</th>
                                                        <th rowspan="1" colspan="1">التاريخ</th>
                                                        <th rowspan="1" colspan="1">ملاحظات</th>
                                                        <th rowspan="1" colspan="1">الاجراءات
                                                        </th>
                                                    </tr>

                                                </tfoot>
                                            </table>
                                            @if (isset($query))
                                                <span
                                                    id="paginate">{{ $profits->appends($query)->links('pagination::bootstrap-4') }}</span>
                                            @else
                                                <span
                                                    id="paginate">{{ $profits->links('pagination::bootstrap-4') }}</span>
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

    <script>
        // [ Autofill ]
        $('#autofill').DataTable({
            autoFill: true
        });
        // [ KeyTable Integpaymentn ]
        $('#key-integpaymentn').DataTable({
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
    </script>
@endsection
