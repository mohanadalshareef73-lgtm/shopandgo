@extends('layout.master')

@section('title')
    <title> سجل التسديدات </title>
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
                                <h2 class="mb-0">سجل التسديدات للطلبية <span class="text-primary">{{ $order->code }} - {{ $order->customer->name }} - {{ $order->customer->code }}</span> </h2>
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
                                                            aria-label="Name: activate to sort column ascending">
                                                            اسم الزبون
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            القيمة
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            الطلبية
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            التاريخ
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 285.883px;"
                                                            aria-label="Position: activate to sort column ascending">
                                                            الاجراءات
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($paids as $paid)
                                                        <tr class="odd">

                                                            <td>
                                                                <a href="{{ route('customer.paid.print', $paid->id) }}"
                                                                    class="btn btn-light-primary" target="_blank"><i
                                                                        class="fas fa-print"></i>
                                                                </a>
                                                            </td>

                                                            <td class="">{{ $paid->customer->name }}
                                                                <br>
                                                                <a style="color: green" href="https://wa.me/{{ $paid->customer->phone_1 }}" target="_blank">{{ $paid->customer->code }}</a></td>

                                                                <td class="" style="color: green;">
                                                                    {{ $paid->paid_price }}
                                                                    {{ $paid->order->currency_sign }}
                                                                    <br>
                                                                    <span class="text-secondary">{{ $paid->paymentMethod->name }}</span>
                                                                </td>

                                                                <td class="">{{ $paid->order->code }} </td>
                                                                <td class="">{{ $paid->created_at->format('Y-m-d') }} </td>
                                                            <td>

                                                                @can('customer-delete')

                                                                        <div class="col-sm-6 d-flex justify-content-center align-items-center">
                                                                            <div class="">
                                                                                <div class="card-body pc-component">
                                                                                    <div id="exampleModalLive22"
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
                                                                                                            class="fas fa-trash-alt text-danger fa-5x mb-3"></i>
                                                                                                    </div>
                                                                                                    <p
                                                                                                        style="font-size: 18px;">
                                                                                                        هل انت متأكد من حذف السجل<br> سيتم تسجيل الحركة في النظام !</p>
                                                                                                    <form action="{{ route('customer.destroy', $paid->id) }}" method="POST">
                                                                                                        @csrf
                                                                                                        @method('DELETE')

                                                                                                </div>
                                                                                                <div
                                                                                                    class="modal-footer d-flex justify-content-center">
                                                                                                    <button type="submit"
                                                                                                        class="btn btn-primary">نـعـم</button>
                                                                                                    </form>
                                                                                                    <button type="button"
                                                                                                        class="btn btn-secondary"
                                                                                                        data-bs-dismiss="modal">إغـلاق</button>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <button type="button"
                                                                                        class="btn btn-light-danger"
                                                                                        data-bs-toggle="modal"
                                                                                        data-bs-target="#exampleModalLive22">حذف السجل</button>
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
                                                        <th rowspan="1" colspan="1">اسم الزبون</th>
                                                        <th rowspan="1" colspan="1">القيمة</th>
                                                        <th rowspan="1" colspan="1">الطلبية</th>
                                                        <th rowspan="1" colspan="1">التاريخ</th>
                                                        <th rowspan="1" colspan="1">الاجراءات</th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                            @if(isset($query))
                                            <span id="paginate">{{ $paids->appends($query)->links('pagination::bootstrap-4') }}</span>
                                        @else
                                            <span id="paginate">{{ $paids->links('pagination::bootstrap-4') }}</span>
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
