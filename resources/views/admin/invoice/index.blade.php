@extends('layout.master')

@section('title')
    <title> بيانات الفاتورة </title>
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
                                <li class="breadcrumb-item"><a href="{{ route('invoice.index') }}">بيانات الفاتورة</a></li>
                                <li class="breadcrumb-item" aria-current="page">القائمة</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">بيانات الفاتورة</h2>
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
                                    role="button" aria-expanded="false" aria-controls="collapseExample"> تعديل بيانات
                                    الفاتورة</a>
                            </div>
                        @endcan

                        <div class="collapse" id="collapseExample">
                            <div class="card-body">
                                <form action="{{ route('invoice.update', $invoice->id) }}" method="post"
                                    enctype="multipart/form-data">
                                    @method('PUT')
                                    @csrf
                                    <div class="row">

                                        <div class="col-md-12 pb-4">
                                            <div class="page-header-title">
                                                <h4 class="mb-0">بيانات فاتورة الطلبات</h4>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName">رسالة ترحيبية</label>
                                                <textarea type="text" name="welcome" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                    placeholder="رسالة ترحيبية" required oninvalid="this.setCustomValidity('الرجاء ادخال الرسالة الترحيبية')"
                                                    oninput="this.setCustomValidity('')">{{ $invoice->welcome }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName">الملاحظة الاولي</label>
                                                <textarea type="text" name="note" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                    placeholder="الملاحظة الاولي"required oninvalid="this.setCustomValidity('الرجاء ادخال الملاحظة الاولي')"
                                                    oninput="this.setCustomValidity('')">{{ $invoice->note }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName">الملاحظة الثانية</label>
                                                <textarea type="text" name="note2" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                    placeholder="الملاحظة الثانية" required oninvalid="this.setCustomValidity('الرجاء ادخال الملاحظة الثانية')"
                                                    oninput="this.setCustomValidity('')">{{ $invoice->note2 }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName">العنوان</label>
                                                <textarea type="text" name="address" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                    placeholder="العنوان" required oninvalid="this.setCustomValidity('الرجاء ادخال العنوان')"
                                                    oninput="this.setCustomValidity('')">{{ $invoice->address }}</textarea>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary mb-4">حفظ التعديلات</button>
                                        </div>
                                    </div>
                            </div>

                            <div class="card-body">
                                    <div class="row">

                                        <div class="col-md-12 pb-4">
                                            <div class="page-header-title">
                                                <h4 class="mb-0">بيانات فاتورة المستحقات</h4>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName">رسالة ترحيبية</label>
                                                <textarea type="text" name="welcome_2" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                    placeholder="رسالة ترحيبية" required oninvalid="this.setCustomValidity('الرجاء ادخال الرسالة الترحيبية')"
                                                    oninput="this.setCustomValidity('')">{{ $invoice2->welcome }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName">الملاحظة الاولي</label>
                                                <textarea type="text" name="note_2" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                    placeholder="الملاحظة الاولي"required oninvalid="this.setCustomValidity('الرجاء ادخال الملاحظة الاولي')"
                                                    oninput="this.setCustomValidity('')">{{ $invoice2->note }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName">الملاحظة الثانية</label>
                                                <textarea type="text" name="note2_2" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                    placeholder="الملاحظة الثانية" required oninvalid="this.setCustomValidity('الرجاء ادخال الملاحظة الثانية')"
                                                    oninput="this.setCustomValidity('')">{{ $invoice2->note2 }}</textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName">العنوان</label>
                                                <textarea type="text" name="address_2" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                    placeholder="العنوان" required oninvalid="this.setCustomValidity('الرجاء ادخال العنوان')"
                                                    oninput="this.setCustomValidity('')">{{ $invoice2->address }}</textarea>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary mb-4">حفظ التعديلات</button>
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

                                            <div class="row dt-row">


                                                <div class="col-md-12 pb-4">
                                                    <div class="page-header-title">
                                                        <h4 class="mb-0">بيانات فاتورة الطلبات</h4>
                                                    </div>
                                                </div>

                                            <div class="col-md-6">
                                                <div class="pc-component">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h5 class="card-title">الرسالة الترحيبية</h5>
                                                                <span style="font-size: 16px;" class="text-primary">{{ $invoice->welcome }}</span>

                                                            <p class="card-text pt-1">
                                                                وقت اخر تعديل <span style="font-size: 16px;" class="text-primary">{{ $invoice->updated_at->format('Y-m-d h:i:s') }}</span> </p>
                                                            {{-- <a href="#!" class="card-link">Card link</a>
                                                      <a href="#!" class="card-link">Another link</a> --}}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="pc-component">
                                                    <div class="card">
                                                        <div class="card-body">

                                                            <h5 class="card-title">الملاحظة الاولي</h5>
                                                            <span style="font-size: 16px;" class="text-primary">{{ $invoice->note }}</span>


                                                            <p class="card-text pt-1">
                                                                وقت اخر تعديل <span style="font-size: 16px;" class="text-primary">{{ $invoice->updated_at->format('Y-m-d h:i:s') }}</span> </p>
                                                            {{-- <a href="#!" class="card-link">Card link</a>
                                                      <a href="#!" class="card-link">Another link</a> --}}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="pc-component">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h5 class="card-title">الملاحظة الثانية</h5>
                                                            <span style="font-size: 16px;" class="text-primary">{{ $invoice->note2 }}</span>


                                                            <p class="card-text pt-1">
                                                                وقت اخر تعديل <span style="font-size: 16px;" class="text-primary">{{ $invoice->updated_at->format('Y-m-d h:i:s') }}</span> </p>
                                                            {{-- <a href="#!" class="card-link">Card link</a>
                                                      <a href="#!" class="card-link">Another link</a> --}}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="pc-component">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h5 class="card-title">العنوان</h5>
                                                            <span style="font-size: 16px;" class="text-primary">{{ $invoice->address }}</span>


                                                            <p class="card-text pt-1">
                                                                وقت اخر تعديل <span style="font-size: 16px;" class="text-primary">{{ $invoice->updated_at->format('Y-m-d h:i:s') }}</span> </p>
                                                            {{-- <a href="#!" class="card-link">Card link</a>
                                                      <a href="#!" class="card-link">Another link</a> --}}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            </div>

                                        </div>
                                    </div>

                                </div>

                            </div>
                        </div>


                        <div class="card-body">
                            <div class="dt-responsive table-responsive">
                                <div id="autofill_wrapper" class="dataTables_wrapper dt-bootstrap5">
                                    <div class="row dt-row">
                                        <div class="col-sm-12">

                                            <div class="row dt-row">


                                        <div class="col-md-12 pb-4">
                                            <div class="page-header-title">
                                                <h4 class="mb-0">بيانات فاتورة المستحقات</h4>
                                            </div>
                                        </div>

                                            <div class="col-md-6">
                                                <div class="pc-component">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h5 class="card-title">الرسالة الترحيبية</h5>
                                                                <span style="font-size: 16px;" class="text-primary">{{ $invoice2->welcome }}</span>

                                                            <p class="card-text pt-1">
                                                                وقت اخر تعديل <span style="font-size: 16px;" class="text-primary">{{ $invoice2->updated_at->format('Y-m-d h:i:s') }}</span> </p>
                                                            {{-- <a href="#!" class="card-link">Card link</a>
                                                      <a href="#!" class="card-link">Another link</a> --}}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="pc-component">
                                                    <div class="card">
                                                        <div class="card-body">

                                                            <h5 class="card-title">الملاحظة الاولي</h5>
                                                            <span style="font-size: 16px;" class="text-primary">{{ $invoice2->note }}</span>


                                                            <p class="card-text pt-1">
                                                                وقت اخر تعديل <span style="font-size: 16px;" class="text-primary">{{ $invoice2->updated_at->format('Y-m-d h:i:s') }}</span> </p>
                                                            {{-- <a href="#!" class="card-link">Card link</a>
                                                      <a href="#!" class="card-link">Another link</a> --}}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="pc-component">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h5 class="card-title">الملاحظة الثانية</h5>
                                                            <span style="font-size: 16px;" class="text-primary">{{ $invoice2->note2 }}</span>


                                                            <p class="card-text pt-1">
                                                                وقت اخر تعديل <span style="font-size: 16px;" class="text-primary">{{ $invoice2->updated_at->format('Y-m-d h:i:s') }}</span> </p>
                                                            {{-- <a href="#!" class="card-link">Card link</a>
                                                      <a href="#!" class="card-link">Another link</a> --}}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="pc-component">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h5 class="card-title">العنوان</h5>
                                                            <span style="font-size: 16px;" class="text-primary">{{ $invoice2->address }}</span>


                                                            <p class="card-text pt-1">
                                                                وقت اخر تعديل <span style="font-size: 16px;" class="text-primary">{{ $invoice2->updated_at->format('Y-m-d h:i:s') }}</span> </p>
                                                            {{-- <a href="#!" class="card-link">Card link</a>
                                                      <a href="#!" class="card-link">Another link</a> --}}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            </div>

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
                invoiceable: false,
                className: 'select-checkbox',
                targets: 0
            }],
            select: {
                style: 'os',
                selector: 'td:first-child'
            },
            invoice: [
                [1, 'asc']
            ],
            autoFill: {
                columns: ':not(:first-child)'
            }
        });
    </script>
@endsection
