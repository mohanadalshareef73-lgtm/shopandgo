@extends('layout.master')

@section('title')
    <title> حالات الرحلة </title>
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
                                <h2 class="mb-0">حالات الرحلة </h2>
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

                            @can('status-create')
                                <a class="btn btn-primary m-t-5" data-bs-toggle="collapse" href="#collapseExample"
                                    role="button" aria-expanded="false" aria-controls="collapseExample">اضافة حالة الرحلة جديدة</a>
                            @endcan
                            <a class="btn btn-primary m-t-5" style="margin-right: 10px;" data-bs-toggle="collapse"
                                href="#collapseExample2" role="button" aria-expanded="false"
                                aria-controls="collapseExample2">الـبـحـث</a>

                        </div>


                        <div class="collapse" id="collapseExample2">
                            <div class="card-body">
                                <form action="{{ route('status.search') }}" method="get" enctype="multipart/form-data">
                                    @csrf

                                    <div class="row">
                                        @if (isset($query))
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">اسم حالة الرحلة </label>
                                                    <input type="text" name="name"
                                                        @if (isset($query['name'])) value="{{ $query['name'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="اسم حالة الرحلة ">
                                                </div>
                                            </div>
                                        @else
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">اسم حالة الرحلة </label>
                                                    <input type="text" name="name" class="form-control"
                                                        id="exampleInputName343" placeholder="اسم حالة الرحلة ">
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
                                                <a href="{{ route('status.index') }}" class="btn btn-primary p-3"><i
                                                        class="fas fa-redo"></i></a>
                                            </div>
                                        </div>

                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="collapse" id="collapseExample">
                            <div class="card-body">
                                <form action="{{ route('status.store') }}" method="post"
                                    enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">


                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">اسم حالة الرحلة</label>
                                                <input type="text" name="name" class="form-control"
                                                    id="exampleInputName343" required placeholder="اسم حالة الرحلة"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال اسم حالة الرحلة')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary mb-4">حفظ حالة الرحلة</button>
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
                                                            اسم حالة الرحلة
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            عدد الطلبات
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 285.883px;"
                                                            aria-label="Position: activate to sort column ascending">
                                                            الاجراءات
                                                        </th>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                    @foreach ($status as $status1)
                                                        <tr class="odd">

                                                            <td class="">{{ $status1->name }}</td>


                                                            <td class="">{{ $status1->orders->count() }}</td>

                                                            <td>
                                                                @can('status-edit')
                                                                    <a href="{{ route('status.edit', $status1->id) }}"
                                                                        class="btn btn-light-primary">تعديل
                                                                    </a>
                                                                @endcan

                                                                @can('status-delete')
                                                                    @if ($status1->orders->count() > 0)
                                                                        <a href="{{ route('status.show', $status1->id) }}"
                                                                            class="btn btn-light-info">طلبيات حالة الرحلة
                                                                        </a>
                                                                    @endif
                                                                @endcan

                                                                @can('status-delete')
                                                                        <button type="button"
                                                                            class="btn btn-light-danger"
                                                                            data-bs-toggle="modal"
                                                                            data-bs-target="#exampleModalLiveDelete{{ $status1->id }}">حذف</button>

                                                                        <div id="exampleModalLiveDelete{{ $status1->id }}"
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
                                                                                            هل انت متأكد من حذف حالة الرحلة <span class="text-danger">{{ $status1->name }}</span> ؟</p>
                                                                                        <form
                                                                                            action="{{ route('status.destroy', $status1->id) }}"
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
                                                        <th rowspan="1" colspan="1">اسم حالة الرحلة</th>
                                                        <th rowspan="1" colspan="1">عدد الطلبات</th>
                                                        <th rowspan="1" colspan="1">الاجراءات</th>
                                                    </tr>

                                                </tfoot>
                                            </table>

                                            @if (isset($query))
                                                <span
                                                    id="paginate">{{ $status->appends($query)->links('pagination::bootstrap-4') }}</span>
                                            @else
                                                <span
                                                    id="paginate">{{ $status->links('pagination::bootstrap-4') }}</span>
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
    </script>
@endsection
