@extends('layout.master')

@section('title')
    <title> الاشعارات </title>
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
                                <li class="breadcrumb-item"><a href="{{ route('notification.index') }}">الاشعارات</a></li>
                                <li class="breadcrumb-item" aria-current="page">القائمة</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">الاشعارات</h2>
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

                        @can('notification-create')
                            <div class="card-header">
                                <a class="btn btn-primary m-t-5" data-bs-toggle="collapse" href="#collapseExample"
                                    role="button" aria-expanded="false" aria-controls="collapseExample"> اضافة اشعار جديد</a>
                            </div>
                        @endcan

                        <div class="collapse" id="collapseExample">
                            <div class="card-body">
                                <form action="{{ route('notification.store') }}" method="post"
                                    enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputEmail1">عنوان الاشعار</label>
                                                <input type="text" name="title" class="form-control"
                                                    id="exampleInputEmail1" required aria-describedby="emailHelp"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال عنوان الاشعار')"
                                                    oninput="this.setCustomValidity('')" placeholder="عنوان الاشعار">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputEmail1">نبذة عن الاشعار</label>
                                                <input type="text" name="disc" class="form-control"
                                                    id="exampleInputEmail1" required aria-describedby="emailHelp"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال نبذة عن الاشعار')"
                                                    oninput="this.setCustomValidity('')" placeholder="نبذة عن الاشعار">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleFormControlSelect1">تحديد نوع
                                                    الجهة</label>
                                                <select class="form-select" name="type" id="exampleFormControlSelect1">
                                                    <option selected value="all">الكـل</option>
                                                    <option value="some">بعض المستخدمين</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-6" style="display: none;" id="selectSomeOrgs">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputEmail133">تحديد المستخدمين</label>
                                                <br>
                                                <select name="orgs[]" style="width: 100%; height: 300px;" class="form-control js-example-basic-multiple"
                                                    multiple="multiple" id="exampleInputEmail133">
                                                    @foreach ($orgs as $org)
                                                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName">صورة من الاشعار
                                                    (إختياري)</label>
                                                <input type="file" name="img" class="form-control"
                                                    id="exampleInputName" aria-describedby="nameHelp"
                                                    accept=".pdf,image/jpeg,image/png,image/jpg"
                                                    placeholder="صورة من الاشعار">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName333">تاريخ الاشعار
                                                    (إختياري)</label>
                                                <input type="date" name="date" class="form-control"
                                                    id="exampleInputName333" aria-describedby="nameHelp"
                                                    placeholder="تاريخ الاشعار">
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary mb-4">إرسال الاشعار</button>
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
                                            <table class="table table-hover text-center">
                                                <thead>
                                                    <tr>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Type: activate to sort column ascending">
                                                            عنوان الاشعار
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Message: activate to sort column ascending">
                                                            نبذة عن الاشعار
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Message: activate to sort column ascending">
                                                            عدد المرات المقروءة
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Customer: activate to sort column ascending">
                                                            المستخدمين
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Customer: activate to sort column ascending">
                                                            صورة الاشعار
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Customer: activate to sort column ascending">
                                                            تاريخ الاشعار
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Customer: activate to sort column ascending">
                                                            تاريخ ووقت الإنشاء في النظام
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Customer: activate to sort column ascending">
                                                            الاجراءات
                                                        </th>

                                                    </tr>

                                                </thead>
                                                <tbody>
                                                    @foreach ($notifications as $notification)
                                                        <tr class="odd">

                                                            <td class="">{{ $notification->title }}</td>

                                                            <td class="">{{ $notification->disc }}</td>

                                                            <td class="">
                                                                {{ $notification->orgs()->where('read', true)->count() }}
                                                                <br> العدد {{ $notification->orgs->count() }}
                                                            </td>

                                                            @if ($notification->type && $notification->typeOf == "type_id")
                                                                <td class="">{{ $notification->type->type }}</td>
                                                            @elseif ($notification->typeOf == "some")
                                                                <td class="">بـعـض الـمستخدمين</td>
                                                            @else
                                                                <td class="">الـكـل</td>
                                                            @endif

                                                            @if ($notification->img)
                                                                <td class="">
                                                                    <a href=".returnOrder" data-bs-toggle="modal"
                                                                        onclick="showAdImage({{ $notification }})">{{ $notification->no }}
                                                                        <br>
                                                                        <i class="fas fa-eye"
                                                                            style="font-size:25px;margin-top:5px"></i>
                                                                    </a>
                                                                </td>
                                                            @else
                                                                <td class=""> - </td>
                                                            @endif

                                                            </td>

                                                            @if ($notification->date)
                                                                <td class="">{{ $notification->date }}</td>
                                                            @else
                                                                <td class=""> - </td>
                                                            @endif

                                                            <td class="">{{ $notification->created_at }}</td>
                                                            <td>
                                                                    @can('notification-edit')
                                                                        <a href="{{ route('notification.edit', $notification->id) }}"
                                                                            class="btn btn-light-primary">تعديل
                                                                        </a>
                                                                    @endcan

                                                                    @can('notification-delete')
                                                            <button type="button"
                                                                class="btn btn-light-danger"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#exampleModalLiveDelete{{ $notification->id }}">حذف</button>

                                                            <div id="exampleModalLiveDelete{{ $notification->id }}"
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
                                                                                هل انت متأكد من حذف الاشعار <span class="text-danger">{{ $notification->title }}</span> ؟</p>
                                                                            <form
                                                                                action="{{ route('notification.destroy', $notification->id) }}"
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

                                                                </form>
                                                            </td>
                                                        </tr>
                                                    @endforeach

                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th rowspan="1" colspan="1">عنوان الاشعار</th>
                                                        <th rowspan="1" colspan="1">نبذة عن الاشعار
                                                        </th>
                                                        <th rowspan="1" colspan="1">عدد المرات المقروءة
                                                        </th>
                                                        <th rowspan="1" colspan="1">نوع الجهة
                                                        </th>
                                                        <th rowspan="1" colspan="1">صورة من الاشعار</th>
                                                        <th rowspan="1" colspan="1">تاريخ الاشعار</th>
                                                        <th rowspan="1" colspan="1">تاريخ ووقت الإنشاء في النظام
                                                        </th>


                                                        <th rowspan="1" colspan="1">الاجراءات
                                                        </th>
                                                        {{-- <th rowspan="1" colspan="1">{{ __('messages.action') }}</th> --}}

                                                    </tr>

                                                </tfoot>
                                            </table>
                                                @if(isset($query))
                                                <span id="paginate">{{ $notifications->appends($query)->links('pagination::bootstrap-4') }}</span>
                                            @else
                                                <span id="paginate">{{ $notifications->links('pagination::bootstrap-4') }}</span>
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

            <div class="modal fade returnOrder" tabindex="-1" role="dialog" aria-labelledby="orderdetailsModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="orderdetailsModalLabel">صورة من الاشعار</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">

                            <div class="row text-center">
                                <div class="col-12 pt-3">
                                    <img src="" id="notificationImage" alt="img" class="img-fluid" style="display: none;" />
                                    <embed src="" id="notificationPdf" type="application/pdf" width="100%" height="600px" style="display: none;">
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
function showAdImage(notif) {
    var exampleUrl = "{{ route('home') }}";
    var fileUrl = exampleUrl + "/storage/" + notif['img'];
    var imgElement = document.getElementById("notificationImage");
    var pdfElement = document.getElementById("notificationPdf");

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


        document.addEventListener("DOMContentLoaded", function() {
        const selectType = document.getElementById('exampleFormControlSelect1');
        const selectSomeOrgs = document.getElementById('selectSomeOrgs');

        // Listen for changes on the first select
        selectType.addEventListener('change', function() {
            if (selectType.value === 'some') {
                selectSomeOrgs.style.display = 'block';
            } else {
                selectSomeOrgs.style.display = 'none';
            }
        });

        // Check initial value
        if (selectType.value === 'some') {
            selectSomeOrgs.style.display = 'block';
        } else {
            selectSomeOrgs.style.display = 'none';
        }
    });

    </script>
@endsection
