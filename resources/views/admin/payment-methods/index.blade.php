@extends('layout.master')

@section('title')
    <title>طرق الدفع </title>
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
                                <li class="breadcrumb-item"><a href="javascript: void(0)">طرق الدفع</a></li>
                                <li class="breadcrumb-item" aria-current="page">القائمة</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">طرق الدفع</h2>
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


                        <div class="card-header d-flex justify-content-between">
                            <div>
                                @can('payment-methods-list')
                                    <a class="btn btn-primary m-t-5" data-bs-toggle="collapse" href="#collapseExamplesearch"
                                        role="button" aria-expanded="false" aria-controls="collapseExamplesearch">
                                        الـبـحـث
                                    </a>
                                @endcan
                            </div>

                            <div>
                                @can('payment-methods-create')
                                    <a class="btn btn-primary m-t-5" data-bs-toggle="collapse" href="#collapseExample"
                                        role="button" aria-expanded="false" aria-controls="collapseExample">
                                        اضافة طريقة الدفع جديدة
                                    </a>
                                @endcan
                            </div>
                        </div>



                        @can('payment-methods-list')
                            <div class="collapse" id="collapseExamplesearch">
                                <div class="card-body">
                                    <form action="{{ route('payment-methods.search') }}" method="get"
                                        enctype="multipart/form-data">
                                        @csrf
                                        <div class="row">
                                            @if (isset($query))
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="form-label" for="exampleInputName">البحث هنا...</label>
                                                        <input type="text" name="search"
                                                            class="form-control" id="exampleInputName"
                                                            @if (isset($query['search'])) value="{{ $query['search'] }}" @endif
                                                            aria-describedby="nameHelp" placeholder="البحث هنا...">
                                                    </div>
                                                </div>
                                            @else
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label class="form-label" for="exampleInputName">البحث هنا...</label>
                                                        <input type="text" name="search" value="{{ old('search') }}"
                                                            class="form-control" id="exampleInputName"
                                                            aria-describedby="nameHelp" placeholder="البحث هنا...">
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
                                                    <a href="{{ route('payment-methods.index') }}"
                                                        class="btn btn-primary p-3"><i class="fas fa-redo"></i></a>
                                                </div>
                                            </div>

                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endcan


                        @can('payment-methods-create')
                            <div class="collapse" id="collapseExample">
                                <div class="card-body">
                                    <form action="{{ route('payment-methods.store') }}" method="post"
                                        enctype="multipart/form-data">
                                        @csrf
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName">اسم طريقة الدفع</label>
                                                    <input type="text" name="name"
                                                        value="{{ old('name') }}" class="form-control"
                                                        id="exampleInputName" required aria-describedby="nameHelp"
                                                        placeholder="اسم طريقة الدفع"
                                                        oninvalid="this.setCustomValidity('الرجاء ادخال اسم طريقة الدفع')"
                                                        oninput="this.setCustomValidity('')">
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
                                            <div class="form-group">
                                                <div class="form-check">
                                                    <input class="form-check-input" checked type="checkbox"
                                                        name="status" id="invalidCheck">
                                                    <label class="form-check-label" for="invalidCheck">تفعيل
                                                        طريقة الدفع</label>
                                                </div>
                                            </div>
                                                <button type="submit" class="btn btn-primary mb-4">حفظ طريقة الدفع
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
                                                            اسم طريقة الدفع
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Status: activate to sort column ascending">
                                                            حالة طريقة الدفع
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
                                                    @foreach ($payments as $index => $payment)
                                                        <tr class="odd">

                                                            <td class="">{{ ++$index }}</td>
                                                            <td class="">{{ $payment->name }}
                                                            </td>

                                                            @can('payment-methods-edit')
                                                            <td class="">
                                                                <div class="form-check form-switch custom-switch-v1 mb-2">
                                                                    @if ($payment->status)
                                                                        <input type="checkbox"
                                                                            onclick="redirectToAd('{{ route('payment-methods.show', $payment->id) }}')"
                                                                            class="form-check-input input-primary"
                                                                            id="{{ $payment->id }}" checked>
                                                                    @else
                                                                        <input type="checkbox"
                                                                            onclick="redirectToAd('{{ route('payment-methods.show', $payment->id) }}')"
                                                                            class="form-check-input input-primary"
                                                                            id="{{ $payment->id }}">
                                                                    @endif
                                                                </div>
                                                            </td>
                                                            @else
                                                                <td class=""> - </td>
                                                            @endcan

                                                            <td class="">{{ $payment->notes }} </td>

                                                            @can('payment-methods-edit')
                                                                <td>
                                                                    <a href="{{ route('payment-methods.edit', $payment->id) }}"
                                                                        class="btn btn-light-primary">تعديل
                                                                    </a>
                                                                </td>
                                                            @else
                                                                <td class=""> - </td>
                                                            @endcan
                                                        </tr>
                                                    @endforeach

                                                </tbody>

                                                <tfoot>

                                                    <tr>
                                                        <th rowspan="1" colspan="1">#</th>
                                                        <th rowspan="1" colspan="1">اسم طريقة الدفع
                                                        </th>
                                                        <th rowspan="1" colspan="1">حالة طريقة الدفع</th>
                                                        <th rowspan="1" colspan="1">ملاحظات</th>
                                                        <th rowspan="1" colspan="1">الاجراءات
                                                        </th>
                                                    </tr>

                                                </tfoot>
                                            </table>
                                            @if (isset($query))
                                                <span
                                                    id="paginate">{{ $payments->appends($query)->links('pagination::bootstrap-4') }}</span>
                                            @else
                                                <span
                                                    id="paginate">{{ $payments->links('pagination::bootstrap-4') }}</span>
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
