@extends('layout.master')

@section('title')
    <title> الزبائن </title>
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
                                <h2 class="mb-0">الزبائن </h2>
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
                        {{-- <div class="card-header">
                        <a href="{{ route('ad.create') }}" class=" btn btn-outline-success">{{ __('messages.ad.add') }}</a>
                    </div> --}}
                        <div class="card-header">

                            @can('customer-create')
                                <a class="btn btn-primary m-t-5" data-bs-toggle="collapse" href="#collapseExample"
                                    role="button" aria-expanded="false" aria-controls="collapseExample">اضافة زبون جديد</a>
                            @endcan
                            <a class="btn btn-primary m-t-5" style="margin-right: 10px;" data-bs-toggle="collapse"
                                href="#collapseExample2" role="button" aria-expanded="false"
                                aria-controls="collapseExample2">الـبـحـث</a>

                            <a class="btn btn-info m-t-5" style="margin-right: 10px;" href=".submissionUser"
                                data-bs-toggle="modal">طـبـاعـة</a>


                            <a class="btn btn-success m-t-5" style="margin-right: 10px;" href="{{ route('export.customers') }}">تصدير ملف اكسل</a>

                        </div>

                        <div class="collapse" id="collapseExample2">
                            <div class="card-body">
                                <form action="{{ route('customer.search') }}" method="get" enctype="multipart/form-data">
                                    @csrf

                                    <div class="row">
                                        @if (isset($query))
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">اسم او كود الزبون
                                                    </label>
                                                    <input type="text" name="name"
                                                        @if (isset($query['name'])) value="{{ $query['name'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="اسم او كود الزبون ">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">رقم الهاتف</label>
                                                    <input type="text" name="phone_1"
                                                        @if (isset($query['phone_1'])) value="{{ $query['phone_1'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="رقم الهاتف">
                                                </div>
                                            </div>
                                        @else
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">اسم او كود الزبون
                                                    </label>
                                                    <input type="text" name="name" class="form-control"
                                                        id="exampleInputName343" placeholder="اسم او كود الزبون ">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">رقم الهاتف</label>
                                                    <input type="text" name="phone_1" class="form-control"
                                                        id="exampleInputName343" placeholder="رقم الهاتف">
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
                                                <a href="{{ route('customer.index') }}" class="btn btn-primary p-3"><i
                                                        class="fas fa-redo"></i></a>
                                            </div>
                                        </div>

                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="collapse" id="collapseExample">
                            <div class="card-body">
                                <form action="{{ route('customer.store') }}" method="post"
                                    enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">


                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">اسم الزبون</label>
                                                <input type="text" name="name" class="form-control"
                                                    id="exampleInputName343" required placeholder="اسم الزبون"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال اسم الزبون')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">رقم الهاتف</label>
                                                <input type="text" name="phone_1" class="form-control"
                                                    id="exampleInputName343" required placeholder="رقم الهاتف"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال رقم الهاتف')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">رقم الهاتف 2
                                                    (إختياري)</label>
                                                <input type="text" name="phone_2" class="form-control"
                                                    id="exampleInputName343" placeholder="رقم الهاتف 2 (إختياري)">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">كود الزبون
                                                    (إختياري)</label>
                                                <input type="text" name="code" class="form-control"
                                                    id="exampleInputName343" placeholder="كود الزبون (إختياري)">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">العنوان
                                                    (إختياري)</label>
                                                <input type="text" name="address" class="form-control"
                                                    id="exampleInputName343" placeholder="العنوان (إختياري)">
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary mb-4">حفظ الزبون</button>
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
                                                            اسم الزبون
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            رقم الهاتف
                                                        </th>


                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            العنوان
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            المستحقات
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 285.883px;"
                                                            aria-label="Position: activate to sort column ascending">
                                                            الاجراءات
                                                        </th>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                    @foreach ($customers as $customer)
                                                        <tr class="odd">

                                                            <td class="">{{ $customer->name }}
                                                                <br> <a style="color: green"
                                                                    href="https://wa.me/{{ $customer->phone_1 }}"
                                                                    target="_blank">{{ $customer->code }}</a>
                                                            </td>


                                                            <td class="">
                                                                <a
                                                                    href="tel:{{ $customer->phone_1 }}">{{ $customer->phone_1 }}</a>
                                                                @if ($customer->phone_2)
                                                                    <br>
                                                                    <a
                                                                        href="tel:{{ $customer->phone_2 }}">{{ $customer->phone_2 }}</a>
                                                                @endif
                                                            </td>

                                                            @if ($customer->address)
                                                                <td class="">{{ $customer->address }}</td>
                                                            @else
                                                                <td class=""> - </td>
                                                            @endif
                                                            <td class="" style="color: green;">
                                                                {{ ($customer->orders->sum('total_price')-$customer->orders->sum('discount')) + $customer->orders->sum('ship_price') - $customer->orders->sum('paid_price') }}
                                                                DLY</td>
                                                            <td>
                                                                @can('customer-edit')
                                                                    <a href="{{ route('customer.edit', $customer->id) }}"
                                                                        class="btn btn-light-primary">تعديل
                                                                    </a>
                                                                @endcan

                                                                @can('customer-delete')
                                                                    @if ($customer->unpaidOrders->count() > 0)
                                                                        <a href="{{ route('customer.show', $customer->id) }}"
                                                                            class="btn btn-light-info">تسديد المستحقات
                                                                        </a>
                                                                    @endif
                                                                @endcan

                                                                @can('customer-delete')
                                                                    @if ($customer->paids->count() > 0)
                                                                        <a href="{{ route('customer.paidhistory', $customer->id) }}"
                                                                            class="btn btn-light-warning">سجل التسديدات
                                                                        </a>
                                                                    @endif
                                                                @endcan

                                                                @can('customer-edit')
                                                                    <button type="button" class="btn btn-light-danger"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#exampleModalLiveDelete{{ $customer->id }}">حذف</button>

                                                                    <div id="exampleModalLiveDelete{{ $customer->id }}"
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
                                                                                        هل انت متأكد من حذف الزبون <span
                                                                                            class="text-danger">{{ $customer->name }}</span>
                                                                                        ؟</p>
                                                                                    <form
                                                                                        action="{{ route('customer_delete.destroy', $customer->id) }}"
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
                                                        <th rowspan="1" colspan="1">اسم الزبون</th>
                                                        <th rowspan="1" colspan="1">رقم الهاتف</th>
                                                        <th rowspan="1" colspan="1">العنوان</th>
                                                        <th rowspan="1" colspan="1">المستحقات</th>
                                                        <th rowspan="1" colspan="1">الاجراءات</th>
                                                    </tr>

                                                </tfoot>
                                            </table>

                                            @if (isset($query))
                                                <span
                                                    id="paginate">{{ $customers->appends($query)->links('pagination::bootstrap-4') }}</span>
                                            @else
                                                <span
                                                    id="paginate">{{ $customers->links('pagination::bootstrap-4') }}</span>
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

            <div class="modal fade submissionUser" tabindex="-1" role="dialog"
                aria-labelledby="orderdetailsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="orderdetailsModalLabel">طباعة الزبائن</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">

                            <div class="row text-center">
                                <div class="col-12 pt-3">
                                    <div class="d-flex justify-content-center">
                                        <a href="{{ route('customer.print') }}" target="_blank" class="btn btn-info">طـبـاعـة كـل الـزبـائـن</a>
                                    </div>
                                </div>
                                <div class="col-12 pt-3">
                                    <div class="d-flex justify-content-center">
                                        <a href="{{ route('customer.print.depts') }}" target="_blank" class="btn btn-info">طـبـاعـة الـزبـائـن بالـديـون</a>
                                    </div>
                                </div>
                                <div class="col-12 pt-3">
                                    <div class="d-flex justify-content-center">
                                        <a href="{{ route('customer.print.noDepts') }}" target="_blank" class="btn btn-info">طـبـاعـة الـزبـائـن مـن غـيـر ديـون</a>
                                    </div>
                                </div>
                            </div>

                            <div dir="rtl">
                                <div class="row mt-2">
                                    <div class="col-12 text-end">
                                        <button type="button" class="btn btn-light me-1"
                                            data-bs-dismiss="modal">إغلاق</button>


                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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


        function showSubImage(notif) {
            var exampleUrl = "{{ route('home') }}";
            console.log(notif);
            var fileUrl = exampleUrl + "/storage/" + notif['img'];
            var imgElement = document.getElementById("submissionImage");
            var pdfElement = document.getElementById("submissionPdf");

            // Reset the display of both elements
            imgElement.style.display = "none";
            pdfElement.style.display = "none";

            if (notif['img'].endsWith('.pdf')) {
                pdfElement.src = fileUrl;
                pdfElement.style.display = "block";
            } else {
                imgElement.src = fileUrl;
                imgElement.style.display = "block";
            }
        }

        function toggleDocSelect() {
            var typeSelect = document.getElementById('type-select');
            var docSelect = document.getElementById('doc-select');
            var docDropdown = document.getElementById('doc-select-dropdown');

            if (typeSelect.value == '5') {
                docSelect.style.display = 'block';
            } else {
                docSelect.style.display = 'none';
                docDropdown.selectedIndex = 0; // إعادة تعيين الخيار المحدد
            }
        }
    </script>
@endsection
