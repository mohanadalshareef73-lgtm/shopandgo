@extends('layout.master')

@section('title')
    <title> حركات النظام </title>
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
                                <li class="breadcrumb-item"><a href="{{ route('home') }}">الرئيسية</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('log.index') }}">حركات النظام</a></li>
                                <li class="breadcrumb-item" aria-current="page">القائمة</li>
                            </ul>
                        </div>

                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">حركات النظام</h2>
                            </div>
                        </div>

                        <div class="col-md-12 pt-3">
                            <a href=".exampleModal" data-bs-toggle="modal" data-bs-target="#exampleModal"
                                class="btn btn-primary d-inline-flex align-item-start" data-bs-toggle="modal"
                                data-bs-target="#customer-edit_add-modal">
                                <i class="ti ti-search f-18"></i>
                            </a>

                            {{-- <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal"
                          data-bs-whatever="@getbootstrap">Open modal for @bootstrap</button> --}}

                        </div>

                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->

            @include('layout.validation-messages')



            <!-- [ Main Content ] start -->
            <div class="row">
                <!-- [ card ] start -->

                @if ($logs->count() == 0)
                    <div class="col-md-12">
                        <div class="page-header-title text-center">
                            <h5 style="color: grey;" class="mb-0">لا يوجد اي حركات</h5>
                        </div>
                    </div>
                @endif
                @foreach ($logs as $log)
                    <div class="col-md-6 col-xl-4">
                        <div class="pc-component">
                            <div class="card">
                                <div class="card-body">

                                    <h5 class="card-title">{{ $log->title }}
                                        <span class="text-primary">{{ $log->log }}</span>
                                    </h5>

                                    @if ($log->user)
                                        <h6 class="card-subtitle mb-2 text-muted">{{ $log->user->name }}</h6>
                                    @else
                                        <h6 class="card-subtitle mb-2 text-muted"> محاولة تسجيل دخول</h6>
                                    @endif
                                    <p class="card-text">{{ $log->created_at->format('Y-m-d h:i:s') }}</p>
                                    {{-- <a href="#!" class="card-link">Card link</a>
                        <a href="#!" class="card-link">Another link</a> --}}
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                @if (isset($query))
                    <span id="paginate">{{ $logs->appends($query)->links('pagination::bootstrap-4') }}</span>
                @else
                    <span id="paginate">{{ $logs->links('pagination::bootstrap-4') }}</span>
                @endif

            </div>
            <!-- [ Main Content ] end -->

            <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
                aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">{{ __('messages.searchDate') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form action="{{ route('log.create') }}" method="GET">
                            @csrf
                            <div class="modal-body">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="recipient-name"
                                                class="col-form-label">{{ __('messages.startDate') }}</label>
                                            <input type="date" name="startDate"
                                            @if (isset($query['startDate']))
                                                value="{{ $query['startDate'] }}"
                                            @endif
                                                placeholder="من التاريخ" class="form-control">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="message-text"
                                                class="col-form-label">{{ __('messages.endDate') }}</label>
                                            <input type="date" name="endDate"
                                            @if (isset($query['org_id']))
                                                value="{{ $query['org_id'] }}"
                                            @endif
                                                placeholder="الي التاريخ" class="form-control">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="message-text" class="col-form-label">تحديد حركات مستخدم معين</label>
                                            <select class="form-select" name="org_id" id="type-select">
                                                <option label="تحديد مستخدم"></option>
                                                @foreach ($orgs as $org)
                                                    @if (isset($query['org_id']) && $query['org_id'] == $org->id)
                                                        <option selected value="{{ $org->id }}">{{ $org->name }}
                                                        </option>
                                                    @else
                                                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="message-text" class="col-form-label">تحديد نوع الحركة</label>
                                            <select class="form-select" name="type" id="type-select">
                                                <option label="تحديد نوع الحركة"></option>

                                                @if (isset($query['type']) && $query['type'] == 'customer')
                                                <option selected value="customer">الزبائن</option>
                                                @else
                                                <option value="customer">الزبائن</option>
                                                @endif

                                                @if (isset($query['type']) && $query['type'] == 'dept')
                                                <option selected value="dept">الديون</option>
                                                @else
                                                <option value="dept">الديون</option>
                                                @endif

                                                @if (isset($query['type']) && $query['type'] == 'system')
                                                <option selected value="system">النظام</option>
                                                @else
                                                <option value="system">النظام</option>
                                                @endif

                                                @if (isset($query['type']) && $query['type'] == 'invoice')
                                                <option selected value="invoice">فاتورة</option>
                                                @else
                                                <option value="invoice">فاتورة</option>
                                                @endif

                                                @if (isset($query['type']) && $query['type'] == 'notification')
                                                <option selected value="notification">الزبائن</option>
                                                @else
                                                <option value="notification">الزبائن</option>
                                                @endif

                                                @if (isset($query['type']) && $query['type'] == 'order')
                                                <option selected value="order">الطلبات</option>
                                                @else
                                                <option value="order">الطلبات</option>
                                                @endif

                                                @if (isset($query['type']) && $query['type'] == 'paymentExpenses')
                                                <option selected value="paymentExpenses">مصروفات الدفع</option>
                                                @else
                                                <option value="paymentExpenses">مصروفات الدفع</option>
                                                @endif

                                                @if (isset($query['type']) && $query['type'] == 'platform')
                                                <option selected value="platform">المنصات</option>
                                                @else
                                                <option value="platform">المنصات</option>
                                                @endif

                                                @if (isset($query['type']) && $query['type'] == 'purchase')
                                                <option selected value="purchase">المشتريات</option>
                                                @else
                                                <option value="purchase">المشتريات</option>
                                                @endif


                                                @if (isset($query['type']) && $query['type'] == 'description')
                                                <option selected value="description">وصف المشتريات</option>
                                                @else
                                                <option value="description">وصف المشتريات</option>
                                                @endif

                                                @if (isset($query['type']) && $query['type'] == 'shippingCharge')
                                                <option selected value="shippingCharge">مصروفات الشحن</option>
                                                @else
                                                <option value="shippingCharge">مصروفات الشحن</option>
                                                @endif

                                                @if (isset($query['type']) && $query['type'] == 'shippingType')
                                                <option selected value="shippingType">انواع الشحن</option>
                                                @else
                                                <option value="shippingType">انواع الشحن</option>
                                                @endif

                                                @if (isset($query['type']) && $query['type'] == 'Treasury')
                                                <option selected value="Treasury">قيمة الخزينة</option>
                                                @else
                                                <option value="Treasury">قيمة الخزينة</option>
                                                @endif

                                                @if (isset($query['type']) && $query['type'] == 'TreasuryHistory')
                                                <option selected value="TreasuryHistory">سجل الخزينة</option>
                                                @else
                                                <option value="TreasuryHistory">سجل الخزينة</option>
                                                @endif

                                                @if (isset($query['type']) && $query['type'] == 'trip')
                                                <option selected value="trip">الرحلات</option>
                                                @else
                                                <option value="trip">الرحلات</option>
                                                @endif

                                                @if (isset($query['type']) && $query['type'] == 'user')
                                                <option selected value="user">المستخدمين</option>
                                                @else
                                                <option value="user">المستخدمين</option>
                                                @endif

                                                @if (isset($query['type']) && $query['type'] == 'login')
                                                <option selected value="login">تسجيل الدخول</option>
                                                @else
                                                <option value="login">تسجيل الدخول</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>

                                </div>

                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary"
                                    data-bs-dismiss="modal">{{ __('messages.close') }}</button>
                                <button type="submit" class="btn btn-primary">{{ __('messages.search') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

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
