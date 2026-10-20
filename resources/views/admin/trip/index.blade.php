@extends('layout.master')

@section('title')
    <title> الرحلات </title>
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

                                @if ($page == "all")
                                <h2 class="mb-0">كل الرحلات </h2>
                                @elseif($page == "onway")
                                <h2 class="mb-0">رحلات في الطريق </h2>
                                @elseif($page == "arrived")
                                <h2 class="mb-0">رحلات تم وصولها </h2>
                                @elseif($page == "delay")
                                <h2 class="mb-0">رحلات متأخرة </h2>
                                @endif

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
                                @can('trip-create')
                                <a class="btn btn-primary m-t-5" data-bs-toggle="collapse" href="#collapseExample"
                                    role="button" aria-expanded="false" aria-controls="collapseExample">اضافة رحلة جديدة</a>
                                    @endcan
                                    <a class="btn btn-primary m-t-5" style="margin-right: 10px;" data-bs-toggle="collapse" href="#collapseExample2"
                                    role="button" aria-expanded="false" aria-controls="collapseExample2">الـبـحـث</a>

                            </div>


                        <div class="collapse" id="collapseExample2">
                            <div class="card-body">
                                <form action="{{ route('trip.search') }}" method="get" enctype="multipart/form-data">
                                    @csrf

                                    <div class="row">
                                    @if (isset($query))
                                    <input type="hidden"
                                    @if (isset($query['page']))
                                    value="{{ $query['page'] }}"
                                @endif

                                     name="page">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">كود الرحلة </label>
                                                <input type="text" name="code"
                                                @if (isset($query['code']))
                                                    value="{{ $query['code'] }}"
                                                @endif
                                                  class="form-control"
                                                    id="exampleInputName343" placeholder="كود الرحلة  ">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">البلد المصدر</label>
                                                <input type="text" name="country"
                                                @if (isset($query['country']))
                                                value="{{ $query['country'] }}"
                                                @endif
                                                 class="form-control"
                                                    id="exampleInputName343" placeholder="البلد المصدر">
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">تاريخ المغادرة</label>
                                                <input type="date" name="out_date"
                                                @if (isset($query['out_date']))
                                                value="{{ $query['out_date'] }}"
                                                @endif
                                                 class="form-control"
                                                    id="exampleInputName343" placeholder="تاريخ المغادرة">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">تاريخ الوصول المتوقع</label>
                                                <input type="date" name="arrive_date"
                                                @if (isset($query['arrive_date']))
                                                value="{{ $query['arrive_date'] }}"
                                                @endif
                                                 class="form-control"
                                                    id="exampleInputName343" placeholder="تاريخ الوصول المتوقع">
                                            </div>
                                        </div>
                                    @else

                                    <input type="hidden" value="{{ $page }}" name="page">

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">كود الرحلة </label>
                                            <input type="text" name="code"
                                              class="form-control"
                                                id="exampleInputName343" placeholder="كود الرحلة  ">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">البلد المصدر</label>
                                            <input type="text" name="country"
                                             class="form-control"
                                                id="exampleInputName343" placeholder="البلد المصدر">
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">تاريخ المغادرة</label>
                                            <input type="date" name="out_date"
                                             class="form-control"
                                                id="exampleInputName343" placeholder="تاريخ المغادرة">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">تاريخ الوصول المتوقع</label>
                                            <input type="date" name="arrive_date"
                                             class="form-control"
                                                id="exampleInputName343" placeholder="تاريخ الوصول المتوقع">
                                        </div>
                                    </div>
                                    @endif

                                        <div class="col-md-2">
                                            <div class="form-group pt-4">
                                                <label class="form-label" for="exampleInputName343"></label>
                                                <button type="submit" class="btn btn-primary p-3"><i class="fas fa-search"></i></button>
                                            </div>
                                        </div>

                                        <div class="col-md-2">
                                            <div class="form-group pt-4">
                                                <label class="form-label" for="exampleInputName343"></label>
                                                @if ($page == "all")
                                                <a href="{{ route('trip.index') }}" class="btn btn-primary p-3"><i class="fas fa-redo"></i></a>
                                                @elseif($page == "onway")
                                                <a href="{{ route('trip.onway.index') }}" class="btn btn-primary p-3"><i class="fas fa-redo"></i></a>
                                                @elseif($page == "arrived")
                                                <a href="{{ route('trip.arrived.index') }}" class="btn btn-primary p-3"><i class="fas fa-redo"></i></a>
                                                @elseif($page == "delay")
                                                <a href="{{ route('trip.delay.index') }}" class="btn btn-primary p-3"><i class="fas fa-redo"></i></a>
                                                @endif
                                            </div>
                                        </div>

                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="collapse" id="collapseExample">
                            <div class="card-body">
                                <form action="{{ route('trip.store') }}" method="post" enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">


                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">كود الرحلة</label>
                                                <input type="text" name="code" class="form-control"
                                                    id="exampleInputName343" required placeholder="كود الرحلة"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال كود الرحلة')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">البلد المصدر</label>
                                                <input type="text" name="country" class="form-control"
                                                    id="exampleInputName343" required placeholder="البلد المصدر"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال البلد المصدر')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">تاريخ مغادرة الرحلة</label>
                                                <input type="date" name="out_date" class="form-control"
                                                    id="exampleInputName343" required placeholder="تاريخ مغادرة الرحلة"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال تاريخ مغادرة الرحلة')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">تاريخ الوصول المتوقع</label>
                                                <input type="date" name="arrive_date" class="form-control"
                                                    id="exampleInputName343" required placeholder="تاريخ الوصول المتوقع"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال تاريخ الوصول المتوقع')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName">وثيقة
                                                    (إختياري)</label>
                                                <input type="file" name="doc" class="form-control"
                                                    id="exampleInputName" aria-describedby="nameHelp"
                                                    accept=".pdf,image/jpeg,image/png,image/jpg"
                                                    placeholder="وثيقة من الرحلة ان وجدت">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label"
                                                    for="exampleInputName">ملاحطات (إختياري)</label>
                                                <textarea type="text" name="note" class="form-control"
                                                    id="exampleInputName" aria-describedby="nameHelp"
                                                    placeholder="ملاحظات (إختياري)"></textarea>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary mb-4">حفظ الرحلة</button>
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
                                                            كود الرحلة
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            البلد المصدر
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            حالة الرحلة
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                        rowspan="1" colspan="1" style="width: 175.717px;"
                                                        aria-label="Product: activate to sort column ascending">
                                                        عدد الطلبات
                                                    </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            تاريخ المغادرة
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            تاريخ الوصول المتوقع
                                                        </th>


                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            الوثيقة
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
                                                    @foreach ($trips as $trip)
                                                        <tr class="odd">

                                                            <td class="">{{ $trip->code }}</td>

                                                                <td class="">{{ $trip->country }}</td>

                                                                @if ($trip->arrived)
                                                                <td class="text-primary">تم الوصول
                                                                    <br>{{ $trip->arrived_at }}
                                                                </td>
                                                                @elseif ($trip->out_date > now())
                                                                    <td class="text-primary">لم تغادر بعد
                                                                    </td>
                                                                @elseif ($trip->arrive_date > now())
                                                                    <td class="text-warning">في الطريق
                                                                    </td>
                                                                @elseif ($trip->arrive_date < now() && !$trip->arrived)
                                                                    <td class="text-danger">متأخرة
                                                                    </td>
                                                                @elseif ($trip->arrive_date < now() && $trip->arrived)
                                                                    <td class="text-success">وصلت
                                                                    </td>
                                                                @endif

                                                                <td class="">{{ $trip->orders->count() }}</td>
                                                                <td class="">{{ $trip->out_date }}</td>
                                                                <td class="">{{ $trip->arrive_date }}</td>

                                                            @if ($trip->doc)
                                                                <td class="">
                                                                    <a href=".submissionUser" data-bs-toggle="modal"
                                                                        onclick="showSubImage({{ $trip }})">
                                                                        <i class="fas fa-eye"
                                                                            style="font-size:25px;margin-top:5px"></i>
                                                                    </a>
                                                                </td>
                                                            @else
                                                                <td class=""> - </td>
                                                            @endif

                                                            @if ($trip->note)
                                                                <td class="">{{ $trip->note }}</td>
                                                            @else
                                                                <td class=""> - </td>
                                                            @endif

                                                            <td>
                                                                @can('trip-edit')
                                                                    <a href="{{ route('trip.edit', $trip->id) }}"
                                                                        class="btn btn-light-primary">تعديل
                                                                    </a>
                                                                @endcan

                                                                @if ($trip->orders->count() > 0)
                                                                @can('trip-list')
                                                                    <a href="{{ route('trip.show', $trip->id) }}"
                                                                        class="btn btn-light-info">عرض الطلبات
                                                                    </a>
                                                                @endcan
                                                                @endif

                                                                @can('trip-edit')
                                                                    @if (!$trip->arrived)
                                                                        {{-- <a href="{{ route('trip.arrived', $trip->id) }}"
                                                                            class="btn btn-light-success">وصلت
                                                                        </a> --}}

                                                                        <button type="button"
                                                                    class="btn btn-light-success"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#exampleModalLiveArrivedTrip{{ $trip->id }}">وصلت</button>

                                                                <div id="exampleModalLiveArrivedTrip{{ $trip->id }}"
                                                                    class="modal fade" tabindex="-1"
                                                                    role="dialog"
                                                                    aria-labelledby="exampleModalLiveLabel"
                                                                    aria-hidden="true">
                                                                    <div class="modal-dialog modal-dialog-centered"
                                                                        role="document">
                                                                        <div class="modal-content">
                                                                            <div
                                                                                class="modal-body text-center">
                                                                                <div>
                                                                                    <i
                                                                                        class="ti ti-alert-circle text-success fa-5x mb-3"></i>
                                                                                </div>
                                                                                <p
                                                                                    style="font-size: 18px;">
                                                                                    هل انت متأكد من وصول الرحلة <span class="text-success">{{ $trip->code }}</span> القادمة من <span class="text-danger">{{ $trip->country }}</span> ؟</p>
                                                                                <form
                                                                                    action="{{ route('trip.arrived', $trip->id) }}"
                                                                                    method="get">
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

                                                            @can('trip-delete')
                                                                <button type="button"
                                                                    class="btn btn-light-danger"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#exampleModalLiveDelete{{ $trip->id }}">حذف</button>

                                                                <div id="exampleModalLiveDelete{{ $trip->id }}"
                                                                    class="modal fade" tabindex="-1"
                                                                    role="dialog"
                                                                    aria-labelledby="exampleModalLiveLabel"
                                                                    aria-hidden="true">
                                                                    <div class="modal-dialog modal-dialog-centered"
                                                                        role="document">
                                                                        <div class="modal-content">
                                                                            <div
                                                                                class="modal-body text-center">
                                                                                <div>
                                                                                    <i
                                                                                        class="ti ti-alert-circle text-danger fa-5x mb-3"></i>
                                                                                </div>
                                                                                <p
                                                                                    style="font-size: 18px;">
                                                                                    هل انت متأكد من حذف الرحلة <span class="text-danger">{{ $trip->code }}</span> القادمة من <span class="text-danger">{{ $trip->country }}</span> ؟</p>
                                                                                <form
                                                                                    action="{{ route('trip.destroy', $trip->id) }}"
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
                                                        <th rowspan="1" colspan="1">كود الرحلة</th>
                                                        <th rowspan="1" colspan="1">البلد المصدر</th>
                                                        <th rowspan="1" colspan="1">حالة الرحلة</th>
                                                        <th rowspan="1" colspan="1">عدد الطلبات</th>
                                                        <th rowspan="1" colspan="1">تاريخ المغادرة</th>
                                                        <th rowspan="1" colspan="1">تاريخ الوصول المتوقع</th>
                                                        <th rowspan="1" colspan="1">الوثيقة</th>
                                                        <th rowspan="1" colspan="1">ملاحظات</th>
                                                        <th rowspan="1" colspan="1">الاجراءات</th>
                                                    </tr>

                                                </tfoot>
                                            </table>

                                            @if(isset($query))
                                            <span id="paginate">{{ $trips->appends($query)->links('pagination::bootstrap-4') }}</span>
                                        @else
                                            <span id="paginate">{{ $trips->links('pagination::bootstrap-4') }}</span>
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
                            <h5 class="modal-title" id="orderdetailsModalLabel">وثيقة الرحلة</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">

                            <div class="row text-center">
                                <div class="col-12 pt-3">
                                    <img src="" id="submissionImage" alt="img" class="img-fluid"
                                        style="display: none;" />
                                    <embed src="" id="submissionPdf" type="application/pdf" width="100%"
                                        height="600px" style="display: none;">
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
            var fileUrl = exampleUrl + "/storage/" + notif['doc'];
            var imgElement = document.getElementById("submissionImage");
            var pdfElement = document.getElementById("submissionPdf");

            // Reset the display of both elements
            imgElement.style.display = "none";
            pdfElement.style.display = "none";

            if (notif['doc'].endsWith('.pdf')) {
                pdfElement.src = fileUrl;
                pdfElement.style.display = "block";
            } else {
                imgElement.src = fileUrl;
                imgElement.style.display = "block";
            }
        }

    </script>
@endsection
